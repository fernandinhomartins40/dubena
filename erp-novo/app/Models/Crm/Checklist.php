<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modelo de checklist — C10. Escopo por grupo. */
class Checklist extends Model
{
    use BelongsToGrupo;

    protected $table = 'checklists';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function perguntas(): HasMany
    {
        return $this->hasMany(ChecklistPergunta::class)->orderBy('ordem');
    }
}
