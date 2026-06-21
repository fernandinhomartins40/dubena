<?php

namespace App\Models\Financeiro;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceiroParcela extends Model
{
    use HasFactory;

    protected $table = 'financeiroparcelas';

    protected $fillable = [
        'financeiro_id', 'numero', 'vencimento', 'valor', 'desconto',
        'valor_efetivado', 'baixado', 'datahora_baixa',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'vencimento' => 'date',
            'valor' => 'decimal:2',
            'desconto' => 'decimal:2',
            'valor_efetivado' => 'decimal:2',
            'baixado' => 'boolean',
            'datahora_baixa' => 'datetime',
        ];
    }

    public function financeiro(): BelongsTo
    {
        return $this->belongsTo(Financeiro::class);
    }
}
