<?php

namespace App\Models\Frota;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Troca de óleo de um veículo — C6. */
class VeiculoTrocaOleo extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['veiculo_id' => 'veiculos'];

    protected $table = 'veiculo_trocas_oleo';

    protected $fillable = ['veiculo_id', 'data', 'km', 'valor', 'observacao'];

    protected function casts(): array
    {
        return ['data' => 'date', 'km' => 'integer', 'valor' => 'decimal:2'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
