<?php

namespace App\Models\Frota;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrada/saída de veículo do pátio (F12) — tenant via empresa_id (herda do veículo). */
class VeiculoEntradaSaida extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação). */
    protected $tenantParent = ['veiculo_id' => 'veiculos'];

    protected $table = 'veiculo_entradas_saidas';

    protected $fillable = ['empresa_id', 'veiculo_id', 'colaborador_id', 'tipo', 'datahora', 'km', 'observacao'];

    protected function casts(): array
    {
        return ['datahora' => 'datetime', 'km' => 'integer'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
