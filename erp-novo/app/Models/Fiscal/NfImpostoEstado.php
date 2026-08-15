<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tributação por par origem_uf → destino_uf — porte de `NFIMPOSTOESTADOS`.
 *
 * Só entra em jogo quando a operação é INTERESTADUAL (no legado, idDest != 1):
 * o ImpostoDB busca a linha cujo origem/destino batem com o emitente/destinatário
 * e, não achando, ERRA em vez de faturar com alíquota errada — comportamento que a
 * ResolucaoTributariaService replica.
 */
class NfImpostoEstado extends Model
{
    use BelongsToTenant;

    protected $table = 'nf_imposto_estados';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'nf_imposto_id', 'origem_uf', 'destino_uf',
        // PJ
        'cst_icms', 'aliq_icms', 'perc_bc_icms', 'origem_icms', 'modalidade_bc_icms',
        'aliq_icms_st', 'perc_bc_icms_st', 'modalidade_bc_icms_st',
        'mva', 'mva_reduzido', 'aliq_diferimento', 'taxa_fecop',
        'mot_deson_icms', 'cod_beneficio',
        // PF / consumidor final
        'pf_cst_icms', 'pf_aliq_icms', 'pf_perc_bc_icms', 'pf_origem_icms',
        'pf_modalidade_bc_icms', 'pf_aliq_icms_st', 'pf_modalidade_bc_icms_st',
        'pf_mva', 'pf_taxa_fecop', 'pf_mot_deson_icms', 'pf_cod_beneficio',
        'pf_aliq_icms_dest',
        'legado_id',
    ];

    protected function casts(): array
    {
        return [
            'aliq_icms' => 'float', 'perc_bc_icms' => 'float',
            'aliq_icms_st' => 'float', 'perc_bc_icms_st' => 'float',
            'mva' => 'float', 'mva_reduzido' => 'float',
            'aliq_diferimento' => 'float', 'taxa_fecop' => 'float',
            'pf_aliq_icms' => 'float', 'pf_perc_bc_icms' => 'float',
            'pf_aliq_icms_st' => 'float', 'pf_mva' => 'float',
            'pf_taxa_fecop' => 'float', 'pf_aliq_icms_dest' => 'float',
        ];
    }

    public function imposto(): BelongsTo
    {
        return $this->belongsTo(NfImposto::class, 'nf_imposto_id');
    }
}
