<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteEndereco;
use App\Models\Pedido\PedidoAvaliacao;
use Illuminate\Support\Facades\DB;

/**
 * Migra o banco do APP "Gás em Casa" (MySQL `sgcm_api`) para o schema novo.
 *
 * Diferença em relação aos demais migrators: o app é um CANAL do ERP, não uma
 * fonte paralela. Todo pedido do app nasceu no ERP (`pedidos.erp_id`, 100%
 * preenchido no dump analisado) e o cliente do app aponta para o cliente do ERP
 * (`clientes.api_id` no lado Oracle). Por isso NÃO se recria pedido, e o cliente
 * só é criado quando comprovadamente não existe do outro lado — recriar o que já
 * está lá duplicaria a base.
 *
 * O que este migrator traz:
 *   - endereços de entrega do app  → cliente_enderecos
 *   - avaliações de pedido          → pedido_avaliacoes
 *   - usuários do app SEM `api_id`  → clientes (+ telefones), marcados na
 *     observação com o id de origem. São ~10,6 mil pessoas que baixaram o app e
 *     nunca viraram cliente do ERP: base de cadastro real, não descartável.
 *
 * Fonte: conexão `app_legado` (MySQL). Sem ela configurada: 0 lidos/gravados.
 */
final class AppGasEmCasaMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    /** Mapa api_id (cliente do app) => id do cliente no ERP novo. */
    private array $mapaClientes = [];

    /** Mapa id do pedido no app => id do pedido no ERP novo. */
    private array $mapaPedidos = [];

    public function nome(): string
    {
        return 'app-gasemcasa';
    }

    public function dependeDe(): array
    {
        return ['clientes', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        if (! $this->appDisponivel()) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['conexão `app_legado` indisponível — nada a migrar']);
        }

        $this->montarCorrelacoes($ctx);

        $avisos = [];
        if ($this->mapaClientes === []) {
            $avisos[] = 'nenhum cliente do ERP tem api_id preenchido: '
                .'endereços/avaliações do app não têm âncora e serão pulados';
        }

        // Usuário do app que nunca virou cliente do ERP (baixou o app e não
        // comprou pelo canal, ou o vínculo se perdeu): é CRIADO como cliente,
        // marcado pela origem. Descartá-lo perderia a base de cadastro do app.
        $clientesCriados = $ctx->dryRun ? 0 : $this->criarClientesDoApp();
        if ($clientesCriados > 0) {
            $avisos[] = "{$clientesCriados} usuário(s) do app sem cliente no ERP "
                .'foram criados como cliente novo';
        }

        // Pedido do app cujo `erp_id` não existe no dump do ERP: os dois dumps
        // foram tirados em momentos diferentes, então o app tem pedidos mais
        // recentes que o corte do Oracle. São migrados para não perder o pedido
        // (nem a avaliação presa a ele).
        $pedidosCriados = $ctx->dryRun ? 0 : $this->criarPedidosRecentesDoApp();
        if ($pedidosCriados > 0) {
            $avisos[] = "{$pedidosCriados} pedido(s) do app posteriores ao corte "
                .'do dump do ERP foram migrados';
        }

        $enderecos = $this->lerEnderecos();
        $avaliacoes = $this->lerAvaliacoes();

        $gravados = 0;
        $pulados = 0;

        if (! $ctx->dryRun) {
            foreach ($enderecos as $e) {
                ClienteEndereco::withoutTenant()->updateOrCreate(
                    ['cliente_id' => $e['cliente_id'], 'endereco' => $e['endereco'], 'numero' => $e['numero']],
                    $e
                );
                $gravados++;
            }
            foreach ($avaliacoes as $a) {
                PedidoAvaliacao::withoutTenant()->updateOrCreate(['pedido_id' => $a['pedido_id']], $a);
                $gravados++;
            }

            // Preço por condição de pagamento (era o gap P0 "catálogo sem preço"
            // da auditoria 2026-08-14) + pagamentos online + cupons do app.
            $gravados += $this->migrarPrecosPorCondicao();
            $gravados += $this->migrarTransacoesOnline();
            $gravados += $this->migrarCupons();
        }

        $pulados = $this->puladosEnderecos + $this->puladosAvaliacoes;
        if ($this->puladosEnderecos) {
            $avisos[] = "{$this->puladosEnderecos} endereço(s) do app sem cliente correspondente no ERP";
        }
        if ($this->puladosAvaliacoes) {
            $avisos[] = "{$this->puladosAvaliacoes} avaliação(ões) sem pedido correspondente no ERP";
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($enderecos) + count($avaliacoes) + $pulados,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->appDisponivel()) {
            return [];
        }

        return [
            new IntegrityInvariant($ctx, 'cliente_enderecos', 'cliente_id', 'clientes'),
            new IntegrityInvariant($ctx, 'pedido_avaliacoes', 'pedido_id', 'pedidos'),
        ];
    }

    private int $puladosEnderecos = 0;

    private int $puladosAvaliacoes = 0;

    /**
     * Cria como cliente do ERP os usuários do app que não têm `api_id`
     * apontando para eles, e completa `$this->mapaClientes` com os novos ids.
     *
     * A empresa é a revenda do app: o `sgcm_api` opera com uma revenda por
     * instalação (`pedidos.user_id`), e ela corresponde à matriz do ERP.
     */
    private function criarClientesDoApp(): int
    {
        $novos = [];
        try {
            $rows = $this->app()->table('clienteimportacoes')->get();
        } catch (\Throwable) {
            return 0;
        }

        $empresa = $this->empresaDoApp();
        if ($empresa === null) {
            return 0;
        }

        $telefones = $this->telefonesPorCliente();
        $proximoId = (int) DB::table('clientes')->max('id');
        $criados = [];

        foreach ($rows as $r) {
            $apiId = (int) $r->id;
            if (isset($this->mapaClientes[$apiId])) {
                continue; // já existe no ERP
            }

            $proximoId++;
            $nome = trim((string) ($r->nome ?? ''));
            if ($nome === '') {
                $nome = trim((string) ($r->primeironome ?? '')) ?: 'Cliente do app';
            }

            $novos[] = [
                'id' => $proximoId,
                'empresa_id' => $empresa['empresa_id'],
                'grupo_id' => $empresa['grupo_id'],
                'nome' => mb_substr($nome, 0, 200),
                'cpf' => $this->soDigitos($r->cpf ?? null),
                'email' => $r->email !== null ? mb_substr((string) $r->email, 0, 150) : null,
                'sexo' => $this->sexoDe($r->sexo ?? null),
                'datanascimento' => $r->datanascimento ?? null,
                'cliente' => true,
                'ativo' => (bool) ($r->ativo ?? true),
                'gasdopovo' => (bool) ($r->gasdopovo ?? false),
                'observacoes' => 'Cadastro originado do app Gás em Casa '
                    ."(id de origem: {$apiId})",
                'created_at' => $r->created_at ?? null,
            ];
            $this->mapaClientes[$apiId] = $proximoId;
            $criados[$proximoId] = $apiId;
        }

        if ($novos === []) {
            return 0;
        }

        $this->gravarPreservandoId('clientes', $novos);

        // Telefones do app: sem eles o cadastro criado fica sem contato — que é
        // justamente como o app identifica o cliente.
        $linhasTelefone = [];
        foreach ($criados as $clienteId => $apiId) {
            foreach ($telefones[$apiId] ?? [] as $fone) {
                $linhasTelefone[] = [
                    'cliente_id' => $clienteId,
                    'empresa_id' => $empresa['empresa_id'],
                    'telefone' => mb_substr($fone, 0, 30),
                    'whatsapp' => false,
                ];
            }
        }
        if ($linhasTelefone !== []) {
            $proximoTel = (int) DB::table('clientetelefones')->max('id');
            foreach ($linhasTelefone as &$t) {
                $t['id'] = ++$proximoTel;
            }
            unset($t);
            $this->gravarPreservandoId('clientetelefones', $linhasTelefone);
        }

        return count($novos);
    }

    /**
     * Migra os pedidos do app que não têm par no dump do ERP (são posteriores
     * ao corte daquele dump) e completa `$this->mapaPedidos` com eles.
     */
    private function criarPedidosRecentesDoApp(): int
    {
        try {
            $rows = $this->app()->table('pedidos')->orderBy('id')->get();
        } catch (\Throwable) {
            return 0;
        }

        $empresa = $this->empresaDoApp();
        if ($empresa === null) {
            return 0;
        }

        $situacoes = $this->mapaSituacoes();
        $situacaoPadrao = $situacoes['PENDENTE'] ?? null;
        if ($situacaoPadrao === null) {
            return 0;
        }

        $itensPorPedido = $this->itensPorPedidoDoApp();
        $proximoId = (int) DB::table('pedidos')->max('id');
        $novos = [];
        $novosItens = [];
        $produtoPadrao = (int) (DB::table('produtos')->min('id') ?? 0);

        foreach ($rows as $r) {
            $idApp = (int) $r->id;
            if (isset($this->mapaPedidos[$idApp])) {
                continue; // já veio pelo ERP
            }
            $clienteId = $this->mapaClientes[(int) $r->cliente_id] ?? null;
            if ($clienteId === null) {
                continue; // sem cliente não há pedido possível
            }

            $proximoId++;
            $efeito = $this->efeitoDaSituacaoApp((int) $r->pedidosituacao_id);
            $itens = $itensPorPedido[$idApp] ?? [];
            $totalItens = array_sum(array_column($itens, 'valor_total'));

            $novos[] = [
                'id' => $proximoId,
                'empresa_id' => $empresa['empresa_id'],
                'grupo_id' => $empresa['grupo_id'],
                'cliente_id' => $clienteId,
                'pedidosituacao_id' => $situacoes[$efeito] ?? $situacaoPadrao,
                'datahora' => $r->created_at ?? null,
                'datahora_acao' => $r->datahoraentrega ?? $r->updated_at ?? null,
                'entrega_taxa' => (float) ($r->valorfrete ?? 0),
                'valor_venda' => round($totalItens, 2),
                'valor_desconto' => (float) ($r->desconto_cupons ?? 0),
                'observacao' => trim('Pedido originado do app Gás em Casa '
                    ."(id de origem: {$idApp}). ".(string) ($r->observacoes ?? '')),
                'estoque_movimentado' => $efeito === 'CONCLUIDO',
                'created_at' => $r->created_at ?? null,
            ];
            $this->mapaPedidos[$idApp] = $proximoId;

            foreach ($itens as $it) {
                $novosItens[] = [
                    'pedido_id' => $proximoId,
                    'empresa_id' => $empresa['empresa_id'],
                    'produto_id' => $it['produto_id'] ?: $produtoPadrao,
                    'quantidade' => $it['quantidade'],
                    'preco_unitario' => $it['preco_unitario'],
                    'desconto' => 0,
                    'valor_total' => $it['valor_total'],
                ];
            }
        }

        if ($novos === []) {
            return 0;
        }

        $this->gravarPreservandoId('pedidos', $novos);

        if ($novosItens !== []) {
            // Só grava item de produto que existe no destino.
            $idsProduto = DB::table('produtos')->pluck('id')->flip();
            $novosItens = array_values(array_filter(
                $novosItens,
                fn ($i) => isset($idsProduto[$i['produto_id']])
            ));
            $proximoItem = (int) DB::table('pedidoitens')->max('id');
            foreach ($novosItens as &$i) {
                $i['id'] = ++$proximoItem;
            }
            unset($i);
            $this->gravarPreservandoId('pedidoitens', $novosItens);
        }

        return count($novos);
    }

    /**
     * Efeito → id da situação no ERP (a primeira de cada efeito).
     *
     * @return array<string,int>
     */
    private function mapaSituacoes(): array
    {
        $out = [];
        foreach (DB::table('pedidosituacoes')->orderBy('id')->get() as $s) {
            $out[(string) $s->efeito] ??= (int) $s->id;
        }

        return $out;
    }

    /** As 4 situações do app (1=Pendente, 2=Em entrega, 3=Entregue, 4=Cancelado). */
    private function efeitoDaSituacaoApp(int $id): string
    {
        return match ($id) {
            3 => 'CONCLUIDO',
            4 => 'CANCELADO',
            default => 'PENDENTE',
        };
    }

    /** @return array<int, list<array<string,mixed>>> */
    private function itensPorPedidoDoApp(): array
    {
        $out = [];
        try {
            $rows = $this->app()->table('pedidoitens')->get();
        } catch (\Throwable) {
            return [];
        }

        // `produtoimportacoes` espelha o produto do ERP: `erp_id` é o id lá.
        $produtoErp = [];
        try {
            foreach ($this->app()->table('produtoimportacoes')->get() as $p) {
                $produtoErp[(int) $p->id] = (int) $p->erp_id;
            }
        } catch (\Throwable) {
            $produtoErp = [];
        }

        foreach ($rows as $r) {
            $out[(int) $r->pedido_id][] = [
                'produto_id' => $produtoErp[(int) $r->produto_id] ?? 0,
                'quantidade' => (float) $r->quantidade,
                'preco_unitario' => (float) $r->precovendaunitario,
                'valor_total' => (float) $r->precovendatotal,
            ];
        }

        return $out;
    }

    /** @return array<int, list<string>> api_id => telefones */
    private function telefonesPorCliente(): array
    {
        $out = [];
        try {
            $rows = $this->app()->table('clientetelefones')
                ->select('cliente_id', 'telefone')->get();
        } catch (\Throwable) {
            return [];
        }

        foreach ($rows as $r) {
            $fone = trim((string) $r->telefone);
            if ($fone !== '') {
                $out[(int) $r->cliente_id][] = $fone;
            }
        }

        return $out;
    }

    /**
     * Empresa (tenant) que representa a revenda do app.
     *
     * @return array{empresa_id:int, grupo_id:int}|null
     */
    private function empresaDoApp(): ?array
    {
        // Âncora preferida: a empresa dos clientes que JÁ vieram do app pelo
        // ERP — é literalmente a revenda que opera o canal.
        if ($this->mapaClientes !== []) {
            $id = DB::table('clientes')
                ->whereIn('id', array_slice(array_values($this->mapaClientes), 0, 500))
                ->selectRaw('empresa_id, grupo_id, COUNT(*) as n')
                ->groupBy('empresa_id', 'grupo_id')
                ->orderByDesc('n')
                ->first();
            if ($id !== null) {
                return ['empresa_id' => (int) $id->empresa_id, 'grupo_id' => (int) $id->grupo_id];
            }
        }

        $matriz = DB::table('empresas')->orderByDesc('matriz')->orderBy('id')->first();

        return $matriz === null
            ? null
            : ['empresa_id' => (int) $matriz->id, 'grupo_id' => (int) $matriz->grupo_id];
    }

    /** O legado grava "Masculino"/"Feminino"; a coluna nova é char(1). */
    private function sexoDe(mixed $v): ?string
    {
        $v = mb_strtoupper(trim((string) ($v ?? '')));

        return match (true) {
            str_starts_with($v, 'M') => 'M',
            str_starts_with($v, 'F') => 'F',
            default => null,
        };
    }

    private function soDigitos(mixed $v): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($v ?? ''));

        return $d === '' ? null : substr($d, 0, 20);
    }

    /**
     * Correlaciona as chaves do app com as linhas já migradas do ERP.
     * `clientes.api_id` e `pedidos.apipedido_id` são as pontes gravadas pelo
     * próprio ERP legado quando o pedido entrava pelo app.
     */
    private function montarCorrelacoes(MigrationContext $ctx): void
    {
        $legado = $ctx->legado();

        try {
            $this->mapaClientes = $legado->table('clientes')
                ->whereNotNull('api_id')
                ->pluck('id', 'api_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable) {
            $this->mapaClientes = [];
        }

        try {
            $this->mapaPedidos = $legado->table('pedidos')
                ->whereNotNull('apipedido_id')
                ->pluck('id', 'apipedido_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable) {
            $this->mapaPedidos = [];
        }
    }

    /** @return list<array<string,mixed>> */
    private function lerEnderecos(): array
    {
        $this->puladosEnderecos = 0;

        try {
            $rows = $this->app()->table('clienteenderecos')->where('ativo', 1)->get();
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $clienteId = $this->mapaClientes[(int) $r->cliente_id] ?? null;
            if ($clienteId === null) {
                $this->puladosEnderecos++;

                continue;
            }

            $out[] = [
                'cliente_id' => $clienteId,
                'empresa_id' => $this->empresaDoCliente($clienteId),
                'titulo' => $this->limita((string) ($r->titulo ?: 'Casa'), 100),
                'endereco' => $this->limita((string) $r->rua, 255),
                'numero' => $this->limita((string) $r->numero, 20),
                'complemento' => $this->limita((string) ($r->complemento ?? ''), 120) ?: null,
                'ponto_referencia' => $this->limita((string) ($r->pontoreferencia ?? ''), 160) ?: null,
                'bairro' => $this->limita((string) ($r->bairro ?? ''), 120) ?: null,
                'cidade' => $this->limita((string) ($r->cidade ?? ''), 120) ?: null,
                'cep' => $this->limita(preg_replace('/\D/', '', (string) ($r->cep ?? '')), 12) ?: null,
                'uf' => $this->limita((string) ($r->uf ?? ''), 2) ?: null,
                'latitude' => $r->latitude !== null ? (float) $r->latitude : null,
                'longitude' => $r->longitude !== null ? (float) $r->longitude : null,
                'favorito' => false,
            ];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function lerAvaliacoes(): array
    {
        $this->puladosAvaliacoes = 0;

        try {
            $rows = $this->app()->table('pedidoavaliacoes')->get();
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        $vistos = [];
        foreach ($rows as $r) {
            $pedidoId = $this->mapaPedidos[(int) $r->pedido_id] ?? null;
            if ($pedidoId === null || isset($vistos[$pedidoId])
                || $this->empresaDoPedido($pedidoId) === null) {
                // empresa nula estouraria o NOT NULL do destino e derrubaria a
                // carga inteira — pedido sem par vira descarte contado.
                $this->puladosAvaliacoes++;

                continue;
            }
            $vistos[$pedidoId] = true;

            $rating = $r->rating === null ? null : (int) round((float) $r->rating);
            if ($rating !== null) {
                $rating = max(1, min(5, $rating));
            }

            $out[] = [
                'pedido_id' => $pedidoId,
                'empresa_id' => $this->empresaDoPedido($pedidoId),
                'rating' => $rating,
                'mensagem' => $this->limita((string) ($r->mensagem ?? ''), 140) ?: null,
                'ignorado' => $rating === null,
            ];
        }

        return $out;
    }

    /**
     * Preço por condição de pagamento do app → produto_condicao_precos.
     *
     * O app precifica por condição (dinheiro/cartão/pix): sem esta tabela o
     * catálogo do tenant migrado fica sem preço. As pontes são as tabelas de
     * importação do sgcm (`*importacoes.erp_id` = id no ERP).
     */
    private function migrarPrecosPorCondicao(): int
    {
        try {
            $rows = $this->app()->table('produtocondicaopagamentos')->get();
        } catch (\Throwable) {
            return 0;
        }

        $empresa = $this->empresaDoApp();
        if ($empresa === null || $rows->isEmpty()) {
            return 0;
        }

        $produtoErp = [];
        try {
            foreach ($this->app()->table('produtoimportacoes')->get(['id', 'erp_id']) as $p) {
                $produtoErp[(int) $p->id] = (int) $p->erp_id;
            }
        } catch (\Throwable) {
        }
        $condicaoErp = [];
        try {
            foreach ($this->app()->table('condicaopagamentoimportacoes')->get(['id', 'erp_id']) as $c) {
                $condicaoErp[(int) $c->id] = (int) $c->erp_id;
            }
        } catch (\Throwable) {
        }

        $idsProduto = DB::table('produtos')->pluck('id')->flip();
        $idsCondicao = DB::table('condicaopagamentos')->pluck('id')->flip();

        $n = 0;
        foreach ($rows as $r) {
            $produto = $produtoErp[(int) $r->produtoimportacao_id] ?? 0;
            $condicao = $condicaoErp[(int) $r->condicaopagamentoimportacao_id] ?? 0;
            if (! isset($idsProduto[$produto]) || ! isset($idsCondicao[$condicao])) {
                continue;
            }

            DB::table('produto_condicao_precos')->updateOrInsert(
                [
                    'empresa_id' => $empresa['empresa_id'],
                    'produto_id' => $produto,
                    'condicaopagamento_id' => $condicao,
                    'gasdopovo' => false,
                ],
                [
                    'valor' => round((float) $r->valor, 2),
                    'created_at' => $r->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );
            $n++;
        }

        return $n;
    }

    /** Transações de pagamento online do app (cartão via gateway) → pagamentos_online. */
    private function migrarTransacoesOnline(): int
    {
        try {
            $rows = $this->app()->table('transacoesonline')->get();
        } catch (\Throwable) {
            return 0;
        }

        $n = 0;
        foreach ($rows as $r) {
            $pedidoId = (int) ($r->erppedido_id ?? 0);
            $pedido = DB::table('pedidos')
                ->where('id', $pedidoId)->first(['id', 'empresa_id', 'cliente_id', 'valor_venda']);
            if ($pedido === null) {
                continue;
            }

            DB::table('pagamentos_online')->updateOrInsert(
                ['pedido_id' => $pedido->id, 'tid' => (string) $r->tid],
                [
                    'empresa_id' => $pedido->empresa_id,
                    'cliente_id' => $pedido->cliente_id,
                    'gateway' => 'rede',
                    'valor' => (float) $pedido->valor_venda,
                    'situacao' => ((int) ($r->cancelado ?? 0)) === 1 ? 'CANCELADO' : 'CAPTURADO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $n++;
        }

        return $n;
    }

    /** Cupons de desconto do app → promoções (o destino tem o campo `codigo`). */
    private function migrarCupons(): int
    {
        try {
            $rows = $this->app()->table('cupons')->get();
        } catch (\Throwable) {
            return 0;
        }

        $grupo = (int) (DB::table('grupos')->min('id') ?? 0);
        if ($grupo === 0) {
            return 0;
        }

        $n = 0;
        foreach ($rows as $r) {
            $codigo = trim((string) ($r->codigo ?? ''));
            if ($codigo === '') {
                continue;
            }
            $percentual = mb_strtolower((string) ($r->tipo ?? '')) === 'percentual';

            DB::table('promocoes')->updateOrInsert(
                ['grupo_id' => $grupo, 'codigo' => $codigo],
                [
                    'descricao' => 'Cupom '.$codigo.' (app)',
                    'inicio' => $r->datainicio ?? null,
                    'fim' => $r->datafim ?? null,
                    'desconto_percentual' => $percentual ? (float) $r->valor : null,
                    'ativo' => (bool) ($r->ativo ?? true),
                    'created_at' => $r->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );
            $n++;
        }

        return $n;
    }

    private function empresaDoCliente(int $clienteId): ?int
    {
        static $cache = [];

        return $cache[$clienteId] ??= Cliente::withoutTenant()
            ->whereKey($clienteId)->value('empresa_id');
    }

    private function empresaDoPedido(int $pedidoId): ?int
    {
        static $cache = [];

        return $cache[$pedidoId] ??= DB::table('pedidos')
            ->where('id', $pedidoId)->value('empresa_id');
    }

    /** Corta no limite da coluna preservando UTF-8 (o legado tem acento). */
    private function limita(string $v, int $max): string
    {
        $v = trim($v);

        return mb_substr($v, 0, $max);
    }

    private function app()
    {
        return DB::connection('app_legado');
    }

    private function appDisponivel(): bool
    {
        try {
            $this->app()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
