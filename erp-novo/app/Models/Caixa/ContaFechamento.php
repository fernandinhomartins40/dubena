<?php

namespace App\Models\Caixa;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaFechamento extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'contafechamentos';

    protected $fillable = [
        'empresa_id', 'conta_id', 'user_id', 'abertura', 'fechamento',
        'saldo_inicial', 'saldo_final', 'aberto',
    ];

    protected function casts(): array
    {
        return [
            'abertura' => 'datetime',
            'fechamento' => 'datetime',
            'saldo_inicial' => 'decimal:2',
            'saldo_final' => 'decimal:2',
            'aberto' => 'boolean',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class);
    }
}
