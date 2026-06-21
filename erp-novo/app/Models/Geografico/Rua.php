<?php

namespace App\Models\Geografico;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rua/logradouro — pertence a uma cidade; escopo por grupo. Legado: ruas.
 */
class Rua extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'ruas';

    protected $fillable = ['grupo_id', 'cidade_id', 'descricao', 'cep', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class);
    }
}
