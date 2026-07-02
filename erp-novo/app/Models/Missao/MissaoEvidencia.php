<?php

namespace App\Models\Missao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidência fotográfica de uma visita (L7) — fachada/panfleto/visita. O arquivo
 * fica no storage PRIVADO (mesmo disco das comprovações P7). Tenant-scoped.
 */
class MissaoEvidencia extends Model
{
    use BelongsToTenant;

    protected $table = 'missao_evidencias';

    protected $fillable = ['empresa_id', 'missao_visita_id', 'tipo', 'foto_path'];

    public function visita(): BelongsTo
    {
        return $this->belongsTo(MissaoVisita::class, 'missao_visita_id');
    }
}
