<?php

namespace App\Models\Venda;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teto de desconto por perfil/produto/segmento — F2.
 *
 * A regra mais específica vence (ver AlcadaDescontoService::escolher). `null` em
 * produto/setor/condição significa "vale para todos", não "nenhum".
 */
class AlcadaDesconto extends Model
{
    use BelongsToTenant;

    protected $table = 'alcada_descontos';

    protected $fillable = [
        'empresa_id', 'role_id', 'colaborador_id', 'produto_id', 'setor_id',
        'condicaopagamento_id', 'percentual_max', 'valor_max', 'base_calculo',
        'permite_solicitar', 'data_inicio', 'data_fim', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'percentual_max' => 'decimal:4',
            'valor_max' => 'decimal:2',
            'permite_solicitar' => 'boolean',
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Produto\Produto::class);
    }

    /**
     * Quão específica é esta regra — usado para desempatar quando várias batem.
     * Colaborador nominal pesa mais que papel; produto pesa mais que setor.
     */
    public function especificidade(): int
    {
        return ($this->colaborador_id !== null ? 8 : 0)
            + ($this->produto_id !== null ? 4 : 0)
            + ($this->condicaopagamento_id !== null ? 2 : 0)
            + ($this->setor_id !== null ? 1 : 0)
            + ($this->role_id !== null ? 1 : 0);
    }
}
