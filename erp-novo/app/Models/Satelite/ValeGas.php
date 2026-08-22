<?php

namespace App\Models\Satelite;

use App\Domain\Shared\Auditavel;
use App\Domain\Satelite\SituacaoValeGas;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValeGas extends Model
{
    use Auditavel, BelongsToTenant;
    use HasFactory;

    protected $table = 'vale_gas';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'produto_id', 'financeiro_id',
        'codigo', 'valor', 'validade', 'situacao', 'pedido_id', 'utilizado_em',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'validade' => 'date',
            'situacao' => SituacaoValeGas::class,
            'utilizado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
