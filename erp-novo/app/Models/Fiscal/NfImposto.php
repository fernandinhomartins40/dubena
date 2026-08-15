<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regra tributária por (operação fiscal × grupo fiscal) — porte de `NFIMPOSTOS`.
 *
 * Cada linha carrega DOIS conjuntos de tributos: o de PJ (colunas sem prefixo) e o
 * de consumidor final/PF (`pf_*`). Quem escolhe entre eles é a
 * ResolucaoTributariaService, replicando o `isConsumidorFinal` do ImpostoDB legado.
 *
 * `estados` é o desdobramento por par origem→destino UF, usado nas operações
 * interestaduais.
 */
class NfImposto extends Model
{
    use BelongsToTenant;

    protected $table = 'nf_impostos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'operacao_fiscal_id', 'grupo_fiscal_id',
        // ICMS (PJ)
        'cst_icms', 'aliq_icms', 'perc_bc_icms', 'origem_icms',
        'modalidade_bc_icms', 'aliq_icms_mono',
        // ICMS-ST (PJ)
        'aliq_icms_st', 'perc_bc_icms_st', 'modalidade_bc_icms_st', 'mva', 'mva_reduzido',
        // Outros ICMS (PJ)
        'aliq_diferimento', 'taxa_fecop', 'mot_deson_icms', 'cod_beneficio',
        // PIS/COFINS (PJ)
        'cst_pis', 'aliq_pis', 'perc_bc_pis', 'aliq_pis_credito',
        'cst_cofins', 'aliq_cofins', 'perc_bc_cofins', 'aliq_cofins_credito',
        // PF / consumidor final
        'pf_cst_icms', 'pf_aliq_icms', 'pf_perc_bc_icms', 'pf_origem_icms',
        'pf_modalidade_bc_icms', 'pf_aliq_icms_mono', 'pf_modalidade_bc_icms_st',
        'pf_mva', 'pf_taxa_fecop', 'pf_mot_deson_icms', 'pf_cod_beneficio',
        'pf_cst_pis', 'pf_aliq_pis', 'pf_perc_bc_pis', 'pf_aliq_pis_credito',
        'pf_cst_cofins', 'pf_aliq_cofins', 'pf_perc_bc_cofins',
        // Complementos
        'informacoes_adicionais', 'pf_informacoes_adicionais',
        'piscofins_tipo_credito', 'piscofins_nat_receita',
        'piscofins_tipo_bc_credito', 'piscofins_gera_credito',
        'legado_id',
    ];

    protected function casts(): array
    {
        return [
            'aliq_icms' => 'float', 'perc_bc_icms' => 'float', 'aliq_icms_mono' => 'float',
            'aliq_icms_st' => 'float', 'perc_bc_icms_st' => 'float',
            'mva' => 'float', 'mva_reduzido' => 'float',
            'aliq_diferimento' => 'float', 'taxa_fecop' => 'float',
            'aliq_pis' => 'float', 'perc_bc_pis' => 'float', 'aliq_pis_credito' => 'float',
            'aliq_cofins' => 'float', 'perc_bc_cofins' => 'float', 'aliq_cofins_credito' => 'float',
            'pf_aliq_icms' => 'float', 'pf_perc_bc_icms' => 'float', 'pf_aliq_icms_mono' => 'float',
            'pf_mva' => 'float', 'pf_taxa_fecop' => 'float',
            'pf_aliq_pis' => 'float', 'pf_perc_bc_pis' => 'float', 'pf_aliq_pis_credito' => 'float',
            'pf_aliq_cofins' => 'float', 'pf_perc_bc_cofins' => 'float',
        ];
    }

    public function operacaoFiscal(): BelongsTo
    {
        return $this->belongsTo(OperacaoFiscal::class, 'operacao_fiscal_id');
    }

    public function grupoFiscal(): BelongsTo
    {
        return $this->belongsTo(MalhaFiscal::class, 'grupo_fiscal_id');
    }

    public function estados(): HasMany
    {
        return $this->hasMany(NfImpostoEstado::class, 'nf_imposto_id');
    }
}
