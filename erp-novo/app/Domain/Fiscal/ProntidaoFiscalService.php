<?php

namespace App\Domain\Fiscal;

use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Produto\Produto;

/**
 * "Pronto para emitir?" — tudo o que a emissão exige, conferido ANTES.
 *
 * Hoje cada falta só aparece como erro na hora de transmitir a nota, uma de
 * cada vez: envia o certificado, falha pelo CEP; corrige o CEP, falha pelo
 * município. Aqui a lista inteira aparece de uma vez, com o que fazer.
 *
 * As regras espelham as de `NFePHPSefazDriver::dadosEmitente()` e
 * `configuracaoDoTools()` (mesmas fontes, mesmos limites): se divergirem, este
 * diagnóstico diria "pronto" para uma nota que a emissão recusa.
 */
class ProntidaoFiscalService
{
    public function __construct(private CertificadoService $certificados) {}

    /** @return array{pronto: bool, itens: list<array{chave: string, titulo: string, ok: bool, detalhe: string|null}>} */
    public function verificar(Empresa $empresa): array
    {
        $empresa->loadMissing('cidadeCadastro.municipio');
        $fiscal = ConfigFiscal::withoutTenant()->where('empresa_id', $empresa->id)->first();
        $config = EmpresaConfig::query()->where('empresa_id', $empresa->id)->first();
        $dados = $config?->dados ?? [];
        $emiteNfce = (bool) ($dados['fiscal']['nfce_emite'] ?? false);
        $digitos = fn (?string $v) => preg_replace('/\D/', '', (string) $v) ?? '';

        $itens = [];
        $item = function (string $chave, string $titulo, bool $ok, ?string $seFalta) use (&$itens) {
            $itens[] = ['chave' => $chave, 'titulo' => $titulo, 'ok' => $ok, 'detalhe' => $ok ? null : $seFalta];
        };

        // ── Certificado ──
        $cert = $config ? $this->certificados->status($config) : ['tem_certificado' => false];
        $item('certificado', 'Certificado digital A1', (bool) ($cert['tem_certificado'] ?? false) && ! ($cert['expirado'] ?? false),
            ($cert['tem_certificado'] ?? false) ? 'O certificado enviado está vencido. Envie o novo (.pfx) na aba Fiscal.' : 'Envie o arquivo .pfx e a senha na aba Fiscal / NF-e.');
        if (($cert['tem_certificado'] ?? false) && $config?->cert_cnpj) {
            $item('certificado_cnpj', 'Certificado é desta empresa', $digitos($config->cert_cnpj) === $digitos($empresa->cnpj),
                'O certificado enviado é de outro CNPJ. Envie o certificado desta empresa.');
        }

        // ── Configuração de emissão ──
        $item('config_fiscal', 'Ambiente e regime tributário', $fiscal !== null && in_array((int) $fiscal->ambiente, [1, 2], true) && in_array((int) $fiscal->regime_tributario, [1, 2, 3, 4], true),
            'Escolha o ambiente e o regime tributário (CRT) na aba Fiscal.');
        if ($emiteNfce) {
            $item('csc', 'CSC da NFC-e', $fiscal !== null && filled($fiscal->csc_id) && filled($fiscal->getRawOriginal('csc_token')),
                'Informe o ID do CSC e o CSC (fornecidos pela SEFAZ para o ambiente escolhido) na aba Fiscal.');
        }

        // ── Dados do emitente (os que vão na nota) ──
        $item('razao_social', 'Razão social', filled(trim((string) $empresa->razao_social)), 'Preencha a razão social na aba Identificação.');
        $item('cnpj', 'CNPJ', strlen($digitos($empresa->cnpj)) === 14, 'O CNPJ precisa ter 14 dígitos (aba Identificação).');
        $item('ie', 'Inscrição estadual', filled(trim((string) $empresa->inscricao_estadual)), 'Preencha a inscrição estadual (aba Identificação).');
        $item('endereco', 'Endereço do emitente', filled(trim((string) $empresa->endereco)) && filled(trim((string) $empresa->numero)) && filled(trim((string) $empresa->bairro)),
            'Rua, número e bairro da empresa (aba Endereço).');
        $item('cep', 'CEP do emitente', strlen($digitos($empresa->cep)) === 8, 'O CEP da empresa precisa ter 8 dígitos (aba Endereço).');
        $municipio = $empresa->cidadeCadastro?->municipio;
        $item('municipio', 'Município com código IBGE', $municipio !== null && (int) $municipio->cod_ibge >= 1000000
            && strtoupper((string) $municipio->uf) === strtoupper((string) $empresa->uf),
            'A cidade da empresa precisa estar ligada ao município oficial (IBGE) e bater com a UF (Endereços → Cidades).');

        // ── Produtos ──
        $ativos = Produto::query()->where('empresa_id', $empresa->id)->where('ativo', true);
        $semNcm = (clone $ativos)->where(fn ($q) => $q->whereNull('ncm')->orWhere('ncm', ''))
            // NULL != 'servico' não é verdadeiro no SQL: sem o whereNull, produto
            // sem natureza sumiria da contagem.
            ->where(fn ($q) => $q->whereNull('natureza')->orWhere('natureza', '!=', 'servico'))->count();
        $semGrupo = (clone $ativos)->whereNull('grupo_fiscal_id')->count();
        $item('produtos_ncm', 'Produtos com NCM', $semNcm === 0, "{$semNcm} produto(s) ativo(s) sem NCM — a nota é recusada sem ele (Produtos → aba Fiscal).");
        $item('produtos_grupo', 'Produtos com grupo fiscal', $semGrupo === 0, "{$semGrupo} produto(s) ativo(s) sem grupo fiscal — o imposto é escolhido por ele (Produtos → aba Fiscal).");

        return ['pronto' => collect($itens)->every(fn ($i) => $i['ok']), 'itens' => $itens];
    }
}
