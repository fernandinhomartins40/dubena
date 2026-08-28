<?php

namespace App\Models\Frota;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/** Tipo de veículo (caminhão, moto, ...) — escopo por grupo. C6. */
class VeiculoTipo extends Model
{
    use BelongsToGrupo;

    protected $table = 'veiculo_tipos';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
