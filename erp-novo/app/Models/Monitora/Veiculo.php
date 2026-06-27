<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Veículo rastreado (módulo Monitora). Escopo por empresa.
 */
class Veiculo extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'monitora_veiculos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'placa', 'descricao', 'tipo_id',
        'motorista', 'km_atual', 'imei', 'deviceid', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'km_atual' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(VeiculoTipo::class, 'tipo_id');
    }

    public function posicoes(): HasMany
    {
        return $this->hasMany(Posicao::class, 'veiculo_id');
    }

    public function ultimaPosicao(): HasOne
    {
        return $this->hasOne(UltimaPosicao::class, 'veiculo_id');
    }
}
