<?php

namespace App\Models\Apoio;

/**
 * Motivo de atraso do pedido (T4.8) — justificativa obrigatória quando o
 * atendimento marca um pedido como atrasado. No legado: `PedidomotivoatrasoController`.
 */
class PedidoMotivoAtraso extends CadastroApoio
{
    protected $table = 'pedido_motivos_atraso';

    protected $fillable = ['grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
