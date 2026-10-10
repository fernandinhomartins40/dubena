<?php

namespace App\Models\Estoque;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Requisição de material entre setores — C11. Escopo por empresa. */
class EstoqueRequisicao extends Model
{
    use BelongsToTenant;

    protected $table = 'estoque_requisicoes';

    protected $fillable = [
        'empresa_id', 'setor_origem_id', 'setor_destino_id', 'produto_id',
        'quantidade', 'situacao', 'observacao', 'user_id',
    ];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:4'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function setorOrigem(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_origem_id');
    }

    public function setorDestino(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_destino_id');
    }
}
