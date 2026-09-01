<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;

/**
 * Cadastros contábeis e fiscais que nenhum migrador cobria.
 *
 * Plano de contas (169), centros de custo (110) e operações fiscais (66) —
 * pequenos em volume, mas é o que faz as telas de configuração aparecerem
 * vazias e o que dá SIGNIFICADO ao financeiro: sem plano de contas, os 443 mil
 * lançamentos migrados não se agrupam em DRE nem em relatório gerencial.
 *
 * Ficaram de fora porque a auditoria os marcou como "cobertos" — as tabelas
 * existiam no schema novo (`planos_conta`, `centros_custo`,
 * `operacoes_fiscais`), mas ninguém as preenchia.
 *
 * Escopo por GRUPO (não por empresa): são cadastros compartilhados na rede.
 */
final class CadastrosContabeisMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'cadastros-contabeis';
    }

    public function dependeDe(): array
    {
        return ['empresas'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;

        $origens = ['planocontas', 'centrocustos', 'nfoperacaos'];
        if (array_filter($origens, fn ($t) => $this->tabelaExiste($ctx, $t)) === []) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado indisponível ou sem os cadastros contábeis/fiscais']);
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $avisos = [];

        $grupoPadrao = (int) ($this->destino()->table('grupos')->min('id') ?? 1);

        [$n, $g, $p] = $this->planoDeContas($ctx, $grupoPadrao);
        $lidos += $n;
        $gravados += $g;
        $pulados += $p;

        [$n, $g, $p] = $this->centrosDeCusto($ctx, $grupoPadrao);
        $lidos += $n;
        $gravados += $g;
        $pulados += $p;

        [$n, $g, $p] = $this->operacoesFiscais($ctx, $grupoPadrao);
        $lidos += $n;
        $gravados += $g;
        $pulados += $p;

        // Com o plano de contas presente, os títulos podem receber o vínculo
        // que ficou nulo quando o financeiro foi migrado sem ele.
        $religados = $ctx->dryRun ? 0 : $this->religarFinanceiro($ctx);
        if ($religados > 0) {
            $avisos[] = "{$religados} título(s) financeiros religados ao plano de "
                .'contas/centro de custo (o rateio já existia no legado)';
        }

        if ($pulados > 0) {
            $avisos[] = "{$pulados} cadastro(s) descartado(s) por duplicidade ou "
                .'referência inválida';
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
            new CountInvariant($ctx, 'planocontas', 'planos_conta'),
            new CountInvariant($ctx, 'centrocustos', 'centros_custo'),
        ];
    }

    /**
     * Plano de contas — árvore com `pai_id` auto-referente.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function planoDeContas(MigrationContext $ctx, int $grupoPadrao): array
    {
        if (! $this->tabelaExiste($ctx, 'planocontas')) {
            return [0, 0, 0];
        }

        $linhas = [];
        $pais = [];
        $lidos = 0;

        foreach ($ctx->legado()->table('planocontas')->orderBy('id')->get() as $r) {
            $lidos++;
            $id = (int) $r->id;
            $pai = (int) ($r->paiplanoconta_id ?? 0);
            if ($pai > 0) {
                $pais[$id] = $pai;
            }

            $linhas[] = [
                'id' => $id,
                'grupo_id' => (int) ($r->grupo_id ?? 0) ?: $grupoPadrao,
                'codigo' => mb_substr((string) ($r->codigo ?? ''), 0, 30) ?: null,
                'descricao' => mb_substr(trim((string) $r->descricao), 0, 255),
                'pagarreceber' => $this->pagarReceber($r->pagarreceber ?? null),
                'nivel' => (int) ($r->nivel ?? 1) ?: 1,
                'ativo' => $this->booleano($r->ativo ?? '1'),
                'created_at' => $r->created_at ?? null,
            ];
        }

        if ($linhas === [] || $ctx->dryRun) {
            return [$lidos, 0, 0];
        }

        // Grava SEM o pai e religa depois: a árvore referencia linhas que ainda
        // não existem quando o lote é inserido.
        $gravados = $this->gravarPreservandoId('planos_conta', $linhas);
        $gravados += $this->religarArvore('planos_conta', $pais);

        return [$lidos, $gravados, 0];
    }

    /** @return array{0:int,1:int,2:int} */
    private function centrosDeCusto(MigrationContext $ctx, int $grupoPadrao): array
    {
        if (! $this->tabelaExiste($ctx, 'centrocustos')) {
            return [0, 0, 0];
        }

        $linhas = [];
        $pais = [];
        $lidos = 0;

        foreach ($ctx->legado()->table('centrocustos')->orderBy('id')->get() as $r) {
            $lidos++;
            $id = (int) $r->id;
            $pai = (int) ($r->paicentrocusto_id ?? 0);
            if ($pai > 0) {
                $pais[$id] = $pai;
            }

            $linhas[] = [
                'id' => $id,
                'grupo_id' => (int) ($r->grupo_id ?? 0) ?: $grupoPadrao,
                'codigo' => mb_substr((string) ($r->codigo ?? ''), 0, 30) ?: null,
                'descricao' => mb_substr(trim((string) $r->descricao), 0, 255),
                'nivel' => (int) ($r->nivel ?? 1) ?: 1,
                'ativo' => $this->booleano($r->ativo ?? '1'),
                'created_at' => $r->created_at ?? null,
            ];
        }

        if ($linhas === [] || $ctx->dryRun) {
            return [$lidos, 0, 0];
        }

        $gravados = $this->gravarPreservandoId('centros_custo', $linhas);
        $gravados += $this->religarArvore('centros_custo', $pais);

        return [$lidos, $gravados, 0];
    }

    /**
     * Operações fiscais (natureza da operação + CFOP).
     *
     * @return array{0:int,1:int,2:int}
     */
    private function operacoesFiscais(MigrationContext $ctx, int $grupoPadrao): array
    {
        if (! $this->tabelaExiste($ctx, 'nfoperacaos')) {
            return [0, 0, 0];
        }

        $linhas = [];
        $lidos = 0;
        $pulados = 0;
        // O destino tem UNIQUE (grupo_id, descricao) e o legado repete a mesma
        // natureza de operação em ids diferentes. Fica a primeira.
        $vistas = [];

        foreach ($ctx->legado()->table('nfoperacaos')->orderBy('id')->get() as $r) {
            $lidos++;
            $grupo = (int) ($r->grupo_id ?? 0) ?: $grupoPadrao;
            $descricao = mb_substr(trim((string) $r->descricao), 0, 255);
            $chave = $grupo.'|'.mb_strtolower($descricao);
            if (isset($vistas[$chave])) {
                $pulados++;

                continue;
            }
            $vistas[$chave] = true;

            $linhas[] = [
                'id' => (int) $r->id,
                'grupo_id' => $grupo,
                'descricao' => $descricao,
                'descricao_fiscal' => mb_substr((string) ($r->descricaofiscal ?? ''), 0, 255) ?: null,
                'cfop' => mb_substr(preg_replace('/\D/', '', (string) ($r->cfop ?? '')), 0, 4) ?: null,
                // No legado são strings ('1'/'0'/'S'), não boolean.
                'movimenta_estoque' => $this->booleano($r->movimentaestoque ?? null),
                'movimenta_financeiro' => $this->booleano($r->movimentafinanceiro ?? null),
                'ativo' => true,
                'created_at' => $r->created_at ?? null,
            ];
        }

        if ($linhas === [] || $ctx->dryRun) {
            return [$lidos, 0, $pulados];
        }

        return [$lidos, $this->gravarPreservandoId('operacoes_fiscais', $linhas), $pulados];
    }

    /**
     * Aplica o `pai_id` depois que todas as linhas existem, ignorando pai que
     * não veio no dump (a FK é nullOnDelete: referência solta viraria erro).
     *
     * @param  array<int,int>  $pais  filho => pai
     */
    private function religarArvore(string $tabela, array $pais): int
    {
        if ($pais === []) {
            return 0;
        }

        $existentes = $this->destino()->table($tabela)->pluck('id')->flip();
        $n = 0;
        foreach ($pais as $filho => $pai) {
            if (! $existentes->has($pai) || ! $existentes->has($filho)) {
                continue;
            }
            $this->destino()->table($tabela)->where('id', $filho)->update(['pai_id' => $pai]);
            $n++;
        }

        return $n;
    }

    /**
     * Religa `financeiros.planoconta_id` / `centrocusto_id`.
     *
     * O FinanceiroMigrator rodou antes destes cadastros existirem, então
     * anulou as duas FKs (sanitização correta na época). Agora que o plano de
     * contas está no destino, o vínculo é refeito a partir do rateio de maior
     * valor do legado — é o que faz o DRE e os relatórios por conta pararem de
     * vir zerados.
     */
    private function religarFinanceiro(MigrationContext $ctx): int
    {
        if (! $this->tabelaExiste($ctx, 'financeirorateios')
            || $this->destino()->table('financeiros')->whereNotNull('planoconta_id')->exists()) {
            return 0;
        }

        $planos = $this->destino()->table('planos_conta')->pluck('id')->flip();
        $centros = $this->destino()->table('centros_custo')->pluck('id')->flip();
        if ($planos->isEmpty()) {
            return 0;
        }

        // Rateio de MAIOR valor por título (o destino guarda um por título).
        $melhor = [];
        $ctx->legado()->table('financeirorateios')
            ->select('financeiro_id', 'planoconta_id', 'centrocusto_id', 'valor')
            ->orderBy('financeiro_id')
            ->chunk(20000, function ($rows) use (&$melhor) {
                foreach ($rows as $r) {
                    $fid = (int) $r->financeiro_id;
                    $valor = (float) ($r->valor ?? 0);
                    if (! isset($melhor[$fid]) || $valor > $melhor[$fid]['valor']) {
                        $melhor[$fid] = [
                            'valor' => $valor,
                            'plano' => (int) ($r->planoconta_id ?? 0),
                            'centro' => (int) ($r->centrocusto_id ?? 0),
                        ];
                    }
                }
            });

        $n = 0;
        foreach (array_chunk($melhor, 1000, true) as $bloco) {
            foreach ($bloco as $fid => $dados) {
                $plano = $planos->has($dados['plano']) ? $dados['plano'] : null;
                $centro = $centros->has($dados['centro']) ? $dados['centro'] : null;
                if ($plano === null && $centro === null) {
                    continue;
                }
                $this->destino()->table('financeiros')->where('id', $fid)->update([
                    'planoconta_id' => $plano,
                    'centrocusto_id' => $centro,
                ]);
                $n++;
            }
        }

        return $n;
    }

    /** 'P' (pagar) ou 'R' (receber) — o destino é char(1). */
    private function pagarReceber(mixed $v): string
    {
        return str_starts_with(mb_strtoupper(trim((string) ($v ?? ''))), 'P') ? 'P' : 'R';
    }

    private function booleano(mixed $v): bool
    {
        $v = mb_strtoupper(trim((string) ($v ?? '')));

        return in_array($v, ['1', 'S', 'T', 'TRUE', 'Y'], true);
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
