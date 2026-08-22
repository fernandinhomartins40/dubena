<?php

namespace App\Domain\Rh;

use App\Domain\Auditoria\RegistroAcao;
use App\Models\Rh\Colaborador;
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
        $colaborador->familias()->whereKey($familiaId)->delete();
    }
}
