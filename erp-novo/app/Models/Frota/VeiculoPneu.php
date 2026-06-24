<?php

namespace App\Models\Frota;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pneu instalado num veículo — C6. */
class VeiculoPneu extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['veiculo_id' => 'veiculos'];

    protected $table = 'veiculo_pneus';

    protected $fillable = ['veiculo_id', 'posicao', 'marca', 'data_instalacao', 'km_instalacao'];

    protected function casts(): array
    {
        return ['data_instalacao' => 'date', 'km_instalacao' => 'integer'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
