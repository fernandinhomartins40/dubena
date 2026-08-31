<?php

namespace App\Models\Cliente;

use App\Domain\Cliente\PapelPessoa;
use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TipoPessoa;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Models\User;
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
        return $this->belongsTo(User::class, 'desativado_por');
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

    /**
     * Endereço legível: logradouro, número, complemento, bairro e cidade.
     *
     * A coluna `endereco` está NULL em 100% da base (0 de 55.453 medidos em
     * produção): o logradouro real sempre veio da FK `rua_id`. Quem lia a
     * coluna direto exibia só o número — "Endereço: 587" — e foi assim que a
     * tela de revisão, a roteirização, as missões e os relatórios ficaram sem
     * o logradouro.
     *
     * Este accessor é o ponto único: usa o texto quando existir (cadastro
     * antigo ou importação que preencheu) e cai na FK, que é o caso real.
     */
    public function getEnderecoCompletoAttribute(): ?string
    {
        $logradouro = $this->endereco ?: $this->rua?->descricao;

        $linha = trim(($logradouro ?? '').' '.($this->numero ?? ''));

        foreach ([$this->complemento, $this->bairro?->descricao, $this->cidade?->descricao] as $parte) {
            if (filled($parte)) {
                $linha = $linha === '' ? (string) $parte : $linha.', '.$parte;
            }
        }

        return $linha !== '' ? $linha : null;
    }

    /**
     * Só logradouro + número — para onde a linha completa não cabe (cupom,
     * etiqueta, lista compacta).
     */
    public function getEnderecoLinhaAttribute(): ?string
    {
        $logradouro = $this->endereco ?: $this->rua?->descricao;

        return trim(($logradouro ?? '').' '.($this->numero ?? '')) ?: null;
    }

    public function tipopessoa(): BelongsTo
    {
        return $this->belongsTo(TipoPessoa::class, 'tipopessoa_id');
    }

    public function segmento(): BelongsTo
    {
        return $this->belongsTo(Segmento::class, 'segmento_id');
    }

    /** Papeis da pessoa, com vigencia (F3-01). */
    public function papeis(): HasMany
    {
        return $this->hasMany(ClientePapel::class);
    }

    /**
     * A pessoa exerce este papel HOJE?
     *
     * Le da tabela de papeis, com o booleano legado como fallback: enquanto as
     * duas fontes convivem, um cadastro que so tem o booleano (criado por um
     * caminho ainda nao migrado) nao pode desaparecer da lista.
     */
    public function temPapel(PapelPessoa $papel): bool
    {
        if ($this->relationLoaded('papeis') || $this->exists) {
            $daTabela = $this->papeis()
                ->where('papel', $papel->value)
                ->vigentes()
                ->exists();

            if ($daTabela) {
                return true;
            }
        }

        return (bool) $this->{$papel->colunaLegada()};
    }
}
