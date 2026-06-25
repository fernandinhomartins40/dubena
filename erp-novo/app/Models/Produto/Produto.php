<?php

namespace App\Models\Produto;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produto (classificação fiscal/GLP) — escopo por empresa. Valores numéricos
 * nativos (decimal), flags boolean. Legado: produtos (+ alters).
 */
class Produto extends Model
{
    use Auditavel;
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'produtos';

    protected $fillable = [
        'empresa_id', 'grupo_id',
        'descricao', 'produtoclasse_id', 'unidademedida_id',
        'vasilhame_retornavel', 'produto_retornavel_id', 'ativo', 'envia_app_nf',
        'dias_giro', 'observacao',
        'preco_venda', 'preco_venda_minimo', 'custo_medio', 'custo_frete',
        'preco_gasdopovo', 'peso_liquido', 'peso_bruto',
        'nfe_permite', 'sped', 'nfe_tipo_item', 'ncm', 'nfe_cest', 'ean', 'ean_trib',
        'especie', 'marca', 'nfe_cod_gen', 'nfe_cod_lst', 'nfe_aliq_ipi', 'nfe_bc_ipi',
        'nfe_cod_enquadramento_ipi', 'nfe_ex_tipi', 'nfe_descricao_fiscal', 'nfe_nat_rec',
        'tipo_glp', 'nfe_cprod_anp', 'nfe_desc_anp', 'nfe_qbc_prod', 'nfe_valiq_prod',
        'nfe_vcide', 'pgni', 'pgnn', 'pglp',
    ];

    protected function casts(): array
    {
        return [
            'vasilhame_retornavel' => 'boolean',
            'ativo' => 'boolean',
            'envia_app_nf' => 'boolean',
            'nfe_permite' => 'boolean',
            'sped' => 'boolean',
            'dias_giro' => 'integer',
            'preco_venda' => 'decimal:2',
            'preco_venda_minimo' => 'decimal:2',
            'custo_medio' => 'decimal:4',
            'custo_frete' => 'decimal:4',
            'preco_gasdopovo' => 'decimal:2',
            'peso_liquido' => 'decimal:3',
            'peso_bruto' => 'decimal:3',
            'nfe_aliq_ipi' => 'decimal:4',
            'nfe_bc_ipi' => 'decimal:2',
            'nfe_qbc_prod' => 'decimal:4',
            'nfe_valiq_prod' => 'decimal:4',
            'nfe_vcide' => 'decimal:4',
            'pgni' => 'decimal:4',
            'pgnn' => 'decimal:4',
            'pglp' => 'decimal:4',
        ];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(ProdutoClasse::class, 'produtoclasse_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'unidademedida_id');
    }

    public function origens(): HasMany
    {
        return $this->hasMany(ProdutoOrigem::class);
    }
}
