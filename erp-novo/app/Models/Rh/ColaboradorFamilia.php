<?php

namespace App\Models\Rh;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dependente/familiar do colaborador — C5. */
class ColaboradorFamilia extends Model
{
    // PIE P2-E-11: folha (comissão, ponto) e saúde (ASO) mudavam sem trilha.
    use Auditavel, BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['colaborador_id' => 'colaboradores'];

    protected $table = 'colaborador_familias';

    protected $fillable = ['colaborador_id', 'nome', 'parentesco', 'data_nascimento'];

    protected function casts(): array
    {
        return ['data_nascimento' => 'date'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
