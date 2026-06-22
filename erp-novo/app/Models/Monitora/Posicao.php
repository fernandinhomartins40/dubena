<?php

namespace App\Models\Monitora;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Posicao extends Model
{
    use HasFactory;

    protected $table = 'monitora_posicoes';

    protected $fillable = [
        'veiculo_id', 'latitude', 'longitude', 'velocidade', 'direcao', 'ignicao', 'registrado_em',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'velocidade' => 'decimal:2',
            'direcao' => 'integer',
            'ignicao' => 'boolean',
            'registrado_em' => 'datetime',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }
}
