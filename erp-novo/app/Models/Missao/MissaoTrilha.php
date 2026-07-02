<?php

namespace App\Models\Missao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ponto da trilha GPS de uma missão (L7). Gravado em lote pelo app durante a
 * execução — base do percurso/distância/tempo por casa na auditoria (L9).
 * Sem updated_at (só inserção; imutável na prática). Tenant-scoped.
 */
class MissaoTrilha extends Model
{
    use BelongsToTenant;

    protected $table = 'missao_trilha';

    public $timestamps = false;

    protected $fillable = ['empresa_id', 'missao_atribuicao_id', 'latitude', 'longitude', 'registrado_em'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'registrado_em' => 'datetime',
        ];
    }

    public function atribuicao(): BelongsTo
    {
        return $this->belongsTo(MissaoAtribuicao::class, 'missao_atribuicao_id');
    }
}
