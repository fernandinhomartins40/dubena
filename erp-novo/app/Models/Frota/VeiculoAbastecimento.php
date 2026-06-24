<?php

namespace App\Models\Frota;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Abastecimento de um veículo — C6 (base do consumo médio). */
class VeiculoAbastecimento extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['veiculo_id' => 'veiculos'];

    protected $table = 'veiculo_abastecimentos';

    protected $fillable = [
        'veiculo_id', 'data', 'km', 'litros', 'valor_litro', 'valor_total', 'tanque_cheio',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'km' => 'integer',
            'litros' => 'decimal:3',
            'valor_litro' => 'decimal:3',
            'valor_total' => 'decimal:2',
            'tanque_cheio' => 'boolean',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
