<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Resposta de um item de execução de checklist — C10. */
class ChecklistResposta extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['checklist_execucao_id' => 'checklist_execucoes'];

    protected $table = 'checklist_respostas';

    protected $fillable = ['checklist_execucao_id', 'checklist_pergunta_id', 'conforme', 'observacao'];

    protected function casts(): array
    {
        return ['conforme' => 'boolean'];
    }
}
