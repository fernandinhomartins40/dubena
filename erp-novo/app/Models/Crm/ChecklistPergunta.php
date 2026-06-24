<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pergunta de um checklist — C10. Leaf puro: acessado apenas via o Checklist pai
 * (que é grupo-scoped). Sem coluna própria de tenant, o isolamento vem do pai —
 * não recebe trait (F02: leaf sem escopo independente).
 */
class ChecklistPergunta extends Model
{
    protected $table = 'checklist_perguntas';

    protected $fillable = ['checklist_id', 'pergunta', 'ordem'];

    protected function casts(): array
    {
        return ['ordem' => 'integer'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
