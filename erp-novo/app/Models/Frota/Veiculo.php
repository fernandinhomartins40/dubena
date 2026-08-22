<?php

namespace App\Models\Frota;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Veículo da frota (negócio) — escopo por empresa. C6.
 * Distinto do monitora_veiculos (GPS).
 */
class Veiculo extends Model
{
    use Auditavel, BelongsToTenant;
    use HasFactory;

    protected $table = 'veiculos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'veiculotipo_id', 'tipocombustivel_id',
        'placa', 'descricao', 'renavam', 'km_atual', 'km_troca_oleo',
        'km_ultima_troca_oleo', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'km_atual' => 'integer',
            'km_troca_oleo' => 'integer',
            'km_ultima_troca_oleo' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function abastecimentos(): HasMany
    {
        return $this->hasMany(VeiculoAbastecimento::class);
    }

    public function trocasOleo(): HasMany
    {
        return $this->hasMany(VeiculoTrocaOleo::class);
    }

    public function pneus(): HasMany
    {
        return $this->hasMany(VeiculoPneu::class);
    }

    public function entradasSaidas(): HasMany
    {
        return $this->hasMany(VeiculoEntradaSaida::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(VeiculoDocumento::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(VeiculoTipo::class, 'veiculotipo_id');
    }

    public function combustivel(): BelongsTo
    {
        return $this->belongsTo(TipoCombustivel::class, 'tipocombustivel_id');
    }
}
