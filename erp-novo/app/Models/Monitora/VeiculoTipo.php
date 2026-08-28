<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de veículo rastreado (Monitora) — ícone no mapa + velocidade máxima (base do
 * relatório de excesso). Cadastro de apoio por grupo (legado: veiculotipos).
 */
class VeiculoTipo extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'monitora_veiculo_tipos';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'icone', 'velocidade_maxima', 'ativo'];

    protected function casts(): array
    {
        return [
            'velocidade_maxima' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class, 'tipo_id');
    }
}
