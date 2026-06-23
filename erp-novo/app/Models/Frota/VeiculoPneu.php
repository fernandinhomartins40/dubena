<?php

namespace App\Models\Frota;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pneu instalado num veículo — C6. */
class VeiculoPneu extends Model
{
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
