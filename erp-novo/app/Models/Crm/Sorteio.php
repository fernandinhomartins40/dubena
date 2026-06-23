<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sorteio — C10. Escopo por grupo. */
class Sorteio extends Model
{
    use BelongsToGrupo;

    protected $table = 'sorteios';

    protected $fillable = ['grupo_id', 'descricao', 'data_sorteio', 'situacao', 'numero_sorteado'];

    protected function casts(): array
    {
        return ['data_sorteio' => 'date'];
    }

    public function numeros(): HasMany
    {
        return $this->hasMany(SorteioNumero::class);
    }
}
