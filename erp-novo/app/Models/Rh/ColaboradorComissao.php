<?php

namespace App\Models\Rh;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regra de comissão do colaborador — C5 (portada do legado colaboradorcomissaos).
 * tipo_comissao: 1=percentual sobre o valor; 2=repasse (empresa fica com empresa_valor
 * por unidade, o resto é do colaborador). Variantes _app para pedidos do aplicativo.
 */
class ColaboradorComissao extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['colaborador_id' => 'colaboradores'];

    protected $table = 'colaborador_comissoes';

    protected $fillable = [
        'empresa_id', 'colaborador_id', 'produto_id', 'setor_id', 'condicaopagamento_id',
        'tipo_comissao', 'percentual', 'empresa_valor', 'percentual_app', 'empresa_valor_app',
        'data_inicio', 'data_fim', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_comissao' => 'integer',
            'percentual' => 'decimal:4',
            'empresa_valor' => 'decimal:2',
            'percentual_app' => 'decimal:4',
            'empresa_valor_app' => 'decimal:2',
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function excecoes(): HasMany
    {
        return $this->hasMany(ComissaoExcecao::class, 'colaborador_comissao_id');
    }
}
