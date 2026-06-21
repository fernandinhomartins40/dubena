<?php

namespace App\Models\Apoio;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Base dos cadastros de apoio (descricao + ativo), escopados por grupo.
 * Models concretos definem $table e estendem $fillable/$casts com seus extras.
 */
abstract class CadastroApoio extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $fillable = ['grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
