<?php

namespace App\Models\Caixa;

use App\Domain\Caixa\SituacaoCheque;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cheque recebido/emitido — escopo por empresa. Situação como enum (máquina de estados).
 */
class Cheque extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'cheques';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'banco_id', 'especie', 'numero',
        'agencia', 'conta_corrente', 'titular', 'valor', 'bom_para', 'situacao',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'bom_para' => 'date',
            'situacao' => SituacaoCheque::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
