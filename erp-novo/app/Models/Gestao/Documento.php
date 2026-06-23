<?php

namespace App\Models\Gestao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Documento (gestão documental) — C11. Escopo por empresa. */
class Documento extends Model
{
    use BelongsToTenant;

    protected $table = 'documentos';

    protected $fillable = ['empresa_id', 'tipo', 'descricao', 'numero', 'emissao', 'validade', 'arquivo_path'];

    protected $hidden = ['arquivo_path'];

    protected function casts(): array
    {
        return ['emissao' => 'date', 'validade' => 'date'];
    }
}
