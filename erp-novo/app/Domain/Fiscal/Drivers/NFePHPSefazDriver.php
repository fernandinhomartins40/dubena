<?php

namespace App\Domain\Fiscal\Drivers;

use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Fiscal\XmlNfeBuilder;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\NotaFiscal;
use Illuminate\Support\Facades\Storage;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;

/**
 * Driver SEFAZ REAL (C7b) — porta a lib NFePHP (Tools): assina o XML montado pelo
 * XmlNfeBuilder com o certificado A1 do tenant (C2) e transmite à SEFAZ.
 *
 * GATE EXTERNO: só funciona com certificado válido + webservice SEFAZ acessível
 * (ext-soap). Ativado por FISCAL_DRIVER=nfephp; em CI/homolog usa-se o Fake.
 * NÃO é exercido pela suíte automatizada — a validação real é na homologação fiscal.
 */
class NFePHPSefazDriver implements SefazDriver
{
    public function __construct(private XmlNfeBuilder $builder) {}

    public function transmitir(NotaFiscal $nota): array
    {
        try {
            $tools = $this->tools($nota);

            $emit = $this->dadosEmitente($nota);
            $dest = $this->dadosDestinatario($nota);

            $make = $this->builder->montar($nota, $emit, $dest);
            $xml = $make->monta();
            if (! $xml) {
                return $this->falha('Falha ao montar o XML: '.implode('; ', $make->getErrors()));
            }

            $assinado = $tools->signNFe($xml);
            $chave = $make->getChave();

            // Envio síncrono (lote) e leitura do protocolo de autorização.
            $resp = $tools->sefazEnviaLote([$assinado], random_int(1, 999999999), 1);
            $st = simplexml_load_string($resp);
            $cStat = (string) ($st->cStat ?? '');
            $autorizada = in_array($cStat, ['100', '150'], true); // 100=autorizado

            return [
                'autorizada' => $autorizada,
                'chave' => $chave,
                'protocolo' => (string) ($st->protNFe->infProt->nProt ?? ''),
                'motivo' => (string) ($st->xMotivo ?? $st->protNFe->infProt->xMotivo ?? 'Sem retorno'),
            ];
        } catch (\Throwable $e) {
            return $this->falha($e->getMessage());
        }
    }

    public function cancelar(NotaFiscal $nota, string $justificativa): array
    {
        try {
            $tools = $this->tools($nota);
            $resp = $tools->sefazCancela((string) $nota->chave, $justificativa, (string) $nota->protocolo);
            $st = simplexml_load_string($resp);
            $cStat = (string) ($st->cStat ?? $st->retEvento->infEvento->cStat ?? '');

            return [
                'cancelada' => in_array($cStat, ['101', '135', '155'], true),
                'protocolo' => (string) ($st->retEvento->infEvento->nProt ?? ''),
                'motivo' => (string) ($st->retEvento->infEvento->xMotivo ?? $st->xMotivo ?? 'Sem retorno'),
            ];
        } catch (\Throwable $e) {
            return ['cancelada' => false, 'protocolo' => null, 'motivo' => $e->getMessage()];
        }
    }

    public function inutilizar(int $empresaId, int $modelo, int $serie, int $numeroInicial, int $numeroFinal, string $justificativa): array
    {
        try {
            $tools = $this->toolsDaEmpresa($empresaId);
            $resp = $tools->sefazInutiliza($serie, $numeroInicial, $numeroFinal, $justificativa, $modelo);
            $st = simplexml_load_string($resp);
            $cStat = (string) ($st->infInut->cStat ?? $st->cStat ?? '');

            return [
                'inutilizada' => in_array($cStat, ['102', '563'], true), // 102=homologada, 563=já inutilizada
                'protocolo' => (string) ($st->infInut->nProt ?? ''),
                'motivo' => (string) ($st->infInut->xMotivo ?? $st->xMotivo ?? 'Sem retorno'),
            ];
        } catch (\Throwable $e) {
            return ['inutilizada' => false, 'protocolo' => null, 'motivo' => $e->getMessage()];
        }
    }

    public function cartaCorrecao(NotaFiscal $nota, string $correcao, int $sequencia): array
    {
        try {
            $tools = $this->tools($nota);
            $resp = $tools->sefazCCe((string) $nota->chave, $correcao, $sequencia);
            $st = simplexml_load_string($resp);
            $cStat = (string) ($st->cStat ?? $st->retEvento->infEvento->cStat ?? '');

            return [
                'registrada' => in_array($cStat, ['135', '136'], true), // 135=registrado, 136=registrado fora de prazo
                'protocolo' => (string) ($st->retEvento->infEvento->nProt ?? ''),
                'sequencia' => $sequencia,
                'motivo' => (string) ($st->retEvento->infEvento->xMotivo ?? $st->xMotivo ?? 'Sem retorno'),
            ];
        } catch (\Throwable $e) {
            return ['registrada' => false, 'protocolo' => null, 'sequencia' => $sequencia, 'motivo' => $e->getMessage()];
        }
    }

    /** Tools a partir só do empresa_id (inutilização não tem nota associada). */
    private function toolsDaEmpresa(int $empresaId): Tools
    {
        [$empresa, $fiscal, $certificado] = $this->configuracoesDaEmpresa($empresaId);
        $certificate = $this->certificado($empresa, $certificado);

        return new Tools(json_encode([
            'atualizacao' => now()->toDateString(),
            'tpAmb' => (int) $fiscal->ambiente,
            'razaosocial' => $empresa->razao_social,
            'cnpj' => $this->digitos($empresa->cnpj),
            'siglaUF' => strtoupper((string) $empresa->uf),
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
        ], JSON_THROW_ON_ERROR), $certificate);
    }

    /** Instancia Tools com o certificado A1 do tenant e a config da SEFAZ. */
    private function tools(NotaFiscal $nota): Tools
    {
        [$empresa, , $config] = $this->configuracoesDaEmpresa((int) $nota->empresa_id);
        $certificate = $this->certificado($empresa, $config);

        $emit = $this->dadosEmitente($nota);
        $configJson = json_encode([
            'atualizacao' => now()->toDateString(),
            'tpAmb' => (int) $emit['ambiente'],
            'razaosocial' => $emit['razao_social'],
            'cnpj' => $emit['cnpj'],
            'siglaUF' => $emit['uf'],
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
        ], JSON_THROW_ON_ERROR);

        return new Tools($configJson, $certificate);
    }

    /** @return array<string,mixed> */
    private function dadosEmitente(NotaFiscal $nota): array
    {
        $nota->loadMissing('empresa.cidadeCadastro.municipio', 'cliente');
        $empresa = $nota->empresa;
        if (! $empresa || (int) $empresa->id !== (int) $nota->empresa_id) {
            throw new \RuntimeException('Empresa emitente não pertence à nota fiscal.');
        }

        $fiscal = ConfigFiscal::withoutTenant()
            ->where('empresa_id', $nota->empresa_id)
            ->firstOrFail();
        $municipio = $empresa->cidadeCadastro?->municipio;
        $uf = strtoupper((string) $empresa->uf);
        $ufDestino = strtoupper((string) ($nota->cliente?->uf ?? ''));
        $natureza = trim((string) $nota->getAttribute('natureza_operacao'));

        $dados = [
            'razao_social' => trim((string) $empresa->razao_social),
            'nome_fantasia' => $empresa->nome_fantasia,
            'cnpj' => $this->digitos($empresa->cnpj),
            'ie' => trim((string) $empresa->inscricao_estadual),
            'uf' => $uf,
            'ambiente' => (int) $fiscal->ambiente,
            'crt' => (int) $fiscal->regime_tributario,
            'logradouro' => trim((string) $empresa->endereco),
            'numero' => trim((string) $empresa->numero),
            'bairro' => trim((string) $empresa->bairro),
            'cep' => $this->digitos($empresa->cep),
            'municipio' => trim((string) ($municipio?->nome ?? '')),
            'cod_municipio' => (int) ($municipio?->cod_ibge ?? 0),
            'cuf' => (int) ($municipio?->cod_uf ?? 0),
            'natureza_operacao' => $natureza,
            'id_dest' => $ufDestino === $uf ? 1 : 2,
            'consumidor_final' => blank($nota->cliente?->cnpj) ? 1 : 0,
        ];

        $erros = [];
        foreach (['razao_social', 'ie', 'logradouro', 'numero', 'bairro', 'municipio', 'natureza_operacao'] as $campo) {
            if ($dados[$campo] === '') {
                $erros[] = $campo;
            }
        }
        if (strlen($dados['cnpj']) !== 14) {
            $erros[] = 'cnpj';
        }
        if (strlen($dados['cep']) !== 8) {
            $erros[] = 'cep';
        }
        if (! preg_match('/^[A-Z]{2}$/', $uf) || $ufDestino === '') {
            $erros[] = 'uf';
        }
        if ($dados['cod_municipio'] < 1000000 || $dados['cuf'] <= 0 || strtoupper((string) ($municipio?->uf ?? '')) !== $uf) {
            $erros[] = 'municipio_ibge';
        }
        if (! in_array($dados['ambiente'], [1, 2], true)) {
            $erros[] = 'ambiente';
        }
        if (! in_array($dados['crt'], [1, 2, 3, 4], true)) {
            $erros[] = 'regime_tributario';
        }
        if ($erros !== []) {
            throw new \RuntimeException('Cadastro fiscal incompleto da empresa: '.implode(', ', array_unique($erros)).'.');
        }

        return $dados;
    }

    /** @return array{0:Empresa,1:ConfigFiscal,2:EmpresaConfig} */
    private function configuracoesDaEmpresa(int $empresaId): array
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $fiscal = ConfigFiscal::withoutTenant()->where('empresa_id', $empresaId)->firstOrFail();
        if (! in_array((int) $fiscal->ambiente, [1, 2], true)) {
            throw new \RuntimeException('Ambiente fiscal inválido para esta empresa.');
        }
        $certificado = EmpresaConfig::query()->where('empresa_id', $empresaId)->firstOrFail();

        return [$empresa, $fiscal, $certificado];
    }

    private function certificado(Empresa $empresa, EmpresaConfig $config): Certificate
    {
        if (! $config->cert_path || ! Storage::disk('local')->exists($config->cert_path)) {
            throw new \RuntimeException('Certificado A1 não configurado para esta empresa (Fase C2).');
        }
        if ($config->cert_validade && $config->cert_validade->isPast()) {
            throw new \RuntimeException('Certificado A1 expirado para esta empresa.');
        }
        $cnpj = $this->digitos($empresa->cnpj);
        if ($config->cert_cnpj && $this->digitos($config->cert_cnpj) !== $cnpj) {
            throw new \RuntimeException('Certificado A1 pertence a outro CNPJ.');
        }

        return Certificate::readPfx(
            Storage::disk('local')->get($config->cert_path),
            (string) $config->cert_senha,
        );
    }

    private function digitos(mixed $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    /** @return array<string,mixed> */
    private function dadosDestinatario(NotaFiscal $nota): array
    {
        $cliente = $nota->cliente;

        return [
            'nome' => $cliente?->nome ?? 'CONSUMIDOR',
            'cnpj' => $cliente?->cnpj ? preg_replace('/\D/', '', (string) $cliente->cnpj) : null,
            'cpf' => $cliente?->cpf ? preg_replace('/\D/', '', (string) $cliente->cpf) : null,
        ];
    }

    /** @return array{autorizada:bool, chave:?string, protocolo:?string, motivo:?string} */
    private function falha(string $motivo): array
    {
        return ['autorizada' => false, 'chave' => null, 'protocolo' => null, 'motivo' => $motivo];
    }
}
