<?php

namespace App\Models;

use App\Domain\Shared\Auditavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Empresa = tenant operacional (revenda). Legado: empresas.
 * Config fiscal detalhada virá em EmpresaConfig (N1). Valores numéricos são
 * tipos nativos (decimal), não string.
 */
class Empresa extends Model
{
    use Auditavel;
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'grupo_id', 'razao_social', 'nome_fantasia', 'nome_informal',
        'cnpj', 'inscricao_estadual', 'inscricao_municipal',
        'cep', 'uf', 'cidade', 'bairro', 'endereco', 'numero', 'complemento',
        'telefone1', 'telefone2', 'latitude', 'longitude', 'matriz', 'ativo',
        'app_marketplace_ativo', 'raio_entrega_km',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'matriz' => 'boolean',
            'ativo' => 'boolean',
            'app_marketplace_ativo' => 'boolean',
            'raio_entrega_km' => 'decimal:2',
        ];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_user');
    }

    public function config(): HasOne
    {
        return $this->hasOne(EmpresaConfig::class);
    }
}
