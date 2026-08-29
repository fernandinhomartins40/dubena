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

    // `tenant_account_id` participa da ponte documental: `BelongsToGrupo` so
    // preenche a chave em models que a declaram, e o valor vem do envelope
    // ativo — nunca do payload da requisicao.
    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
