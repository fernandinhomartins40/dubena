<?php

namespace App\Models\Geografico;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bairro — pertence a uma cidade; escopo por grupo. Legado: bairros.
 */
class Bairro extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'bairros';

    protected $fillable = ['grupo_id', 'cidade_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class);
    }
}
