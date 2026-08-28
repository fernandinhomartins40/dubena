<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Pergunta de um checklist — C10. Leaf puro: acessado apenas via o Checklist pai
 * (que é grupo-scoped). Sem coluna própria de tenant, o isolamento vem do pai —
 * não recebe trait (F02: leaf sem escopo independente).
 */
class ChecklistPergunta extends Model
{
    protected $table = 'checklist_perguntas';

    protected $fillable = ['checklist_id', 'pergunta', 'ordem'];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->tenant_account_id === null && $model->checklist_id !== null) {
                $model->tenant_account_id = DB::table('checklists')->whereKey($model->checklist_id)->value('tenant_account_id');
            }
        });
    }

    protected function casts(): array
    {
        return ['ordem' => 'integer'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
