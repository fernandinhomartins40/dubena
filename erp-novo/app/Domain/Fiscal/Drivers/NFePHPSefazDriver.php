<?php

namespace App\Domain\Fiscal\Drivers;

use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Fiscal\XmlNfeBuilder;
use App\Models\EmpresaConfig;
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

    /** Instancia Tools com o certificado A1 do tenant e a config da SEFAZ. */
    private function tools(NotaFiscal $nota): Tools
    {
        $config = EmpresaConfig::query()->where('empresa_id', $nota->empresa_id)->firstOrFail();
        if (! $config->cert_path || ! Storage::disk('local')->exists($config->cert_path)) {
            throw new \RuntimeException('Certificado A1 não configurado para esta empresa (Fase C2).');
        }

        $pfx = Storage::disk('local')->get($config->cert_path);
        $certificate = Certificate::readPfx($pfx, (string) $config->cert_senha);

        $emit = $this->dadosEmitente($nota);
        $configJson = json_encode([
            'atualizacao' => now()->toDateString(),
            'tpAmb' => (int) ($emit['ambiente'] ?? 2),
            'razaosocial' => $emit['razao_social'] ?? '',
            'cnpj' => $emit['cnpj'] ?? '',
            'siglaUF' => $emit['uf'] ?? 'SP',
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
        ]);

        return new Tools($configJson, $certificate);
    }

    /** @return array<string,mixed> */
    private function dadosEmitente(NotaFiscal $nota): array
    {
        $empresa = $nota->empresa;

        return [
            'razao_social' => $empresa?->razao_social,
            'nome_fantasia' => $empresa?->nome_fantasia,
            'cnpj' => preg_replace('/\D/', '', (string) ($empresa?->cnpj ?? '')),
            'uf' => $empresa?->uf ?? 'SP',
            'ambiente' => 2,
        ];
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
