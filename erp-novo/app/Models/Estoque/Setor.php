<?php

namespace App\Models\Estoque;

use App\Domain\Estoque\TipoLocalEstoque;
use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setor (local de estoque) — escopo por empresa. Legado: setors.
 *
 * F3-06: `tipo` diz que ESPECIE de lugar e. Sem ele, o deposito da revenda, o
 * estoque em poder de um franqueado e a carga de um veiculo conviviam na mesma
 * lista sem distincao — e o seletor de "onde lancar a entrada" oferecia os tres.
 */
class Setor extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'setores';

    protected $fillable = ['empresa_id', 'grupo_id', 'descricao', 'tipo', 'ativo'];

    /**
     * Default tambem no model: o de coluna so vale na linha ja gravada, e um
     * `new Setor` com `tipo = null` faria toda comparacao de tipo dar false por
     * acidente em vez de por decisao.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tipo' => 'DEPOSITO',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'tipo' => TipoLocalEstoque::class,
        ];
    }

    /** Locais onde se pode lancar entrada direta de mercadoria. */
    public function scopeArmazens(Builder $q): Builder
    {
        return $q->whereIn('tipo', [
            TipoLocalEstoque::DEPOSITO->value,
            TipoLocalEstoque::LOJA->value,
        ]);
    }
}
