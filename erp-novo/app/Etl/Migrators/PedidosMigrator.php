<?php

namespace App\Etl\Migrators;

use App\Domain\Pedido\EfeitoPedido;
use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N4 — migra operações/situações de pedido, pedidos e itens.
 *
 * Conversão-chave: as FLAGS legadas da situação (fechadoconcluido/entregafinalizada,
 * fechadocancelado/entregacancelada) → o EFEITO explícito da máquina de estados
 * (CONCLUIDO / CANCELADO / PENDENTE). Itens vêm de pedidoprodutos (legado).
 */
final class PedidosMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'pedidos';
    }

    public function dependeDe(): array
    {
        // `users` na frente: atendente/entregador referenciam user_id do legado
        // e são anulados quando o user não existe no destino.
        return ['clientes', 'produtos', 'estoque', 'users'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->legadoDisponivel($ctx)) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['banco legado indisponível — nada a migrar']);
        }

        $operacoes = $this->lerOperacoes($ctx);
        $situacoes = $this->lerSituacoes($ctx);

        $gravados = 0;
        $lidos = count($operacoes) + count($situacoes);
        $pulados = 0;

        if (! $ctx->dryRun) {
            $gravados += $this->gravarPreservandoId('pedidooperacoes', $operacoes);
            $gravados += $this->gravarPreservandoId('pedidosituacoes', $situacoes);
        }

        // Pedidos e itens são centenas de milhares de linhas: carregar tudo em
        // memória estoura o limite do PHP. A carga vai em blocos, direto do
        // cursor do legado para o banco novo.
        $idsSituacao = $this->idsExistentes('pedidosituacoes');
        $idsCliente = $this->idsExistentes('clientes');

        // As tabelas podem não existir no dump em uso (o legado varia por
        // instalação); ausência é "nada a migrar", não falha da carga.
        if ($this->tabelaExiste($ctx, 'pedidos')) {
            $ctx->legado()->table('pedidos')->orderBy('id')->chunk(2000,
                function ($rows) use (&$gravados, &$lidos, &$pulados, $ctx, $idsSituacao, $idsCliente) {
                    $lote = [];
                    foreach ($rows as $r) {
                        $lidos++;
                        // cliente e situação são obrigatórios: sem eles o pedido
                        // não tem como existir no schema novo.
                        if (! isset($idsCliente[(int) $r->cliente_id])
                            || ! isset($idsSituacao[(int) $r->pedidosituacao_id])) {
                            $pulados++;

                            continue;
                        }
                        $lote[] = $this->mapearPedido($r);
                    }
                    if ($lote !== [] && ! $ctx->dryRun) {
                        // Setor, operação e usuários são opcionais no schema
                        // novo: referência que não veio no dump vira null.
                        $lote = $this->anularFksInvalidas($lote, [
                            'pedidooperacao_id' => 'pedidooperacoes',
                            'setor_id' => 'setores',
                            'atendente_user_id' => 'users',
                            'entregador_user_id' => 'users',
                        ]);
                        $gravados += $this->gravarPreservandoId('pedidos', $lote);
                    }
                });
        }

        // Item herda a empresa do pedido: `empresa_id` é NOT NULL nas filhas
        // (isolamento multi-tenant) e o legado não o traz no item.
        $empresaDoPedido = [];
        foreach (DB::table('pedidos')->select('id', 'empresa_id')->cursor() as $p) {
            $empresaDoPedido[(int) $p->id] = (int) $p->empresa_id;
        }
        $idsProduto = $this->idsExistentes('produtos');

        if ($this->tabelaExiste($ctx, 'pedidoprodutos')) {
            $ctx->legado()->table('pedidoprodutos')->orderBy('id')->chunk(2000,
                function ($rows) use (&$gravados, &$lidos, &$pulados, $ctx, $empresaDoPedido, $idsProduto) {
                    $lote = [];
                    foreach ($rows as $r) {
                        $lidos++;
                        $empresa = $empresaDoPedido[(int) $r->pedido_id] ?? null;
                        if ($empresa === null || ! isset($idsProduto[(int) $r->produto_id])) {
                            $pulados++;

                            continue;
                        }
                        $item = $this->mapearItem($r);
                        $item['empresa_id'] = $empresa;
                        $lote[] = $item;
                    }
                    if ($lote !== [] && ! $ctx->dryRun) {
                        $gravados += $this->gravarPreservandoId('pedidoitens', $lote);
                    }
                });
        }

        $avisos = [];
        if ($pulados > 0) {
            $avisos[] = "{$pulados} pedido(s)/item(ns) descartado(s) por "
                .'referência obrigatória ausente (cliente/situação/produto)';
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $lidos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
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

    /** A tabela existe na origem? (o dump varia por instalação do legado) */
    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Conjunto de ids já presentes numa tabela do banco NOVO, para validar as
     * FKs sem uma consulta por linha.
     *
     * @return array<int, true>
     */
    private function idsExistentes(string $tabela): array
    {
        $ids = [];
        foreach (DB::table($tabela)->pluck('id') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    private function mapearPedido(object $r): array
    {
        return [
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
        ];
    }

    /** @return array<string, mixed> */
    private function mapearItem(object $r): array
    {
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
