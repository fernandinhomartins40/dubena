<?php

namespace App\Models\Frota;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/** Tipo de combustível (diesel, gasolina, GNV, ...) — escopo por grupo. C6. */
class TipoCombustivel extends Model
{
    use BelongsToGrupo;

    protected $table = 'tipo_combustiveis';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
