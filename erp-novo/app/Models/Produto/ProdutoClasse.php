<?php

namespace App\Models\Produto;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdutoClasse extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'produtoclasses';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
