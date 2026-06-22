<?php

namespace App\Models\Cobranca;

use App\Domain\Cobranca\SituacaoBoleto;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Boleto extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'boletos';

    protected $fillable = [
        'empresa_id', 'financeiroparcela_id', 'cliente_id', 'banco_codigo', 'carteira',
        'nosso_numero', 'linha_digitavel', 'codigo_barras', 'valor', 'vencimento', 'situacao',
    ];

    protected function casts(): array
    {
        return [
            'banco_codigo' => 'integer',
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'situacao' => SituacaoBoleto::class,
        ];
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(FinanceiroParcela::class, 'financeiroparcela_id');
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(BoletoOcorrencia::class);
    }
}
