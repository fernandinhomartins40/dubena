<?php

namespace App\Models\Telefonia;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chamada tocando agora — a FILA da bina (T4.4).
 *
 * Efêmera por natureza: nasce quando o PABX avisa, morre quando o operador
 * atende, rejeita ou o tempo passa. O histórico que sobrevive é `Ligacao`.
 */
class ChamadaEntrante extends Model
{
    use BelongsToTenant;

    protected $table = 'telefonia_chamadas';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'telefone', 'ramal', 'cliente_id', 'recebida_em',
    ];

    protected function casts(): array
    {
        return ['recebida_em' => 'datetime'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
