<?php

namespace App\Models\Monitora;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot da última posição do veículo (leitura rápida; mantido na ingestão).
 */
class UltimaPosicao extends Model
{
    use HasFactory;

    protected $table = 'monitora_ultima_posicao';

    protected $fillable = [
        'veiculo_id', 'latitude', 'longitude', 'velocidade', 'ignicao', 'registrado_em',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'velocidade' => 'decimal:2',
            'ignicao' => 'boolean',
            'registrado_em' => 'datetime',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }
}
