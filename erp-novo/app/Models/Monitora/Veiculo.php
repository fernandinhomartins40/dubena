<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Veiculo rastreado (modulo Monitora). Escopo por empresa.
 *
 * F3-09: `veiculo_frota_id` liga este registro ao cadastro de FROTA (km, troca
 * de oleo, documentos). O mesmo caminhao existe nas duas tabelas, e ate aqui
 * nada as ligava — a placa era a unica coisa em comum, e ninguem conferia se
 * batia. "Onde esta o caminhao que precisa trocar o oleo?" nao tinha resposta.
 *
 * O rastreador (`imei`) e um VINCULO, nao a identidade do veiculo: trocar de
 * rastreador nao cria um caminhao novo.
 */
class Veiculo extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'monitora_veiculos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'veiculo_frota_id', 'placa', 'descricao', 'tipo_id',
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

    /** Cadastro de frota do mesmo veiculo (km, manutencao, documentos). */
    public function frota(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Frota\Veiculo::class, 'veiculo_frota_id');
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
