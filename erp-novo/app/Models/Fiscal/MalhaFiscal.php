<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/** Malha fiscal genérica (CFOP/CST/NCM/... por tipo) — C12. Escopo por grupo. */
class MalhaFiscal extends Model
{
    use BelongsToGrupo;

    protected $table = 'malha_fiscal';

    protected $fillable = ['grupo_id', 'tipo', 'codigo', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
