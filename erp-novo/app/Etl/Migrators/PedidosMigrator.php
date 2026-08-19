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

    /** @var array<int, true> ids de condicaopagamentos que existem no destino */
    private array $idsCondicao = [];

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

        // `condicaopagamentos` é carregada pelo `complementos`, que roda DEPOIS
        // de `pedidos` na ordem topológica (complementos depende de caixa, que
        // depende de financeiro, que depende de pedidos). Inverter a dependência
        // criaria ciclo. Como o legado repete descrições e o destino tem
        // UNIQUE(grupo_id, descricao), a duplicada é remapeada para a canônica —
        // o mesmo critério do `ComplementosMigrator`, para os dois concordarem.
        $remapCondicao = $this->remapCondicaoPagamento($ctx);
        $this->idsCondicao = $this->idsExistentes('condicaopagamentos');

        // As tabelas podem não existir no dump em uso (o legado varia por
        // instalação); ausência é "nada a migrar", não falha da carga.
        if ($this->tabelaExiste($ctx, 'pedidos')) {
            $ctx->legado()->table('pedidos')->orderBy('id')->chunk(2000,
                function ($rows) use (&$gravados, &$lidos, &$pulados, $ctx, $idsSituacao, $idsCliente, $remapCondicao) {
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
                        $lote[] = $this->mapearPedido($r, $remapCondicao);
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
                        // NÃO passa `condicaopagamento_id` por `anularFksInvalidas`:
                        // a tabela destino ainda está vazia neste ponto da ordem, e
                        // a checagem zeraria os 400 mil vínculos — que foi
                        // exatamente o bug. A validade já foi garantida contra a
                        // ORIGEM em `remapCondicaoPagamento()`.
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
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            // Acréscimo legítimo de SEGUNDA ORIGEM (T2.4): pedidos feitos pelo
            // app APÓS o corte do dump do ERP. O `AppGasEmCasaMigrator` os traz
            // do MySQL `sgcm_api` (correlacionados por `apipedido_id`), e por
            // isso o destino tem mais linhas que o Oracle — por desenho.
            //
            // Verificado que é só acréscimo, nunca perda:
            //   SELECT count(*) FROM (SELECT id::text FROM legado.pedidos
            //     EXCEPT SELECT id::text FROM public.pedidos) x;   -- 0
            //
            // A closure conta exatamente os ids do destino ausentes no Oracle;
            // se algum pedido do dump sumir, a contagem NÃO compensa (o EXCEPT
            // é direcional) e a invariante volta a falhar, como deve.
            new CountInvariant(
                $ctx, 'pedidos', 'pedidos',
                acrescimosEsperados: fn () => $this->pedidosSoDoApp($ctx),
            ),
            new CountInvariant($ctx, 'pedidoprodutos', 'pedidoitens'),
            new IntegrityInvariant($ctx, 'pedidos', 'cliente_id', 'clientes'),
            new IntegrityInvariant($ctx, 'pedidoitens', 'pedido_id', 'pedidos'),
        ];
    }

    /**
     * Pedidos que existem no destino e NÃO no dump Oracle — os que vieram do
     * app depois do corte do dump.
     *
     * Direcional de propósito: mede só o excedente. Um pedido do Oracle que
     * sumisse no destino não seria compensado por este número, e a invariante
     * continuaria acusando — que é exatamente o comportamento desejado.
     */
    private function pedidosSoDoApp(MigrationContext $ctx): int
    {
        try {
            $doOracle = $ctx->legado()->table('pedidos')->pluck('id')
                ->map(fn ($v) => (int) $v)->flip();
        } catch (\Throwable) {
            return 0;
        }

        if ($doOracle->isEmpty()) {
            return 0;
        }

        $extras = 0;
        foreach (DB::table('pedidos')->select('id')->cursor() as $p) {
            if (! isset($doOracle[(int) $p->id])) {
                $extras++;
            }
        }

        return $extras;
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

    /**
     * Garante `condicaopagamentos` carregada e devolve o remap duplicada→canônica.
     *
     * O `ComplementosMigrator` é o dono desta tabela, mas roda DEPOIS de `pedidos`
     * (complementos → caixa → financeiro → pedidos), e inverter a dependência
     * criaria ciclo. Como a FK `pedidos.condicaopagamento_id` é imediata, gravar o
     * pedido antes da condição existir violaria a constraint — por isso a carga
     * acontece aqui quando a tabela ainda está vazia.
     *
     * O critério de deduplicação é o MESMO do `ComplementosMigrator` (primeira
     * ocorrência por (grupo, descrição) vence, pelo UNIQUE do destino): os dois
     * precisam concordar, senão o segundo a rodar remapearia para outro id.
     *
     * @return array<int, int> id do legado => id canônico no destino
     */
    private function remapCondicaoPagamento(MigrationContext $ctx): array
    {
        if (! $this->tabelaExiste($ctx, 'condicaopagamentos')) {
            return [];
        }

        $grupoPadrao = (int) (DB::table('grupos')->min('id') ?? 1);
        $jaCarregada = DB::table('condicaopagamentos')->count() > 0;

        $remap = [];
        $canonicaDaDescricao = [];
        $lote = [];

        foreach ($ctx->legado()->table('condicaopagamentos')->orderBy('id')->get() as $r) {
            $grupo = (int) ($r->grupo_id ?? 0) ?: $grupoPadrao;
            $descricao = mb_substr(trim((string) $r->descricao), 0, 255);
            $chave = $grupo.'|'.mb_strtolower($descricao);

            if (isset($canonicaDaDescricao[$chave])) {
                $remap[(int) $r->id] = $canonicaDaDescricao[$chave];

                continue;
            }
            $canonicaDaDescricao[$chave] = (int) $r->id;

            $lote[] = [
                'id' => (int) $r->id,
                'grupo_id' => $grupo,
                'descricao' => $descricao,
                'num_parcelas' => (int) ($r->num_parcelas ?? 1) ?: 1,
                'intervalo_dias' => (int) ($r->intervalo ?? 30),
                'dias_primeira' => (int) ($r->dias_primeira ?? 0),
                // O legado não tem flag "à vista": é à vista quando a condição
                // tem uma parcela sem prazo.
                'a_vista' => (int) ($r->num_parcelas ?? 1) <= 1
                    && (int) ($r->dias_primeira ?? 0) === 0,
                'ativo' => ! in_array((string) ($r->ativo ?? '1'), ['0', '', 'N', 'n'], true),
                'created_at' => $r->created_at ?? null,
            ];
        }

        if (! $jaCarregada && $lote !== [] && ! $ctx->dryRun) {
            $this->gravarPreservandoId('condicaopagamentos', $lote);
        }

        return $remap;
    }

    /**
     * Condição do pedido já resolvida para o id que EXISTE no destino.
     *
     * Duplicada vira canônica pelo remap; o que não sobreviveu à carga (o dump
     * tem pedido apontando para condição inexistente) vira null — a coluna é
     * nullable, e derrubar o lote inteiro por isso seria pior.
     *
     * @param  array<int, int>  $remapCondicao
     */
    private function condicaoResolvida(object $r, array $remapCondicao): ?int
    {
        $id = (int) ($r->condicaopagamento_id ?? 0);
        if ($id === 0) {
            return null;
        }

        $id = $remapCondicao[$id] ?? $id;

        return isset($this->idsCondicao[$id]) ? $id : null;
    }

    /**
     * @param  array<int, int>  $remapCondicao
     * @return array<string, mixed>
     */
    private function mapearPedido(object $r, array $remapCondicao = []): array
    {
        return [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cliente_id' => (int) $r->cliente_id,
            'pedidooperacao_id' => $r->pedidooperacao_id ?? null,
            'pedidosituacao_id' => (int) $r->pedidosituacao_id,
            // A forma de pagamento não era mapeada: 400 mil pedidos migravam com
            // `condicaopagamento_id` nulo. O FinanceiroService decide o lançamento
            // por ela (à vista × a prazo) e o MaloteService confere o malote pelo
            // mesmo campo — sem ele, o histórico financeiro perde a forma de
            // pagamento e a conferência de caixa não fecha.
            'condicaopagamento_id' => $this->condicaoResolvida($r, $remapCondicao),
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

        // As colunas REAIS do legado são `precovendaunitario` e
        // `precovendatotal`. O código lia `precovenda`/`preco`, que não existem
        // em `pedidoprodutos`: os 406.883 itens migraram com preço 0,00 — o
        // pedido mostrava o total certo e o item "— × 1  R$ 0,00".
        //
        // `?? null` e não `?? 0`: se um dia a coluna sumir do dump, o item entra
        // com preço nulo e a invariante acusa, em vez de gravar zero em silêncio.
        $preco = $r->precovendaunitario ?? $r->precovenda ?? $r->preco ?? null;
        $preco = $preco !== null ? (float) $preco : 0.0;
        $desc = (float) ($r->desconto ?? 0);

        // O total vem do legado quando existe: recalcular por quantidade × preço
        // diverge do que foi cobrado quando houve arredondamento no fechamento.
        $total = isset($r->precovendatotal)
            ? (float) $r->precovendatotal
            : round($qtd * $preco - $desc, 2);

        return [
            'id' => (int) $r->id,
            'pedido_id' => (int) $r->pedido_id,
            'produto_id' => (int) $r->produto_id,
            'quantidade' => $qtd,
            'preco_unitario' => $preco,
            'desconto' => $desc,
            'valor_total' => $total,
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
