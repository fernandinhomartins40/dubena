<?php

namespace App\Models\Financeiro;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceiroParcela extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['financeiro_id' => 'financeiros'];

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
