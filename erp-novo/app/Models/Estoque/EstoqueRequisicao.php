<?php

namespace App\Models\Estoque;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

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
}
