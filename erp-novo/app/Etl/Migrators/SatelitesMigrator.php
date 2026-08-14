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
            $produtoPadrao = (int) (DB::table('produtos')->min('id') ?? 0);
            $lote = [];
            foreach ($ctx->legado()->table('comodatos')->orderBy('id')->get() as $r) {
                $lidos++;
                $cliente = (int) $r->cliente_id;
                if (! isset($idsCliente[$cliente]) || $produtoPadrao === 0) {
                    $pulados++;

                    continue;
                }
                $lote[] = [
                    'id' => (int) $r->id,
                    'empresa_id' => $empresaDoCliente[$cliente] ?? (int) $r->empresa_id,
                    'grupo_id' => (int) $r->grupo_id,
                    'cliente_id' => $cliente,
                    'produto_id' => $produtoPadrao,
                    'quantidade' => 1,
                    'quantidade_devolvida' => 0,
                    'situacao' => $this->booleano($r->ativo ?? '1') ? 'ATIVO' : 'ENCERRADO',
                    'data_emprestimo' => $r->datacontrato,
                    'data_devolucao' => $r->datavencimento ?? null,
                    'created_at' => $r->created_at ?? null,
                ];
            }
            if ($lote !== [] && ! $ctx->dryRun) {
                $gravados += $this->gravarPreservandoId('comodatos', $lote, ['id'], 500);
            }
            if ($lote !== []) {
                $avisos[] = 'comodato do legado é CONTRATO (cliente + datas), sem produto '
                    .'nem quantidade — migrado com produto padrão e quantidade 1';
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
        $ctx = $this->ctxAtual ?? new MigrationContext();

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
        $proximo = (int) (DB::table('convenios')->max('id') ?? 0);

        foreach (array_keys($clientes) as $clienteId) {
            $proximo++;
            $convenioDoCliente[$clienteId] = $proximo;
            $linha = [
                'id' => $proximo,
                'empresa_id' => $empresaDoCliente[$clienteId] ?? 0,
                'cliente_id' => $clienteId,
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

    /** @return array<int,int> */
    private function empresaPorCliente(): array
    {
        $out = [];
        foreach (DB::table('clientes')->select('id', 'empresa_id')->cursor() as $c) {
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
        foreach (DB::table($tabela)->select('id')->cursor() as $r) {
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
