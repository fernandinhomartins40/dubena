<?php

namespace App\Models\Financeiro;

use App\Domain\Financeiro\ContaExtratoAcao;
use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regra de classificação automática de linha do extrato (T4.2).
 *
 * Casa um padrão de descrição do OFX com a ação e os ids que o lançamento
 * precisa — é o que transforma a importação de extrato de "lista para conferir
 * à mão" em "lançamentos pré-classificados".
 */
class ContaExtratoRegra extends Model
{
    use BelongsToTenant;

    protected $table = 'conta_extrato_regras';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'conta_id', 'descricao', 'acao',
        'condicaopagamento_id', 'contamovimentotipo_id', 'plano_conta_id',
        'centro_custo_id', 'cliente_id', 'conta_origem_id',
        'ativo', 'prioridade',
    ];

    protected function casts(): array
    {
        return [
            'acao' => ContaExtratoAcao::class,
            'ativo' => 'boolean',
            'prioridade' => 'integer',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Caixa\Conta::class, 'conta_id');
    }
}
