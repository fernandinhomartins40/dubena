<?php

namespace App\Models\Rh;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Colaborador (funcionário) — escopo por empresa. C5.
 */
class Colaborador extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'colaboradores';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cargo_id', 'user_id',
        'nome', 'cpf', 'cnpj', 'rg', 'data_nascimento', 'data_admissao', 'data_desligamento',
        'telefone', 'entregador', 'ativo',
        // F1 — sob qual relação trabalha (CLT, franqueado PJ, vendedor industrial).
        // `entregador` responde "faz entrega?"; `vinculo` responde "sob qual relação".
        'vinculo',
        // Endereço: o legado sempre teve (81 colaboradores com cidade/bairro) e
        // o formulário da SPA já enviava — faltava a coluna no destino.
        'cep', 'uf', 'cidade_id', 'bairro_id', 'rua_id', 'numero', 'complemento',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'data_admissao' => 'date',
            'data_desligamento' => 'date',
            'entregador' => 'boolean',
            'ativo' => 'boolean',
            'vinculo' => \App\Domain\Rh\VinculoColaborador::class,
        ];
    }

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class);
    }

    public function bairro(): BelongsTo
    {
        return $this->belongsTo(Bairro::class);
    }

    public function rua(): BelongsTo
    {
        return $this->belongsTo(Rua::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function familias(): HasMany
    {
        return $this->hasMany(ColaboradorFamilia::class);
    }

    public function recessos(): HasMany
    {
        return $this->hasMany(ColaboradorRecesso::class);
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(ColaboradorComissao::class);
    }

    public function exames(): HasMany
    {
        return $this->hasMany(ColaboradorExame::class);
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(ColaboradorTurno::class);
    }

    public function pontos(): HasMany
    {
        return $this->hasMany(ColaboradorPonto::class);
    }
}
