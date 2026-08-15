<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * F15 — migra CRM: pós-venda (pesquisas com questionário), metas, sorteios,
 * promoções e checklists.
 *
 * REESCRITO após a auditoria 2026-08-14 (a versão anterior lia tabelas
 * inexistentes e migrava zero). Diferenças de modelagem resolvidas:
 *
 *  - Pós-venda: o legado tem campanha (POSVENDAS) → perguntas → respostas
 *    possíveis, e cada PESQUISA registra as respostas dadas. O destino
 *    (`pos_vendas`) é um registro único por contato. A pesquisa vira a linha;
 *    o questionário respondido é PRESERVADO em `observacao` ("Pergunta:
 *    Resposta; ..."), e a campanha vira `canal`.
 *  - Metas: o legado é por produto/setor/mês com meta+desafio+perfil; o destino
 *    guarda competência+valor — migra o valor da meta, um registro por linha.
 *  - Sorteios: o legado registra o CUPOM (pedido/cliente); vira um sorteio por
 *    linha + o número do cliente em `sorteio_numeros`.
 */
final class CrmMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'crm';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'clientes', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $origens = ['posvendapesquisas', 'metavendas', 'sorteios', 'checklists', 'promocoes'];
        if (array_filter($origens, fn ($t) => $this->tabelaExiste($ctx, $t)) === []) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['tabelas de CRM ausentes no espelho do legado — re-rodar espelhar_oracle.py']);
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;

        $idsCliente = $this->idsDe('clientes');
        $idsPedido = $this->idsDe('pedidos');
        $empresaDoCliente = $this->mapa('clientes', 'empresa_id');
        $empresaPadrao = (int) (DB::table('empresas')->min('id') ?? 0);
        $grupoPadrao = (int) (DB::table('grupos')->min('id') ?? 0);

        // ── Pós-venda: campanha + questionário → pos_vendas ──
        if ($this->tabelaExiste($ctx, 'posvendapesquisas')) {
            $campanhas = [];
            foreach ($this->linhas($ctx, 'posvendas') as $r) {
                $campanhas[(int) $r->id] = trim((string) ($r->descricao ?? ''));
            }
            $perguntaDaResposta = [];
            $textoResposta = [];
            foreach ($this->linhas($ctx, 'posvendarespostas') as $r) {
                $perguntaDaResposta[(int) $r->id] = (int) ($r->posvendapergunta_id ?? 0);
                $textoResposta[(int) $r->id] = trim((string) ($r->descricao ?? ''));
            }
            $textoPergunta = [];
            foreach ($this->linhas($ctx, 'posvendaperguntas') as $r) {
                $textoPergunta[(int) $r->id] = trim((string) ($r->descricao ?? ''));
            }
            $respostasDaPesquisa = [];
            foreach ($this->linhas($ctx, 'posvendapesquisarespostas') as $r) {
                $resposta = (int) ($r->posvendaresposta_id ?? 0);
                $pergunta = $textoPergunta[$perguntaDaResposta[$resposta] ?? 0] ?? null;
                $texto = $textoResposta[$resposta] ?? null;
                if ($texto !== null) {
                    $respostasDaPesquisa[(int) $r->posvendapesquisa_id][] =
                        ($pergunta !== null ? "{$pergunta}: " : '').$texto;
                }
            }

            $lote = [];
            foreach ($this->linhas($ctx, 'posvendapesquisas') as $r) {
                $lidos++;
                $cliente = (int) ($r->cliente_id ?? 0);
                $empresa = $empresaDoCliente[$cliente] ?? $empresaPadrao;
                if ($empresa === 0 || ($r->datahora ?? null) === null) {
                    $pulados++;

                    continue;
                }
                $pedido = (int) ($r->pedido_id ?? 0);
                $questionario = implode('; ', $respostasDaPesquisa[(int) $r->id] ?? []);
                $obs = trim((string) ($r->observacao ?? ''));

                $lote[] = [
                    'id' => (int) $r->id,
                    'empresa_id' => $empresa,
                    'cliente_id' => isset($idsCliente[$cliente]) ? $cliente : null,
                    'pedido_id' => isset($idsPedido[$pedido]) ? $pedido : null,
                    'data' => $r->datahora,
                    'canal' => mb_substr($campanhas[(int) ($r->posvenda_id ?? 0)] ?? 'Pós-venda', 0, 60),
                    'observacao' => mb_substr(trim($questionario.($obs !== '' ? " | {$obs}" : '')), 0, 1000) ?: null,
                    'situacao' => 'RESPONDIDA',
                    'created_at' => $r->created_at ?? null,
                ];
            }
            $gravados += $this->gravarSeNaoDry($ctx, 'pos_vendas', $lote);
        }

        // ── Metas de venda ──
        if ($this->tabelaExiste($ctx, 'metavendas') && $empresaPadrao > 0) {
            $empresas = $this->idsDe('empresas');
            $lote = [];
            foreach ($this->linhas($ctx, 'metavendas') as $r) {
                $lidos++;
                if (($r->datameta ?? null) === null) {
                    $pulados++;

                    continue;
                }
                $empresa = (int) ($r->empresa_id ?? 0);
                $lote[] = [
                    'id' => (int) $r->id,
                    'empresa_id' => isset($empresas[$empresa]) ? $empresa : $empresaPadrao,
                    'competencia' => mb_substr((string) $r->datameta, 0, 7),
                    'meta_valor' => round((float) ($r->valormeta ?? 0), 2),
                    'realizado_valor' => 0,
                    'created_at' => $r->created_at ?? null,
                ];
            }
            $gravados += $this->gravarSeNaoDry($ctx, 'meta_vendas', $lote);
        }

        // ── Sorteios (cupom por pedido/cliente) ──
        if ($this->tabelaExiste($ctx, 'sorteios') && $grupoPadrao > 0) {
            $lote = [];
            $numeros = [];
            foreach ($this->linhas($ctx, 'sorteios') as $r) {
                $lidos++;
                $id = (int) $r->id;
                $sorteado = ($r->datasorteio ?? null) !== null;
                $lote[] = [
                    'id' => $id,
                    'grupo_id' => (int) ($r->grupo_id ?? 0) ?: $grupoPadrao,
                    'descricao' => 'Sorteio #'.$id.($this->booleano($r->app ?? null) ? ' (app)' : ''),
                    'data_sorteio' => $r->datasorteio ?? ($r->datafim ?? null),
                    'situacao' => $sorteado ? 'SORTEADO' : 'ABERTO',
                    'created_at' => $r->created_at ?? null,
                ];
                $cliente = (int) ($r->cliente_id ?? 0);
                if (isset($idsCliente[$cliente])) {
                    $numeros[] = [
                        'id' => $id,
                        'sorteio_id' => $id,
                        'cliente_id' => $cliente,
                        'numero' => $id,
                        'created_at' => $r->created_at ?? null,
                    ];
                }
            }
            $gravados += $this->gravarSeNaoDry($ctx, 'sorteios', $lote);
            $gravados += $this->gravarSeNaoDry($ctx, 'sorteio_numeros', $numeros);
        }

        // ── Promoções (vazia no dump auditado; estrutura tolerante) ──
        if ($this->tabelaExiste($ctx, 'promocoes') && $grupoPadrao > 0) {
            $lote = [];
            foreach ($this->linhas($ctx, 'promocoes') as $r) {
                $lidos++;
                $lote[] = [
                    'id' => (int) $r->id,
                    'grupo_id' => (int) ($r->grupo_id ?? 0) ?: $grupoPadrao,
                    'descricao' => mb_substr(trim((string) ($r->descricao ?? "Promoção {$r->id}")), 0, 255),
                    'inicio' => $r->inicio ?? ($r->datainicio ?? null),
                    'fim' => $r->fim ?? ($r->datafim ?? null),
                    'desconto_percentual' => isset($r->descontopercentual) ? (float) $r->descontopercentual : null,
                    'ativo' => $this->booleano($r->ativo ?? '1'),
                    'created_at' => $r->created_at ?? null,
                ];
            }
            $gravados += $this->gravarSeNaoDry($ctx, 'promocoes', $lote);
        }

        // ── Checklists + execuções (o legado executa por FORM) ──
        if ($this->tabelaExiste($ctx, 'checklists') && $grupoPadrao > 0) {
            $checklistDoForm = [];
            $lote = [];
            foreach ($this->linhas($ctx, 'checklists') as $r) {
                $lidos++;
                $lote[] = [
                    'id' => (int) $r->id,
                    'grupo_id' => (int) ($r->grupo_id ?? 0) ?: $grupoPadrao,
                    'descricao' => mb_substr(trim((string) ($r->descricao ?? "Checklist {$r->id}")), 0, 255),
                    'ativo' => $this->booleano($r->ativo ?? '1'),
                    'created_at' => $r->created_at ?? null,
                ];
                if (($r->checklistform_id ?? null) !== null) {
                    $checklistDoForm[(int) $r->checklistform_id] = (int) $r->id;
                }
            }
            $gravados += $this->gravarSeNaoDry($ctx, 'checklists', $lote);

            if ($this->tabelaExiste($ctx, 'checklistexecucoes')) {
                $empresas = $this->idsDe('empresas');
                $lote = [];
                foreach ($this->linhas($ctx, 'checklistexecucoes') as $r) {
                    $lidos++;
                    $checklist = $checklistDoForm[(int) ($r->checklistform_id ?? 0)] ?? null;
                    $empresa = (int) ($r->empresa_id ?? 0);
                    if ($checklist === null || ! isset($empresas[$empresa])) {
                        $pulados++;

                        continue;
                    }
                    $lote[] = [
                        'id' => (int) $r->id,
                        'checklist_id' => $checklist,
                        'empresa_id' => $empresa,
                        'data' => $r->datahorapesquisa ?? null,
                        'created_at' => $r->created_at ?? null,
                    ];
                }
                $gravados += $this->gravarSeNaoDry($ctx, 'checklist_execucoes', $lote);
            }
        }

        return new MigrationResult($this->nome(), $lidos, $ctx->dryRun ? 0 : $gravados, $pulados);
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'posvendapesquisas', 'pos_vendas'),
            new CountInvariant($ctx, 'metavendas', 'meta_vendas'),
            new CountInvariant($ctx, 'sorteios', 'sorteios'),
            new IntegrityInvariant($ctx, 'pos_vendas', 'empresa_id', 'empresas'),
        ];
    }

    /** @return iterable<object> */
    private function linhas(MigrationContext $ctx, string $tabela): iterable
    {
        try {
            return $ctx->legado()->table($tabela)->orderBy('id')->get();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param list<array<string,mixed>> $lote */
    private function gravarSeNaoDry(MigrationContext $ctx, string $tabela, array $lote): int
    {
        if ($ctx->dryRun || $lote === []) {
            return 0;
        }

        return $this->gravarPreservandoId($tabela, $lote, ['id'], 1000);
    }

    /** @return array<int,true> ids do DESTINO */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach (DB::table($tabela)->pluck('id') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    /** @return array<int,int> id => coluna, do DESTINO */
    private function mapa(string $tabela, string $coluna): array
    {
        $out = [];
        foreach (DB::table($tabela)->select('id', $coluna)->cursor() as $r) {
            $out[(int) $r->id] = (int) $r->{$coluna};
        }

        return $out;
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

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }

    private function booleano(mixed $v): bool
    {
        return in_array(mb_strtoupper(trim((string) ($v ?? ''))), ['1', 'S', 'T', 'TRUE', 'Y'], true);
    }
}
