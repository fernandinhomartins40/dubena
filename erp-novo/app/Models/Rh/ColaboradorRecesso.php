<?php

namespace App\Models\Rh;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Recesso/férias/afastamento do colaborador — C5. */
class ColaboradorRecesso extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['colaborador_id' => 'colaboradores'];

    protected $table = 'colaborador_recessos';

    protected $fillable = ['colaborador_id', 'tipo', 'inicio', 'fim', 'observacao'];

    protected function casts(): array
    {
        return ['inicio' => 'date', 'fim' => 'date'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
