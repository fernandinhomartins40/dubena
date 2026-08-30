<?php

namespace App\Models\Saas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teto numérico de um plano (F2-03). `valor` nulo = ilimitado.
 *
 * Sem linha para uma chave, o plano não declara aquele teto — o que também
 * significa ilimitado. A diferença entre "declarou nulo" e "não declarou" é só
 * de intenção; ambos liberam.
 */
class PlanoLimite extends Model
{
    protected $table = 'plano_limites';

    protected $fillable = ['plano_id', 'limite_chave', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'integer'];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }
}
