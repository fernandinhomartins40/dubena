<?php

namespace App\Models\Financeiro;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceiroRateio extends Model
{
    use HasFactory;

    protected $table = 'financeirorateios';

    protected $fillable = ['financeiro_id', 'planoconta_id', 'centrocusto_id', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2'];
    }

    public function financeiro(): BelongsTo
    {
        return $this->belongsTo(Financeiro::class);
    }
}
