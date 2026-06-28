<?php

namespace App\Models\Produto;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Financeiro\CondicaoPagamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preço por (produto × condição de pagamento) — F3c (paridade com o legado).
 * Escopo por empresa. `gasdopovo` separa a tabela de preços do programa GP.
 */
class ProdutoCondicaoPreco extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'produto_condicao_precos';

    /** @var array<string,string> herança de empresa_id ao criar via relação/sem tenant. */
    protected $tenantParent = ['produto_id' => 'produtos'];

    protected $fillable = [
        'empresa_id', 'produto_id', 'condicaopagamento_id', 'gasdopovo', 'valor',
    ];

    protected function casts(): array
    {
        return [
            'gasdopovo' => 'boolean',
            'valor' => 'decimal:2',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function condicao(): BelongsTo
    {
        return $this->belongsTo(CondicaoPagamento::class, 'condicaopagamento_id');
    }
}
