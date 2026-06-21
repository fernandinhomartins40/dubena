<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permissão granular "modulo.acao" (ex.: cliente.view, financeiro.baixar,
 * caixa.estornar). Substitui o esquema legado de menuusers.
 */
class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = ['chave', 'descricao'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
