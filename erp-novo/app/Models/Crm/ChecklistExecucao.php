<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Execução de um checklist — C10. Escopo por empresa. */
class ChecklistExecucao extends Model
{
    use BelongsToTenant;

    protected $table = 'checklist_execucoes';

    protected $fillable = ['checklist_id', 'empresa_id', 'user_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'date'];
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(ChecklistResposta::class);
    }
}
