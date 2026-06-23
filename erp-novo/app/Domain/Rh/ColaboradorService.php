<?php

namespace App\Domain\Rh;

use App\Models\Rh\Colaborador;
use Illuminate\Support\Facades\DB;

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

    public function excluir(Colaborador $colaborador): void
    {
        $colaborador->delete();
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
