<?php

namespace App\Models\Apoio;

/**
 * Motivo de NÃO-venda (T4.8) — por que a venda não aconteceu: cliente
 * pesquisando preço, demora na entrega, ligou por engano. Usado no atendimento
 * e pelo entregador na venda em campo. No legado: `MotivonaovendaController`.
 */
class MotivoNaoVenda extends CadastroApoio
{
    protected $table = 'motivos_nao_venda';

    protected $fillable = ['grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
