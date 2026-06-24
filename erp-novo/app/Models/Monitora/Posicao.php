<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Posicao extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['veiculo_id' => 'monitora_veiculos'];

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
