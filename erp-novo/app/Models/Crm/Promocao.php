<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/** Promoção / campanha — C10. Escopo por grupo. */
class Promocao extends Model
{
    use BelongsToGrupo;

    protected $table = 'promocoes';

    protected $fillable = ['grupo_id', 'descricao', 'inicio', 'fim', 'desconto_percentual', 'ativo'];

    protected function casts(): array
    {
        return ['inicio' => 'date', 'fim' => 'date', 'desconto_percentual' => 'decimal:2', 'ativo' => 'boolean'];
    }
}
