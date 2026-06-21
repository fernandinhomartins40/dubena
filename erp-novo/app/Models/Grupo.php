<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo = rede de empresas (legado: empresas_grupos). Escopo "grupo" de muitos
 * cadastros de apoio.
 */
class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos';

    protected $fillable = ['descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }
}
