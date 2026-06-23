<?php

namespace App\Models\Rh;

use App\Domain\Tenant\BelongsToTenant;
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
        'nome', 'cpf', 'rg', 'data_nascimento', 'data_admissao', 'data_desligamento',
        'telefone', 'entregador', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'data_admissao' => 'date',
            'data_desligamento' => 'date',
            'entregador' => 'boolean',
            'ativo' => 'boolean',
        ];
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
}
