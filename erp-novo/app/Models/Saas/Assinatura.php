<?php

namespace App\Models\Saas;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assinatura da empresa a um plano (P2) — TENANT (escopada por empresa_id).
 *
 * Status: trial | ativa | inadimplente | cancelada. A assinatura é considerada
 * "vigente" (libera os recursos do plano) quando trial/ativa e dentro da janela
 * de datas — ver LicencaService.
 */
class Assinatura extends Model
{
    use BelongsToTenant;

    public const STATUS_TRIAL = 'trial';

    public const STATUS_ATIVA = 'ativa';

    public const STATUS_INADIMPLENTE = 'inadimplente';

    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'assinaturas';

    protected $fillable = ['empresa_id', 'plano_id', 'status', 'inicio', 'fim', 'trial_ate'];

    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
            'trial_ate' => 'date',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * A assinatura está vigente HOJE? (libera os recursos do plano)
     *  - cancelada/inadimplente → não;
     *  - trial → só até trial_ate (quando definido);
     *  - ativa → dentro de [inicio, fim] (datas nulas = sem limite naquele lado).
     */
    public function vigente(): bool
    {
        $hoje = now()->startOfDay();

        if (in_array($this->status, [self::STATUS_CANCELADA, self::STATUS_INADIMPLENTE], true)) {
            return false;
        }

        if ($this->status === self::STATUS_TRIAL) {
            return $this->trial_ate === null || $this->trial_ate->gte($hoje);
        }

        // ativa
        $depoisDoInicio = $this->inicio === null || $this->inicio->lte($hoje);
        $antesDoFim = $this->fim === null || $this->fim->gte($hoje);

        return $depoisDoInicio && $antesDoFim;
    }
}
