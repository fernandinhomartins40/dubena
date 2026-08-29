<?php

namespace App\Models\Produto;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnidadeMedida extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'unidadesmedida';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'sigla', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
