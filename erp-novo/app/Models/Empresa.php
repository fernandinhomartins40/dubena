<?php

namespace App\Models;

use App\Domain\Shared\Auditavel;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Models\Saas\CidadePlataforma;
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

    public const OWNERSHIP_UNRESOLVED = 'OWNERSHIP_UNRESOLVED';

    public const OWNERSHIP_APPROVED = 'OWNERSHIP_APPROVED';

    protected $table = 'empresas';

    protected $fillable = [
        'grupo_id', 'razao_social', 'nome_fantasia', 'nome_informal',
        'cnpj', 'inscricao_estadual', 'inscricao_municipal',
        'cep', 'uf', 'cidade', 'bairro', 'endereco', 'numero', 'complemento',
        // FKs são a fonte da verdade do endereço; `cidade`/`bairro`/`endereco`
        // acima são o texto DERIVADO delas (ver EnderecoEmpresaSync), mantido
        // porque a DANFE e os PDFs de comodato/vale-gás imprimem a string.
        'cidade_id', 'bairro_id', 'rua_id', 'regiao_id',
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

    /**
     * Cadastro geográfico do endereço (fonte da verdade).
     *
     * O sufixo `Cadastro` evita colisão com as colunas de TEXTO `cidade` e
     * `bairro`, que ainda existem para os PDFs fiscais: `$empresa->cidade` tem
     * de continuar devolvendo a string que a DANFE imprime, não um model.
     */
    public function cidadeCadastro(): BelongsTo
    {
        return $this->belongsTo(Cidade::class, 'cidade_id');
    }

    public function bairroCadastro(): BelongsTo
    {
        return $this->belongsTo(Bairro::class, 'bairro_id');
    }

    public function rua(): BelongsTo
    {
        return $this->belongsTo(Rua::class, 'rua_id');
    }

    public function regiao(): BelongsTo
    {
        return $this->belongsTo(Regiao::class, 'regiao_id');
    }

    /** Cidades da plataforma em que a empresa atua (P3 — descoberta/relatório). */
    public function cidadesPlataforma(): BelongsToMany
    {
        return $this->belongsToMany(
            CidadePlataforma::class,
            'empresa_cidade',
            'empresa_id',
            'cidade_plataforma_id',
        );
    }
}
