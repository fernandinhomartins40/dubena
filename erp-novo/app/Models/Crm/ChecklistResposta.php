<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

/** Resposta de um item de execução de checklist — C10. */
class ChecklistResposta extends Model
{
    protected $table = 'checklist_respostas';

    protected $fillable = ['checklist_execucao_id', 'checklist_pergunta_id', 'conforme', 'observacao'];

    protected function casts(): array
    {
        return ['conforme' => 'boolean'];
    }
}
