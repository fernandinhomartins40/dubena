<?php

namespace App\Domain\Fiscal;

use App\Models\Fiscal\NfImposto;
use App\Models\Fiscal\NfImpostoEstado;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * ResolucaoTributariaService — PORTE FIEL do `ImpostoDB` do legado
 * (ctrl-web/app/Processors/Nfe/Tributacao/ImpostoDB.php).
 *
 * Responde à pergunta que o CalculoImpostoService NÃO responde: *quais* CST,
 * alíquotas, MVA e reduções valem para este item. O cálculo já era um porte fiel;
 * o que faltava era a origem dos números — antes fixos ('00' / 18% / CFOP 5102).
 *
 * As duas regras estruturais do legado, preservadas:
 *
 *  1. PJ × consumidor final. Toda regra guarda dois conjuntos completos de
 *     tributos; `isConsumidorFinal` escolhe o conjunto `pf_*` (ImpostoDB::init).
 *
 *  2. Dentro do estado × interestadual. `idDest == 1` (mesma UF) usa `nf_impostos`;
 *     senão usa a linha de `nf_imposto_estados` do par origem→destino. O legado
 *     LANÇA EXCEÇÃO quando esse par não existe, em vez de faturar com alíquota
 *     errada (ImpostoDB::setImpostoInter) — aqui idem, com a mesma mensagem.
 *
 * Também replicado do legado: em operação interestadual o diferimento é zerado e,
 * para consumidor final, MVA/ST não se aplicam.
 */
class ResolucaoTributariaService
{
    /**
     * Resolve a tributação de um item e devolve o array de entrada do
     * CalculoImpostoService (as chaves que ele consome).
     *
     * @return array<string,mixed>
     */
    public function resolver(
        NfImposto $regra,
        string $ufEmitente,
        string $ufDestinatario,
        bool $consumidorFinal,
    ): array {
        $ufEmitente = strtoupper($ufEmitente);
        $ufDestinatario = strtoupper($ufDestinatario);
        $interestadual = $ufEmitente !== $ufDestinatario;

        $icms = $interestadual
            ? $this->icmsInterestadual($regra, $ufEmitente, $ufDestinatario, $consumidorFinal)
            : $this->icmsInterno($regra, $consumidorFinal);

        // PIS/COFINS não variam por UF no legado — saem sempre da regra base.
        $pisCofins = $consumidorFinal
            ? [
                'cst_pis' => $regra->pf_cst_pis ?? $regra->cst_pis,
                'aliq_pis' => (float) $regra->pf_aliq_pis,
                'perc_bc_pis' => (float) ($regra->pf_perc_bc_pis ?? 100),
                'cst_cofins' => $regra->pf_cst_cofins ?? $regra->cst_cofins,
                'aliq_cofins' => (float) $regra->pf_aliq_cofins,
                'perc_bc_cofins' => (float) ($regra->pf_perc_bc_cofins ?? 100),
            ]
            : [
                'cst_pis' => $regra->cst_pis,
                'aliq_pis' => (float) $regra->aliq_pis,
                'perc_bc_pis' => (float) ($regra->perc_bc_pis ?? 100),
                'cst_cofins' => $regra->cst_cofins,
                'aliq_cofins' => (float) $regra->aliq_cofins,
                'perc_bc_cofins' => (float) ($regra->perc_bc_cofins ?? 100),
            ];

        // DIFAL: só consumidor final em operação interestadual (regra do legado).
        $difal = $interestadual && $consumidorFinal;

        return array_merge($icms, $pisCofins, [
            'difal' => $difal,
            'aliq_fcp' => $icms['aliq_fcp'],
        ]);
    }

    /**
     * Operação DENTRO do estado (idDest == 1 no legado): usa a regra base.
     *
     * @return array<string,mixed>
     */
    private function icmsInterno(NfImposto $regra, bool $consumidorFinal): array
    {
        if ($consumidorFinal) {
            return [
                'cst_icms' => $regra->pf_cst_icms ?? $regra->cst_icms,
                'aliq_icms' => (float) $regra->pf_aliq_icms,
                'perc_bc_icms' => (float) ($regra->pf_perc_bc_icms ?? 100),
                'origem_icms' => $regra->pf_origem_icms,
                // Consumidor final não recolhe ST nem diferimento (ImpostoDB).
                'mva_st' => 0.0,
                'aliq_icms_st' => 0.0,
                'perc_bc_icms_st' => 100.0,
                'aliq_diferimento' => 0.0,
                'aliq_fcp' => (float) $regra->pf_taxa_fecop,
                'mot_deson_icms' => $regra->pf_mot_deson_icms,
                'cod_beneficio' => $regra->pf_cod_beneficio,
            ];
        }

        return [
            'cst_icms' => $regra->cst_icms,
            'aliq_icms' => (float) $regra->aliq_icms,
            'perc_bc_icms' => (float) ($regra->perc_bc_icms ?? 100),
            'origem_icms' => $regra->origem_icms,
            'mva_st' => (float) $regra->mva,
            'aliq_icms_st' => (float) $regra->aliq_icms_st,
            'perc_bc_icms_st' => (float) ($regra->perc_bc_icms_st ?? 100),
            'aliq_diferimento' => (float) $regra->aliq_diferimento,
            'aliq_fcp' => (float) $regra->taxa_fecop,
            'mot_deson_icms' => $regra->mot_deson_icms,
            'cod_beneficio' => $regra->cod_beneficio,
        ];
    }

    /**
     * Operação INTERESTADUAL: exige a linha do par origem→destino. Sem ela, erra —
     * como o legado, que prefere falhar a emitir com tributo errado.
     *
     * @return array<string,mixed>
     */
    private function icmsInterestadual(
        NfImposto $regra,
        string $ufEmitente,
        string $ufDestinatario,
        bool $consumidorFinal,
    ): array {
        $uf = $this->linhaDoEstado($regra, $ufEmitente, $ufDestinatario);

        if ($consumidorFinal) {
            return [
                'cst_icms' => $uf->pf_cst_icms ?? $uf->cst_icms,
                'aliq_icms' => (float) $uf->pf_aliq_icms,
                'perc_bc_icms' => (float) ($uf->pf_perc_bc_icms ?? 100),
                'origem_icms' => $uf->pf_origem_icms,
                'mva_st' => 0.0,
                'aliq_icms_st' => 0.0,
                'perc_bc_icms_st' => 100.0,
                // Fora do estado não há diferimento (comentário do próprio legado).
                'aliq_diferimento' => 0.0,
                'aliq_fcp' => (float) $uf->pf_taxa_fecop,
                'aliq_icms_dest' => (float) $uf->pf_aliq_icms_dest,
                'mot_deson_icms' => $uf->pf_mot_deson_icms,
                'cod_beneficio' => $uf->pf_cod_beneficio,
            ];
        }

        return [
            'cst_icms' => $uf->cst_icms,
            'aliq_icms' => (float) $uf->aliq_icms,
            'perc_bc_icms' => (float) ($uf->perc_bc_icms ?? 100),
            'origem_icms' => $uf->origem_icms,
            'mva_st' => (float) $uf->mva,
            'aliq_icms_st' => (float) $uf->aliq_icms_st,
            'perc_bc_icms_st' => (float) ($uf->perc_bc_icms_st ?? 100),
            'aliq_diferimento' => 0.0,
            'aliq_fcp' => (float) $uf->taxa_fecop,
            'aliq_icms_dest' => (float) $uf->pf_aliq_icms_dest,
            'mot_deson_icms' => $uf->mot_deson_icms,
            'cod_beneficio' => $uf->cod_beneficio,
        ];
    }

    private function linhaDoEstado(NfImposto $regra, string $origem, string $destino): NfImpostoEstado
    {
        $linha = $regra->relationLoaded('estados')
            ? $regra->estados->first(
                fn ($e) => $e->origem_uf === $origem && $e->destino_uf === $destino
            )
            : $regra->estados()
                ->where('origem_uf', $origem)
                ->where('destino_uf', $destino)
                ->first();

        if (! $linha) {
            // Mesma falha do legado (ImpostoDB::setImpostoInter).
            throw ValidationException::withMessages([
                'imposto' => "Não foi encontrado nenhum imposto saindo do {$origem} para {$destino} "
                    ."na operação fiscal #{$regra->operacao_fiscal_id}.",
            ]);
        }

        return $linha;
    }

    /**
     * Localiza a regra aplicável a um produto: casa a operação fiscal com o grupo
     * fiscal do produto; sem regra específica, cai na regra "coringa" da operação
     * (grupo_fiscal_id nulo). Devolve null quando não há regra alguma — o chamador
     * decide se erra ou usa o padrão.
     *
     * ## A data importa (F5-07)
     *
     * `$em` é a data do FATO GERADOR — a emissão da nota —, não "hoje". Alíquota
     * de ICMS muda por decreto estadual com data certa, e o GLP tem histórico
     * disso. Resolver pela data de hoje faria a reemissão de uma nota de
     * dezembro calcular com a alíquota de janeiro: o XML reemitido divergiria do
     * autorizado, em silêncio, e a divergência só apareceria na fiscalização.
     *
     * Quando várias versões já começaram, vale a **mais recente** — que é como
     * uma tabela de vigências se lê. Uma versão com início no futuro não vale
     * ainda, e é isso que permite cadastrar a alíquota nova em dezembro para
     * entrar sozinha em janeiro.
     */
    public function regraPara(int $empresaId, int $operacaoFiscalId, ?int $grupoFiscalId, ?string $em = null): ?NfImposto
    {
        $data = $em !== null ? Carbon::parse($em)->toDateString() : now()->toDateString();

        return NfImposto::query()
            ->where('empresa_id', $empresaId)
            ->where('operacao_fiscal_id', $operacaoFiscalId)
            ->where(fn ($q) => $q
                ->where('grupo_fiscal_id', $grupoFiscalId)
                ->orWhereNull('grupo_fiscal_id'))
            // whereDate e não comparação de texto: `vigencia_inicio` é `date`,
            // mas o cast do Eloquent serializa com hora, e o sqlite não trunca
            // (a armadilha do F5-11).
            ->where(fn ($q) => $q
                ->whereNull('vigencia_inicio')
                ->orWhereDate('vigencia_inicio', '<=', $data))
            ->where(fn ($q) => $q
                ->whereNull('vigencia_fim')
                ->orWhereDate('vigencia_fim', '>=', $data))
            // Regra específica do grupo fiscal ganha da coringa; entre versões,
            // ganha a que começou mais tarde.
            ->orderByRaw('grupo_fiscal_id IS NULL')
            ->orderByDesc('vigencia_inicio')
            ->with('estados')
            ->first();
    }
}
