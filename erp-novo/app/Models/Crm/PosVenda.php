<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Pós-venda / pesquisa de satisfação — C10. Escopo por empresa. */
class PosVenda extends Model
{
    use BelongsToTenant;

    protected $table = 'pos_vendas';

    protected $fillable = ['empresa_id', 'cliente_id', 'pedido_id', 'data', 'nota', 'canal', 'observacao', 'situacao'];

    protected function casts(): array
    {
        return ['data' => 'date', 'nota' => 'integer'];
    }
}
