<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorComissao;
use App\Models\Rh\ColaboradorExame;
use App\Models\Rh\ColaboradorFamilia;
use App\Models\Rh\ColaboradorPonto;
use App\Models\Rh\ColaboradorRecesso;
use App\Models\Rh\ColaboradorTurno;
use App\Models\Rh\ComissaoExcecao;

/**
 * F15 — migra RH: colaboradores + sub-tabelas (família, exames, turnos, pontos,
 * recessos, comissões e exceções). Conversões legado→novo: nomes de coluna
 * snake-sem-underscore → snake; flags Oracle '0'/'1' → boolean. Filhas herdam
 * empresa_id do pai (trait BelongsToTenant + $tenantParent, F02).
 */
final class RhMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'rh';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'cadastros-apoio'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        $colaboradores = $this->lerColaboradores($ctx);
        $familias = $this->ler($ctx, 'colaboradorfamilias', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_id' => (int) $r->colaborador_id,
            'nome' => trim((string) ($r->nome ?? '')),
            'parentesco' => $r->parentesco ?? null,
            'data_nascimento' => $r->datanascimento ?? null,
        ]);
        $exames = $this->ler($ctx, 'colaboradorexames', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_id' => (int) $r->colaborador_id,
            'tipo' => $r->tipo ?? null,
            'realizado_em' => $r->realizadoem ?? ($r->datarealizacao ?? null),
            'vencimento' => $r->vencimento ?? null,
            'resultado' => $r->resultado ?? null,
            'medico' => $r->medico ?? null,
            'observacao' => $r->observacao ?? null,
        ]);
        $turnos = $this->ler($ctx, 'colaboradorturnos', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_id' => (int) $r->colaborador_id,
            'dia_semana' => isset($r->diasemana) ? (int) $r->diasemana : null,
            'entrada' => $r->entrada ?? null,
            'saida' => $r->saida ?? null,
        ]);
        $pontos = $this->ler($ctx, 'colaboradorpontos', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_id' => (int) $r->colaborador_id,
            'data' => $r->data ?? null,
            'entrada' => $r->entrada ?? null,
            'saida' => $r->saida ?? null,
        ]);
        $recessos = $this->ler($ctx, 'colaboradorrecessos', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_id' => (int) $r->colaborador_id,
            'tipo' => $r->tipo ?? null,
            'inicio' => $r->inicio ?? ($r->datainicio ?? null),
            'fim' => $r->fim ?? ($r->datafim ?? null),
            'observacao' => $r->observacao ?? null,
        ]);
        $comissoes = $this->ler($ctx, 'colaboradorcomissoes', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'colaborador_id' => (int) $r->colaborador_id,
            'produto_id' => $r->produto_id ?? null,
            'setor_id' => $r->setor_id ?? null,
            'condicaopagamento_id' => $r->condicaopagamento_id ?? null,
            'tipo_comissao' => $r->tipocomissao ?? null,
            'percentual' => isset($r->percentual) ? (float) $r->percentual : null,
            'empresa_valor' => isset($r->empresavalor) ? (float) $r->empresavalor : null,
            'percentual_app' => isset($r->percentualapp) ? (float) $r->percentualapp : null,
            'empresa_valor_app' => isset($r->empresavalorapp) ? (float) $r->empresavalorapp : null,
            'data_inicio' => $r->datainicio ?? null,
            'data_fim' => $r->datafim ?? null,
            'ativo' => (bool) ($r->ativo ?? true),
        ]);
        $excecoes = $this->ler($ctx, 'comissaoexcecoes', fn ($r) => [
            'id' => (int) $r->id,
            'colaborador_comissao_id' => (int) $r->colaboradorcomissao_id,
            'segmento_id' => $r->segmento_id ?? null,
            'tipo_excecao' => isset($r->tipoexcecao) ? (int) $r->tipoexcecao : null,
            'valor_excecao' => isset($r->valorexcecao) ? (float) $r->valorexcecao : null,
            'valor_excecao_app' => isset($r->valorexcecaoapp) ? (float) $r->valorexcecaoapp : null,
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            foreach ($colaboradores as $c) {
                $this->upsert(Colaborador::withoutTenant(), $this->semNulos($c));
                $gravados++;
            }
            $gravados += $this->gravar(ColaboradorFamilia::class, $familias);
            $gravados += $this->gravar(ColaboradorExame::class, $exames);
            $gravados += $this->gravar(ColaboradorTurno::class, $turnos);
            $gravados += $this->gravar(ColaboradorPonto::class, $pontos);
            $gravados += $this->gravar(ColaboradorRecesso::class, $recessos);
            $gravados += $this->gravar(ColaboradorComissao::class, $comissoes);
            $gravados += $this->gravar(ComissaoExcecao::class, $excecoes);
        }

        $lidos = count($colaboradores) + count($familias) + count($exames) + count($turnos)
            + count($pontos) + count($recessos) + count($comissoes) + count($excecoes);

        return new MigrationResult($this->nome(), $lidos, $ctx->dryRun ? 0 : $gravados, 0);
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext();
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            new CountInvariant($ctx, 'colaboradores', 'colaboradores'),
            new IntegrityInvariant($ctx, 'colaboradores', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'colaborador_familias', 'colaborador_id', 'colaboradores'),
            new IntegrityInvariant($ctx, 'colaborador_comissoes', 'colaborador_id', 'colaboradores'),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function lerColaboradores(MigrationContext $ctx): array
    {
        return $this->ler($ctx, 'colaboradores', fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'cargo_id' => $r->cargo_id ?? null,
            'user_id' => $r->user_id ?? null,
            'nome' => trim((string) $r->nome),
            'cpf' => $r->cpf ?? null,
            'rg' => $r->rg ?? null,
            'data_nascimento' => $r->datanascimento ?? null,
            'data_admissao' => $r->dataadmissao ?? null,
            'data_desligamento' => $r->datadesligamento ?? null,
            'telefone' => $r->telefone ?? null,
            'entregador' => (bool) ($r->entregador ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
        ]);
    }

    /**
     * Lê uma tabela legado e mapeia cada linha. Tolerante a tabela ausente.
     *
     * @param  callable(object):array<string,mixed>  $map
     * @return list<array<string,mixed>>
     */
    private function ler(MigrationContext $ctx, string $tabela, callable $map): array
    {
        try {
            return $ctx->legado()->table($tabela)->get()->map($map)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
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

    /**
     * Upsert PRESERVANDO o id do legado (forceFill ignora $fillable). Essencial
     * para manter as FKs entre tabelas após a migração.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string,mixed>  $row
     */
    private function upsert(\Illuminate\Database\Eloquent\Builder $query, array $row): void
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
