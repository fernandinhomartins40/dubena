<?php

namespace App\Domain\Rh;

use App\Domain\Auditoria\RegistroAcao;
use App\Models\EmpresaConfig;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorPonto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ColaboradorService (C5) — CRUD do colaborador + sub-relações (família).
 * Regra fica aqui; o controller só valida e serializa.
 */
class ColaboradorService
{
    /** @param array<string,mixed> $dados */
    public function criar(array $dados): Colaborador
    {
        return Colaborador::create($dados);
    }

    /** @param array<string,mixed> $dados */
    public function atualizar(Colaborador $colaborador, array $dados): Colaborador
    {
        $colaborador->update($dados);

        return $colaborador->refresh();
    }

    /**
     * "Excluir" um colaborador é DESATIVÁ-LO, nunca apagá-lo.
     *
     * O delete físico anterior não tinha nem a proteção que o cliente tinha:
     * TODAS as sub-tabelas de RH (família, recessos, comissões, exames, turnos,
     * ponto) são cascadeOnDelete, então apagar o colaborador destruía junto o
     * histórico trabalhista dele — sem nenhuma FK para segurar a operação.
     *
     * Desligamento e desativação são coisas diferentes e ambas ficam: a data de
     * desligamento é fato trabalhista; a desativação é o cadastro sair da lista.
     *
     * @throws ValidationException se já estiver desativado
     */
    public function desativar(Colaborador $colaborador, ?string $motivo = null, ?int $usuarioId = null): Colaborador
    {
        if ($colaborador->ativo === false) {
            throw ValidationException::withMessages([
                'colaborador' => 'Este colaborador já está desativado.',
            ]);
        }

        $colaborador->forceFill([
            'ativo' => false,
            'desativado_em' => now(),
            'desativado_por' => $usuarioId,
            'motivo_desativacao' => $motivo,
        ])->save();

        app(RegistroAcao::class)->registrar($colaborador, 'desativou', $motivo);

        return $colaborador->refresh();
    }

    public function reativar(Colaborador $colaborador): Colaborador
    {
        $motivoAnterior = $colaborador->motivo_desativacao;

        $colaborador->forceFill([
            'ativo' => true,
            'desativado_em' => null,
            'desativado_por' => null,
            'motivo_desativacao' => null,
        ])->save();

        app(RegistroAcao::class)->registrar($colaborador, 'reativou', null, [
            'desativado_antes_por_motivo' => $motivoAnterior,
        ]);

        return $colaborador->refresh();
    }

    /** @param array<string,mixed> $dados */
    public function adicionarFamiliar(Colaborador $colaborador, array $dados): void
    {
        DB::transaction(fn () => $colaborador->familias()->create($dados));
    }

    public function removerFamiliar(Colaborador $colaborador, int $familiaId): void
    {
        // Pelo model, e não `whereKey()->delete()` em massa: a exclusão em massa
        // não dispara o evento `deleted`, e o familiar sumia sem trilha.
        $colaborador->familias()->findOrFail($familiaId)->delete();
    }

    /**
     * Ponto do dia (C5), com trava para dia fechado — PIE P2-E-11.
     *
     * Antes, `updateOrCreate` por data reescrevia o ponto de QUALQUER dia
     * passado, sem rastro: horas da folha mudavam depois do fechamento e
     * ninguém sabia quem mexeu. Agora:
     *  - dentro da janela livre (hoje e os `ponto_dias_edicao_livre` dias
     *    anteriores, configuração da EMPRESA em `empresa_configs.dados.rh`,
     *    padrão 0 = só hoje) o registro é livre, como era;
     *  - fora dela, criar ou alterar exige `motivo`, que fica gravado na linha
     *    e, com o model auditável, entra no antes/depois de `audit_logs`.
     * A janela é configuração do tenant, não constante: cada revenda fecha o
     * ponto num ritmo (diário, semanal, na folha).
     *
     * @param  array{data?: string|null, entrada?: string|null, saida?: string|null, motivo?: string|null}  $d
     */
    public function registrarPonto(Colaborador $colaborador, array $d): ColaboradorPonto
    {
        $data = Carbon::parse($d['data'] ?? now()->toDateString())->startOfDay();
        $hoje = now()->startOfDay();
        if ($data->gt($hoje)) {
            throw ValidationException::withMessages(['data' => 'Não é possível registrar ponto de um dia futuro.']);
        }

        $janela = $this->diasDeEdicaoLivre((int) $colaborador->empresa_id);
        $fechado = $data->lt($hoje->copy()->subDays($janela));
        $motivo = trim((string) ($d['motivo'] ?? ''));
        if ($fechado && $motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'O ponto deste dia já está fechado. Informe o motivo do ajuste.',
            ]);
        }

        $valores = array_intersect_key($d, array_flip(['entrada', 'saida']));
        $valores['motivo_ajuste'] = $fechado ? $motivo : null;

        return DB::transaction(function () use ($colaborador, $data, $valores) {
            // `whereDate` e não `where('data', ...)`: o cast `date` grava com
            // hora no sqlite (armadilha do CLAUDE.md) e o upsert duplicaria.
            $ponto = $colaborador->pontos()->whereDate('data', $data->toDateString())->lockForUpdate()->first()
                ?? $colaborador->pontos()->make(['data' => $data->toDateString()]);
            $ponto->fill($valores)->save();

            return $ponto->refresh();
        });
    }

    private function diasDeEdicaoLivre(int $empresaId): int
    {
        $dados = EmpresaConfig::query()->where('empresa_id', $empresaId)->value('dados');
        $dados = is_string($dados) ? json_decode($dados, true) : $dados;
        $dias = $dados['rh']['ponto_dias_edicao_livre'] ?? 0;

        return max(0, min(31, (int) $dias));
    }
}
