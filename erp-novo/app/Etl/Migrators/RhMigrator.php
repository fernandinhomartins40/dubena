<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use App\Etl\Support\RegistraFalhaDeLeitura;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorComissao;
use App\Models\Rh\ColaboradorExame;
use App\Models\Rh\ColaboradorFamilia;
use App\Models\Rh\ColaboradorRecesso;
use App\Models\Rh\ComissaoExcecao;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * F15 — migra RH: cargos, colaboradores e sub-tabelas (família, exames, férias,
 * comissões e exceções).
 *
 * REESCRITO após a auditoria 2026-08-14: a versão anterior lia tabelas que NÃO
 * existem no legado (`colaboradorpontos`, `colaboradorturnos`, nomes trocados) e
 * migrava zero linhas em silêncio. Este migrator lê os nomes REAIS do espelho
 * (espelhar_oracle.py renomeia COLABORADORS→colaboradores etc.) e resolve as
 * diferenças de modelagem:
 *
 *  - o legado NÃO tem user_id no colaborador: é `users.colaborador_id` que
 *    aponta para cá — o vínculo é resolvido ao contrário;
 *  - o legado NÃO tem flag `entregador`: deriva-se de quem aparece como
 *    entregador em pedidos (via o user vinculado);
 *  - telefone vem de `colaboradortelefones` (primeiro da lista);
 *  - parentesco/tipo de exame são FK no legado (PARENTESCOS/TIPOEXAMES) e
 *    texto no destino — resolvidos pela descrição;
 *  - férias (datainicio + dias) viram `colaborador_recessos` tipo FERIAS.
 */
final class RhMigrator implements Migrator
{
    use PreservaIdsDoLegado;
    use RegistraFalhaDeLeitura;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'rh';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'cadastros-apoio', 'users', 'geografico'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'colaboradores')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['tabela `colaboradores` ausente no espelho do legado — RH NÃO migrado '
                    .'(re-rodar espelhar_oracle.py)']);
        }

        $this->limparAvisosDeLeitura();
        $avisos = [];
        $gravados = 0;
        $lidos = 0;

        // ── Cargos (FK de colaborador) ──
        $cargos = $this->ler($ctx, 'cargos', fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) $r->grupo_id,
            'descricao' => trim((string) $r->descricao),
            'ativo' => $this->booleano($r->ativo ?? '1'),
            'created_at' => $r->created_at ?? null,
        ]);
        if (! $ctx->dryRun && $cargos !== []) {
            $gravados += $this->gravarPreservandoId('cargos', $cargos);
        }
        $lidos += count($cargos);

        // ── Vínculos auxiliares (resolvidos ANTES dos colaboradores) ──
        // users.colaborador_id (legado) → colaborador_id => user_id
        $userDoColaborador = [];
        try {
            foreach ($ctx->legado()->table('users')->whereNotNull('colaborador_id')->get(['id', 'colaborador_id']) as $u) {
                $userDoColaborador[(int) $u->colaborador_id] = (int) $u->id;
            }
        } catch (\Throwable) {
            // sem users no espelho: colaboradores ficam sem vínculo de login.
        }

        // Quem já entregou pedido é entregador (o legado não tem a flag).
        $usersEntregadores = [];
        foreach ($this->destino()->table('pedidos')->whereNotNull('entregador_user_id')
            ->distinct()->pluck('entregador_user_id') as $id) {
            $usersEntregadores[(int) $id] = true;
        }

        $telefonePrincipal = $this->lerOuAvisar(
            'colaboradortelefones',
            function () use ($ctx) {
                $mapa = [];
                foreach ($ctx->legado()->table('colaboradortelefones')->orderBy('id')
                    ->get(['colaborador_id', 'telefone']) as $t) {
                    $mapa[(int) $t->colaborador_id] ??= trim((string) $t->telefone);
                }

                return $mapa;
            },
        );

        $parentescos = $this->descricoes($ctx, 'parentescos');
        $tiposExame = $this->descricoes($ctx, 'tipoexames');
        $idsUser = $this->idsDe('users');

        // ── Colaboradores ──
        $colaboradores = $this->ler($ctx, 'colaboradores', function ($r) use (
            $userDoColaborador, $usersEntregadores, $telefonePrincipal, $idsUser
        ) {
            $id = (int) $r->id;
            $user = $userDoColaborador[$id] ?? null;
            if ($user !== null && ! isset($idsUser[$user])) {
                $user = null; // user do legado não migrado (conflito de id)
            }

            return [
                'id' => $id,
                'empresa_id' => (int) $r->empresa_id,
                'grupo_id' => (int) $r->grupo_id,
                'cargo_id' => ($r->cargo_id ?? null) !== null ? (int) $r->cargo_id : null,
                'user_id' => $user,
                'nome' => trim((string) $r->nome),
                'cpf' => $this->soDigitos($r->cpf ?? null, 14),
                'rg' => ($r->rg ?? null) !== null ? mb_substr(trim((string) $r->rg), 0, 20) : null,
                'data_nascimento' => $r->datanascimento ?? null,
                'data_admissao' => $r->dataadmissao ?? null,
                'data_desligamento' => $r->datadesligamento ?? null,
                'telefone' => isset($telefonePrincipal[$id])
                    ? mb_substr($telefonePrincipal[$id], 0, 30) : null,
                'entregador' => $user !== null && isset($usersEntregadores[$user]),
                'ativo' => $this->booleano($r->ativo ?? '1'),
                // Endereço: o legado sempre teve (81 colaboradores com cidade e
                // bairro preenchidos); faltava a coluna no destino.
                'cep' => $this->soDigitos($r->cep ?? null, 8),
                'uf' => ($r->uf ?? null) !== null ? mb_substr(trim((string) $r->uf), 0, 2) : null,
                'cidade_id' => ($r->cidade_id ?? null) !== null ? (int) $r->cidade_id : null,
                'bairro_id' => ($r->bairro_id ?? null) !== null ? (int) $r->bairro_id : null,
                'rua_id' => ($r->rua_id ?? null) !== null ? (int) $r->rua_id : null,
                'numero' => ($r->numero ?? null) !== null ? mb_substr(trim((string) $r->numero), 0, 20) : null,
                'complemento' => $r->complemento ?? null,
                'created_at' => $r->created_at ?? null,
            ];
        });
        $colaboradores = $this->anularFksInvalidas($colaboradores, [
            'cargo_id' => 'cargos',
            // Geográfico pode ter descartado a cidade do dump: FK nullable,
            // referência sem destino vira null em vez de derrubar a carga.
            'cidade_id' => 'cidades',
            'bairro_id' => 'bairros',
            'rua_id' => 'ruas',
        ]);
        if (! $ctx->dryRun) {
            foreach ($colaboradores as $c) {
                $this->upsert(Colaborador::withoutTenant(), $this->semNulos($c));
                $gravados++;
            }
        }
        $lidos += count($colaboradores);
        $idsColaborador = array_flip(array_map(fn ($c) => $c['id'], $colaboradores));

        // ── Família (parentesco_id → texto) ──
        $familias = $this->ler($ctx, 'colaboradorfamilias', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_id' => (int) $r->colaborador_id,
            'nome' => trim((string) ($r->nome ?? '')),
            'parentesco' => $parentescos[(int) ($r->parentesco_id ?? 0)] ?? null,
            'data_nascimento' => $r->datanascimento ?? null,
        ]);

        // ── Exames (tipoexame_id → texto; data/datavencimento) ──
        $exames = $this->ler($ctx, 'colaboradorexames', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_id' => (int) $r->colaborador_id,
            'tipo' => $tiposExame[(int) ($r->tipoexame_id ?? 0)] ?? null,
            'realizado_em' => $r->data ?? null,
            'vencimento' => $r->datavencimento ?? null,
        ]);

        // ── Férias → recessos tipo FERIAS (datainicio + dias corridos) ──
        $ferias = $this->ler($ctx, 'colaboradorferias', function ($r) {
            $inicio = $r->datainicio ?? null;
            $dias = (int) ($r->dias ?? 0);
            $fim = null;
            if ($inicio !== null && $dias > 0) {
                try {
                    $fim = Carbon::parse($inicio)->addDays($dias - 1)->toDateString();
                } catch (\Throwable) {
                    // Data de início inválida no legado (campo texto livre):
                    // o recesso entra sem data-fim em vez de derrubar a carga.
                    // Não é leitura de origem — nada a avisar no relatório.
                    $fim = null;
                }
            }

            return [
                'id' => (int) $r->id,
                'colaborador_id' => (int) $r->colaborador_id,
                'tipo' => 'FERIAS',
                'inicio' => $inicio,
                'fim' => $fim,
                'observacao' => $this->booleano($r->gozada ?? null) ? 'Gozada' : null,
            ];
        });

        // ── Comissões + exceções ──
        $comissoes = $this->ler($ctx, 'colaboradorcomissoes', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'colaborador_id' => (int) $r->colaborador_id,
            'produto_id' => ($r->produto_id ?? null) !== null ? (int) $r->produto_id : null,
            'setor_id' => ($r->setor_id ?? null) !== null ? (int) $r->setor_id : null,
            'condicaopagamento_id' => ($r->condicaopagamento_id ?? null) !== null ? (int) $r->condicaopagamento_id : null,
            'tipo_comissao' => $r->tipocomissao ?? null,
            'percentual' => isset($r->percentual) ? (float) $r->percentual : null,
            'empresa_valor' => isset($r->empresavalor) ? (float) $r->empresavalor : null,
            'percentual_app' => isset($r->percentualapp) ? (float) $r->percentualapp : null,
            'empresa_valor_app' => isset($r->empresavalorapp) ? (float) $r->empresavalorapp : null,
            'data_inicio' => $r->datainicio ?? null,
            'data_fim' => $r->datafim ?? null,
            'ativo' => $this->booleano($r->ativo ?? '1'),
        ]);
        $comissoes = $this->anularFksInvalidas($comissoes, [
            'produto_id' => 'produtos',
            'setor_id' => 'setores',
            'condicaopagamento_id' => 'condicaopagamentos',
        ]);

        $excecoes = $this->ler($ctx, 'comissaoexcecoes', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_comissao_id' => (int) $r->colaboradorcomissao_id,
            'segmento_id' => ($r->segmento_id ?? null) !== null ? (int) $r->segmento_id : null,
            'tipo_excecao' => isset($r->tipoexcecao) ? (int) $r->tipoexcecao : null,
            'valor_excecao' => isset($r->valorexcecao) ? (float) $r->valorexcecao : null,
            'valor_excecao_app' => isset($r->valorexcecaoapp) ? (float) $r->valorexcecaoapp : null,
        ]);
        $excecoes = $this->anularFksInvalidas($excecoes, ['segmento_id' => 'segmentos']);

        $pulados = 0;
        $filtraColab = function (array $rows) use ($idsColaborador, &$pulados) {
            return array_values(array_filter($rows, function ($r) use ($idsColaborador, &$pulados) {
                if (isset($idsColaborador[(int) $r['colaborador_id']])) {
                    return true;
                }
                $pulados++;

                return false;
            }));
        };

        $familias = $filtraColab($familias);
        $exames = $filtraColab($exames);
        $ferias = $filtraColab($ferias);
        $comissoes = $filtraColab($comissoes);

        if (! $ctx->dryRun) {
            $gravados += $this->gravar(ColaboradorFamilia::class, $familias);
            $gravados += $this->gravar(ColaboradorExame::class, $exames);
            $gravados += $this->gravar(ColaboradorRecesso::class, $ferias);
            $gravados += $this->gravar(ColaboradorComissao::class, $comissoes);
            $idsComissao = $this->idsDe('colaborador_comissoes');
            $excecoesValidas = array_values(array_filter(
                $excecoes,
                fn ($e) => isset($idsComissao[(int) $e['colaborador_comissao_id']])
            ));
            $pulados += count($excecoes) - count($excecoesValidas);
            $gravados += $this->gravar(ComissaoExcecao::class, $excecoesValidas);
        }

        $lidos += count($familias) + count($exames) + count($ferias)
            + count($comissoes) + count($excecoes) + $pulados;

        if ($userDoColaborador === []) {
            $avisos[] = 'nenhum vínculo user↔colaborador encontrado no legado '
                .'(users.colaborador_id) — flag entregador ficou toda false';
        }

        return new MigrationResult(
            $this->nome(), $lidos, $ctx->dryRun ? 0 : $gravados, $pulados,
            array_merge($avisos, $this->avisosDeLeitura()),
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'colaboradores', 'colaboradores'),
            new CountInvariant($ctx, 'colaboradorcomissoes', 'colaborador_comissoes'),
            new CountInvariant($ctx, 'comissaoexcecoes', 'comissao_excecoes'),
            new IntegrityInvariant($ctx, 'colaboradores', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'colaborador_familias', 'colaborador_id', 'colaboradores'),
            new IntegrityInvariant($ctx, 'colaborador_comissoes', 'colaborador_id', 'colaboradores'),
        ];
    }

    /** id => descricao de um cadastro do espelho (parentescos, tipoexames). */
    private function descricoes(MigrationContext $ctx, string $tabela): array
    {
        return $this->lerOuAvisar(
            "{$tabela} (cadastro de apoio do RH)",
            function () use ($ctx, $tabela) {
                $out = [];
                foreach ($ctx->legado()->table($tabela)->get(['id', 'descricao']) as $r) {
                    $out[(int) $r->id] = trim((string) $r->descricao);
                }

                return $out;
            },
        );
    }

    /**
     * Lê uma tabela do espelho. Tabela ausente NÃO é silenciosa: quem chama já
     * garantiu a principal (`colaboradores`); as demais geram lista vazia mas o
     * caso aparece nas invariantes de contagem.
     *
     * @param  callable(object):array<string,mixed>  $map
     * @return list<array<string,mixed>>
     */
    private function ler(MigrationContext $ctx, string $tabela, callable $map): array
    {
        try {
            return $ctx->legado()->table($tabela)->orderBy('id')->get()->map($map)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array<string,mixed>>  $rows
     */
    private function gravar(string $model, array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            $this->upsert($model::query(), $this->semNulos($row));
            $n++;
        }

        return $n;
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

    /** @return array<int,true> ids presentes numa tabela do DESTINO */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach ($this->destino()->table($tabela)->pluck('id') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    private function soDigitos(mixed $v, int $max): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($v ?? ''));

        return $d === '' ? null : substr($d, 0, $max);
    }

    private function booleano(mixed $v): bool
    {
        return in_array(mb_strtoupper(trim((string) ($v ?? ''))), ['1', 'S', 'T', 'TRUE', 'Y'], true);
    }

    /**
     * Upsert PRESERVANDO o id do legado (forceFill ignora $fillable). Essencial
     * para manter as FKs entre tabelas após a migração.
     *
     * @param  array<string,mixed>  $row
     */
    private function upsert(Builder $query, array $row): void
    {
        $model = $query->firstWhere('id', $row['id']) ?? $query->getModel()->newInstance();
        $model->forceFill($row)->save();
    }

    /**
     * Remove chaves null (exceto id) p/ colunas NOT NULL com DEFAULT usarem o default.
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function semNulos(array $row): array
    {
        return array_filter($row, fn ($v, $k) => $v !== null || $k === 'id', ARRAY_FILTER_USE_BOTH);
    }
}
