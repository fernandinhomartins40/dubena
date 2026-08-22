<?php

namespace App\Models\Cliente;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TipoPessoa;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente (também fornecedor/transportador) — escopo por empresa (BelongsToTenant).
 * Valores numéricos nativos; flags boolean. Legado: clientes (+ alters).
 */
class Cliente extends Model
{
    use Auditavel;
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'user_id',
        'nome', 'fantasia', 'tipopessoa_id', 'segmento_id', 'sexo', 'datanascimento', 'observacoes',
        'cpf', 'rg', 'cnpj', 'inscricao_estadual', 'indicador_ie', 'suframa',
        'cliente', 'fornecedor', 'transportador', 'simples', 'nfemite', 'gasdopovo', 'ativo',
        'endereco', 'numero', 'complemento', 'ponto_referencia', 'cep', 'uf',
        'cidade_id', 'bairro_id', 'rua_id', 'email',
        'latitude', 'longitude', 'location_type',
        'convenio', 'convenio_ativo', 'convenio_id', 'convenio_limite',
        'credito_limite', 'credito_saldo', 'data_ultima_compra',
    ];

    protected function casts(): array
    {
        return [
            'datanascimento' => 'date',
            'data_ultima_compra' => 'date',
            'desativado_em' => 'datetime',
            'indicador_ie' => 'integer',
            'cliente' => 'boolean',
            'fornecedor' => 'boolean',
            'transportador' => 'boolean',
            'simples' => 'boolean',
            'nfemite' => 'boolean',
            'gasdopovo' => 'boolean',
            'ativo' => 'boolean',
            'convenio' => 'boolean',
            'convenio_ativo' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'convenio_limite' => 'decimal:2',
            'credito_limite' => 'decimal:2',
            'credito_saldo' => 'decimal:2',
        ];
    }

    public function telefones(): HasMany
    {
        return $this->hasMany(ClienteTelefone::class);
    }

    public function interacoes(): HasMany
    {
        return $this->hasMany(ClienteInteracao::class);
    }

    public function dependentes(): HasMany
    {
        return $this->hasMany(ClienteDependente::class);
    }

    public function precos(): HasMany
    {
        return $this->hasMany(ClientePreco::class);
    }

    /** Quem tirou este cadastro da lista de ativos (trilha da desativacao). */
    public function desativadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'desativado_por');
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

    public function tipopessoa(): BelongsTo
    {
        return $this->belongsTo(TipoPessoa::class, 'tipopessoa_id');
    }

    public function segmento(): BelongsTo
    {
        return $this->belongsTo(Segmento::class, 'segmento_id');
    }
}
