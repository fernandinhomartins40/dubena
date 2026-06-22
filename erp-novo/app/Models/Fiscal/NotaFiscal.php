<?php

namespace App\Models\Fiscal;

use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Fiscal\SituacaoNota;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use App\Models\Pedido\Pedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaFiscal extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'notas_fiscais';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'pedido_id', 'modelo', 'tipo',
        'serie', 'numero', 'chave', 'protocolo',
        'valor_produtos', 'valor_desconto', 'valor_frete', 'valor_icms', 'valor_icms_st',
        'valor_ipi', 'valor_pis', 'valor_cofins', 'valor_total',
        'situacao', 'motivo_rejeicao', 'emitida_em',
    ];

    protected function casts(): array
    {
        return [
            'modelo' => ModeloDocumento::class,
            'situacao' => SituacaoNota::class,
            'serie' => 'integer',
            'numero' => 'integer',
            'valor_produtos' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_frete' => 'decimal:2',
            'valor_icms' => 'decimal:2',
            'valor_icms_st' => 'decimal:2',
            'valor_ipi' => 'decimal:2',
            'valor_pis' => 'decimal:2',
            'valor_cofins' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'emitida_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NotaItem::class);
    }
}
