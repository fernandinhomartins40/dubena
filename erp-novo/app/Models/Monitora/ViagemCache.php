<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Viagens já apuradas de um veículo num período.
 *
 * Guarda o resultado da segmentação para não varrer as posições do dia a cada
 * vez que a tela de rota é reaberta. Só recebe período encerrado — a decisão
 * está no `ViagensService`, que é quem sabe se o dia ainda está correndo.
 */
class ViagemCache extends Model
{
    use BelongsToTenant;

    protected $table = 'monitora_viagens_cache';

    protected $fillable = ['empresa_id', 'veiculo_id', 'de', 'ate', 'conteudo', 'hits'];

    protected function casts(): array
    {
        return [
            'conteudo' => 'array',
            'de' => 'date',
            'ate' => 'date',
            'hits' => 'integer',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }
}
