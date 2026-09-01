<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N8 — satélites: vale-gás (23,6 mil), comodato (975) e convênio.
 *
 * Diferenças de modelagem que a migração precisa resolver:
 *
 *  - **Convênio**: o legado não tem cadastro de convênio — tem
 *    `conveniofechamentos`, um fechamento POR CLIENTE. O destino exige
 *    `convenio_id`, então cria-se um convênio por cliente conveniado (o
 *    cliente já traz a flag `convenio` migrada) e os fechamentos apontam para
 *    ele. Sem isso o módulo ficaria vazio apesar de haver 3.717 fechamentos.
 *  - **Vale-gás**: `valegassituacao_id` do legado → `situacao` textual; o valor
 *    não existe na tabela (vem do produto), então entra 0 e é reportado.
 *  - **Comodato**: o legado registra o contrato (cliente + datas), sem produto
 *    nem quantidade — que são NOT NULL no destino. Usa-se o produto padrão e
 *    quantidade 1, com aviso: é contrato, não movimento de vasilhame.
 */
final class SatelitesMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'satelites';
    }

    public function dependeDe(): array
    {
        return ['clientes', 'produtos', 'financeiro'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;

        // Sem nenhuma das origens não há o que migrar — e isso precisa ser
        // DITO, não reportado como "0 lidos" bem-sucedido (foi assim que os
        // módulos ficaram vazios sem ninguém perceber).
        $origens = ['valegas', 'comodatos', 'conveniofechamentos'];
        $disponiveis = array_filter($origens, fn ($t) => $this->tabelaExiste($ctx, $t));
        if ($disponiveis === []) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado indisponível ou sem as tabelas de satélites '
                    .'(vale-gás, comodato, convênio)']);
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $avisos = [];

        $idsCliente = $this->idsDe('clientes');
        $idsProduto = $this->idsDe('produtos');
        $idsPedido = $this->idsDe('pedidos');
        $idsFinanceiro = $this->tabelaDestinoExiste('financeiros') ? $this->idsDe('financeiros') : [];
        $empresaDoCliente = $this->empresaPorCliente();

        // ── Vale-gás ──
        if ($this->tabelaExiste($ctx, 'valegas')) {
            $lote = [];
            foreach ($ctx->legado()->table('valegas')->orderBy('id')->get() as $r) {
                $lidos++;
                $cliente = (int) $r->cliente_id;
                if (! isset($idsCliente[$cliente])) {
                    $pulados++;

                    continue;
                }
                $produto = (int) ($r->produto_id ?? 0);
                $pedido = (int) ($r->pedido_id ?? 0);

                $lote[] = [
                    'id' => (int) $r->id,
                    'empresa_id' => $empresaDoCliente[$cliente] ?? (int) $r->empresa_id,
                    'grupo_id' => (int) $r->grupo_id,
                    'cliente_id' => $cliente,
                    'produto_id' => isset($idsProduto[$produto]) ? $produto : null,
                    'codigo' => mb_substr((string) $r->codigo, 0, 40),
                    // O legado guarda o valor no produto, não no vale.
                    'valor' => 0,
                    'situacao' => $r->databaixa !== null ? 'UTILIZADO' : 'EMITIDO',
                    'pedido_id' => isset($idsPedido[$pedido]) ? $pedido : null,
                    'utilizado_em' => $r->databaixa ?? null,
                    'created_at' => $r->created_at ?? null,
                ];
            }
            if ($lote !== [] && ! $ctx->dryRun) {
                $gravados += $this->gravarPreservandoId('vale_gas', $lote, ['id'], 1000);
            }
            if ($lote !== []) {
                $avisos[] = 'vale-gás migrado com valor 0: o legado guarda o valor no '
                    .'produto, não no vale';
            }
        }

        // ── Comodato ──
        if ($this->tabelaExiste($ctx, 'comodatos')) {
            $produtoPadrao = (int) ($this->destino()->table('produtos')->min('id') ?? 0);

            // Itens REAIS do contrato (COMODATOITEMS, 1.344 no dump auditado):
            // produto e quantidade vêm daqui — o "produto padrão qtde 1" só
            // resta para contrato sem item (auditoria 2026-08-14, P1).
            $itensPorComodato = [];
            if ($this->tabelaExiste($ctx, 'comodatoitems')) {
                foreach ($ctx->legado()->table('comodatoitems')->orderBy('id')->get() as $i) {
                    $itensPorComodato[(int) $i->comodato_id][] = [
                        'produto_id' => (int) ($i->produto_id ?? 0),
                        'quantidade' => (float) ($i->quantidade ?? 0),
                    ];
                }
            }

            $semItem = 0;
            $lote = [];
            foreach ($ctx->legado()->table('comodatos')->orderBy('id')->get() as $r) {
                $lidos++;
                $cliente = (int) $r->cliente_id;
                if (! isset($idsCliente[$cliente]) || $produtoPadrao === 0) {
                    $pulados++;

                    continue;
                }

                // Produto do item de maior quantidade; quantidade = soma dos itens.
                $produto = $produtoPadrao;
                $quantidade = 1.0;
                $itens = $itensPorComodato[(int) $r->id] ?? [];
                if ($itens !== []) {
                    usort($itens, fn ($a, $b) => $b['quantidade'] <=> $a['quantidade']);
                    $candidato = $itens[0]['produto_id'];
                    if (isset($idsProduto[$candidato])) {
                        $produto = $candidato;
                    }
                    $quantidade = max(1.0, array_sum(array_column($itens, 'quantidade')));
                } else {
                    $semItem++;
                }

                $lote[] = [
                    'id' => (int) $r->id,
                    'empresa_id' => $empresaDoCliente[$cliente] ?? (int) $r->empresa_id,
                    'grupo_id' => (int) $r->grupo_id,
                    'cliente_id' => $cliente,
                    'produto_id' => $produto,
                    'quantidade' => $quantidade,
                    'quantidade_devolvida' => 0,
                    'situacao' => $this->booleano($r->ativo ?? '1') ? 'ATIVO' : 'ENCERRADO',
                    'data_emprestimo' => $r->datacontrato,
                    // `datavencimento` e QUANDO DEVERIA voltar; jogar em
                    // `data_devolucao` (quando VOLTOU) dava um comodato aberto
                    // como se ja tivesse sido devolvido.
                    'data_vencimento' => $r->datavencimento ?? null,
                    'data_devolucao' => null,
                    'nome_representante' => $r->nomerepresentante ?? null,
                    'cpf_representante' => ($r->cpfrepresentante ?? null) !== null
                        ? mb_substr(preg_replace('/\D/', '', (string) $r->cpfrepresentante) ?: '', 0, 11) ?: null
                        : null,
                    'rg_representante' => ($r->rgrepresentante ?? null) !== null
                        ? mb_substr(trim((string) $r->rgrepresentante), 0, 20) : null,
                    'created_at' => $r->created_at ?? null,
                ];
            }
            if ($lote !== [] && ! $ctx->dryRun) {
                $gravados += $this->gravarPreservandoId('comodatos', $lote, ['id'], 500);
            }
            if ($semItem > 0) {
                $avisos[] = "{$semItem} comodato(s) sem item no legado — produto padrão e quantidade 1";
            }
        }

        // ── Convênio: cria o cadastro a partir dos clientes conveniados ──
        if ($this->tabelaExiste($ctx, 'conveniofechamentos')) {
            [$g, $n, $p, $aviso] = $this->migrarConvenios($ctx, $idsCliente, $empresaDoCliente, $idsFinanceiro);
            $gravados += $g;
            $lidos += $n;
            $pulados += $p;
            if ($aviso !== null) {
                $avisos[] = $aviso;
            }
        }

        if ($pulados > 0) {
            $avisos[] = "{$pulados} registro(s) descartado(s): cliente ausente no destino";
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

        return [
            new CountInvariant($ctx, 'valegas', 'vale_gas'),
            new CountInvariant($ctx, 'comodatos', 'comodatos'),
        ];
    }

    /**
     * Convênio: o legado fecha por CLIENTE; o destino exige um convênio.
     *
     * @return array{0:int,1:int,2:int,3:?string} gravados, lidos, pulados, aviso
     */
    private function migrarConvenios(
        MigrationContext $ctx, array $idsCliente, array $empresaDoCliente, array $idsFinanceiro
    ): array {
        if (! $this->tabelaDestinoExiste('convenios')
            || ! $this->tabelaDestinoExiste('convenio_fechamentos')) {
            return [0, 0, 0, null];
        }

        $fechamentos = $ctx->legado()->table('conveniofechamentos')->orderBy('id')->get();
        if ($fechamentos->isEmpty()) {
            return [0, 0, 0, null];
        }

        // Um convênio por cliente que tem fechamento.
        $clientes = [];
        foreach ($fechamentos as $f) {
            $c = (int) $f->cliente_id;
            if (isset($idsCliente[$c])) {
                $clientes[$c] = true;
            }
        }

        $colunasConvenio = DB::getSchemaBuilder()->getColumnListing('convenios');
        $convenioDoCliente = [];
        $novos = [];
        $proximo = (int) ($this->destino()->table('convenios')->max('id') ?? 0);

        $grupoDaEmpresa = $this->grupoPorEmpresa();
        // O convênio no legado não tem nome próprio — é do cliente titular,
        // então é o nome dele que identifica o convênio na tela.
        $nomeDoCliente = $this->destino()->table('clientes')
            ->whereIn('id', array_keys($clientes))
            ->pluck('nome', 'id');

        foreach (array_keys($clientes) as $clienteId) {
            $proximo++;
            $convenioDoCliente[$clienteId] = $proximo;
            $empresaId = $empresaDoCliente[$clienteId] ?? 0;
            $linha = [
                'id' => $proximo,
                'empresa_id' => $empresaId,
                'grupo_id' => $grupoDaEmpresa[$empresaId] ?? null,
                'cliente_id' => $clienteId,
                'descricao' => mb_substr(
                    (string) ($nomeDoCliente[$clienteId] ?? "Convênio {$clienteId}"), 0, 255
                ),
                'ativo' => true,
            ];
            $novos[] = array_filter(
                $linha,
                fn ($k) => in_array($k, $colunasConvenio, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        $gravados = 0;
        if (! $ctx->dryRun && $novos !== []) {
            $gravados += $this->gravarPreservandoId('convenios', $novos, ['id'], 500);
        }

        $lote = [];
        $pulados = 0;
        foreach ($fechamentos as $f) {
            $cliente = (int) $f->cliente_id;
            $convenio = $convenioDoCliente[$cliente] ?? null;
            if ($convenio === null) {
                $pulados++;

                continue;
            }
            $financeiro = (int) ($f->financeiro_id ?? 0);
            $lote[] = [
                'id' => (int) $f->id,
                'empresa_id' => $empresaDoCliente[$cliente] ?? (int) $f->empresa_id,
                'convenio_id' => $convenio,
                'financeiro_id' => isset($idsFinanceiro[$financeiro]) ? $financeiro : null,
                // O legado guarda emissão e vencimento, não o período apurado.
                'periodo_inicio' => $f->dataemissao,
                'periodo_fim' => $f->datavencimento ?? $f->dataemissao,
                'valor_total' => round((float) $f->valor, 2),
                'total_pedidos' => 0,
                'situacao' => 'FECHADO',
                'created_at' => $f->created_at ?? null,
            ];
        }

        if (! $ctx->dryRun && $lote !== []) {
            $gravados += $this->gravarPreservandoId('convenio_fechamentos', $lote, ['id'], 500);
        }

        $aviso = count($novos).' convênio(s) criados a partir dos clientes com '
            .'fechamento (o legado não tem cadastro de convênio); período do '
            .'fechamento derivado de emissão/vencimento';

        return [$gravados, count($fechamentos), $pulados, $aviso];
    }

    /** @return array<int,int> empresa_id => grupo_id */
    private function grupoPorEmpresa(): array
    {
        $out = [];
        foreach ($this->destino()->table('empresas')->select('id', 'grupo_id')->get() as $e) {
            $out[(int) $e->id] = (int) $e->grupo_id;
        }

        return $out;
    }

    /** @return array<int,int> */
    private function empresaPorCliente(): array
    {
        $out = [];
        foreach ($this->destino()->table('clientes')->select('id', 'empresa_id')->cursor() as $c) {
            $out[(int) $c->id] = (int) $c->empresa_id;
        }

        return $out;
    }

    private function booleano(mixed $v): bool
    {
        $v = mb_strtoupper(trim((string) ($v ?? '')));

        return in_array($v, ['1', 'S', 'T', 'TRUE', 'Y'], true);
    }

    /** @return array<int,true> */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach ($this->destino()->table($tabela)->select('id')->cursor() as $r) {
            $ids[(int) $r->id] = true;
        }

        return $ids;
    }

    private function tabelaDestinoExiste(string $tabela): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
