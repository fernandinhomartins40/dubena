<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaItem extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['nota_fiscal_id' => 'notas_fiscais'];

    use HasFactory;

    protected $table = 'nota_itens';

    protected $fillable = [
        'nota_fiscal_id', 'produto_id', 'numero_item', 'quantidade', 'valor_unitario',
        'valor_total', 'desconto', 'cfop', 'cst_icms',
        'bc_icms', 'aliq_icms', 'valor_icms', 'aliq_pis', 'valor_pis',
        'aliq_cofins', 'valor_cofins', 'aliq_ipi', 'valor_ipi',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario' => 'decimal:4',
            'valor_total' => 'decimal:2',
            'desconto' => 'decimal:2',
            'bc_icms' => 'decimal:2',
            'aliq_icms' => 'decimal:4',
            'valor_icms' => 'decimal:2',
            'aliq_pis' => 'decimal:4',
            'valor_pis' => 'decimal:2',
            'aliq_cofins' => 'decimal:4',
            'valor_cofins' => 'decimal:2',
            'aliq_ipi' => 'decimal:4',
            'valor_ipi' => 'decimal:2',
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class, 'nota_fiscal_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
