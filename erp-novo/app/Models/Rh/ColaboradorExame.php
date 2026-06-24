<?php

namespace App\Models\Rh;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Exame ocupacional (ASO) do colaborador — C5. */
class ColaboradorExame extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['colaborador_id' => 'colaboradores'];

    protected $table = 'colaborador_exames';

    protected $fillable = ['colaborador_id', 'tipo', 'realizado_em', 'vencimento', 'resultado', 'medico', 'observacao'];

    protected function casts(): array
    {
        return ['realizado_em' => 'date', 'vencimento' => 'date'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
