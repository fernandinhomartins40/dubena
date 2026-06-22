<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    protected $fillable = ['empresa_id', 'grupo_id', 'placa', 'descricao', 'imei', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
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
