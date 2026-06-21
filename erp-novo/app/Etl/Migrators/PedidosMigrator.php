<?php

namespace App\Etl\Migrators;

use App\Domain\Pedido\EfeitoPedido;
use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoItem;
use App\Models\Pedido\PedidoOperacao;
use App\Models\Pedido\PedidoSituacao;

/**
 * N4 — migra operações/situações de pedido, pedidos e itens.
 *
 * Conversão-chave: as FLAGS legadas da situação (fechadoconcluido/entregafinalizada,
 * fechadocancelado/entregacancelada) → o EFEITO explícito da máquina de estados
 * (CONCLUIDO / CANCELADO / PENDENTE). Itens vêm de pedidoprodutos (legado).
 */
final class PedidosMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'pedidos';
    }

    public function dependeDe(): array
    {
        return ['clientes', 'produtos', 'estoque'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $operacoes = $this->lerOperacoes($ctx);
        $situacoes = $this->lerSituacoes($ctx);
        $pedidos = $this->lerPedidos($ctx);
        $itens = $this->lerItens($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($operacoes as $o) {
                PedidoOperacao::withoutGrupo()->updateOrCreate(['id' => $o['id']], $o);
                $gravados++;
            }
            foreach ($situacoes as $s) {
                PedidoSituacao::withoutGrupo()->updateOrCreate(['id' => $s['id']], $s);
                $gravados++;
            }
            foreach ($pedidos as $p) {
                Pedido::withoutTenant()->updateOrCreate(['id' => $p['id']], $p);
                $gravados++;
            }
            foreach ($itens as $i) {
                PedidoItem::updateOrCreate(['id' => $i['id']], $i);
                $gravados++;
            }
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($operacoes) + count($situacoes) + count($pedidos) + count($itens),
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: 0,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'pedidos', 'pedidos'),
            new CountInvariant($ctx, 'pedidoprodutos', 'pedidoitens'),
            new IntegrityInvariant($ctx, 'pedidos', 'cliente_id', 'clientes'),
            new IntegrityInvariant($ctx, 'pedidoitens', 'pedido_id', 'pedidos'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lerOperacoes(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('pedidooperacaos')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) $r->descricao),
            'convenio' => (bool) ($r->convenio ?? false),
            'gasbolso' => (bool) ($r->gasbolso ?? false),
            'disk' => (bool) ($r->disk ?? false),
            'venda_direta' => (bool) ($r->vendadireta ?? false),
            'pdv' => (bool) ($r->pdv ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerSituacoes(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('pedidosituacaos')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) $r->descricao),
            'efeito' => $this->efeitoDe($r)->value,
            'padrao_tela_pedido' => (bool) ($r->padraotelapedido ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
        ])->all();
    }

    /** Deriva o efeito explícito das flags legadas da situação. */
    private function efeitoDe(object $r): EfeitoPedido
    {
        if (! empty($r->fechadocancelado) || ! empty($r->entregacancelada)) {
            return EfeitoPedido::CANCELADO;
        }
        if (! empty($r->fechadoconcluido) || ! empty($r->entregafinalizada)) {
            return EfeitoPedido::CONCLUIDO;
        }

        return EfeitoPedido::PENDENTE;
    }

    /** @return list<array<string, mixed>> */
    private function lerPedidos(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('pedidos')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cliente_id' => (int) $r->cliente_id,
            'pedidooperacao_id' => $r->pedidooperacao_id ?? null,
            'pedidosituacao_id' => (int) $r->pedidosituacao_id,
            'setor_id' => $r->entregasetor_id ?? null,
            'atendente_user_id' => $r->atendenteuser_id ?? null,
            'entregador_user_id' => $r->entregadoruser_id ?? null,
            'datahora' => $r->datahora ?? null,
            'datahora_acao' => $r->datahoraacao ?? null,
            'entrega_urgente' => (bool) ($r->entregaurgente ?? false),
            'entrega_taxa' => (float) ($r->entregataxa ?? 0),
            'entrega_troco_para' => isset($r->entregatrocopara) ? (float) $r->entregatrocopara : null,
            'valor_venda' => (float) ($r->valorvenda ?? 0),
            'valor_desconto' => (float) ($r->valordesconto ?? 0),
            'observacao' => $r->observacao ?? null,
            // Pedido concluído já tem estoque movimentado no legado.
            'estoque_movimentado' => (bool) ($r->fechadoconcluido ?? false),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerItens(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('pedidoprodutos')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(function ($r) {
            $qtd = (float) ($r->quantidade ?? 0);
            $preco = (float) ($r->precovenda ?? $r->preco ?? 0);
            $desc = (float) ($r->desconto ?? 0);

            return [
                'id' => (int) $r->id,
                'pedido_id' => (int) $r->pedido_id,
                'produto_id' => (int) $r->produto_id,
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'desconto' => $desc,
                'valor_total' => round($qtd * $preco - $desc, 2),
            ];
        })->all();
    }

    private function legadoDisponivel(MigrationContext $ctx): bool
    {
        try {
            $ctx->legado()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
