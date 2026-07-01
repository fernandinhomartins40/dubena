<?php

namespace App\Domain\Logistica;

use App\Models\Logistica\Jornada;
use App\Models\Monitora\Veiculo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * JornadaService (L0) — abre/fecha o turno do entregador e é a fonte de verdade do
 * "quem está em campo agora". Regra central (backend): 1 jornada ativa por
 * entregador; o veículo escolhido tem de ser da MESMA empresa e estar ativo.
 *
 * A distribuição (L1/L3) consulta `jornadaAtiva()` para saber quais entregadores
 * estão disponíveis e em qual veículo (capacidade), e cruza com a última posição
 * do celular (EntregadorPosicao) para proximidade.
 */
class JornadaService
{
    /**
     * Inicia a jornada do entregador com o veículo e o checklist.
     *
     * @param  array<string,mixed>|null  $checklist
     */
    public function iniciar(User $entregador, ?int $veiculoId, ?array $checklist = null, ?int $kmInicial = null): Jornada
    {
        if ($this->jornadaAtiva($entregador->id) !== null) {
            throw ValidationException::withMessages(['jornada' => 'Você já tem uma jornada em andamento.']);
        }

        $veiculo = $this->resolverVeiculo($entregador, $veiculoId);

        return DB::transaction(fn () => Jornada::create([
            'empresa_id' => $entregador->empresa_id,
            'grupo_id' => $entregador->grupo_id,
            'entregador_user_id' => $entregador->id,
            'veiculo_id' => $veiculo?->id,
            'iniciada_em' => now(),
            'km_inicial' => $kmInicial ?? $veiculo?->km_atual,
            'checklist' => $checklist,
            'status' => 'ativa',
        ]));
    }

    /** Encerra a jornada (km final opcional; atualiza o hodômetro do veículo). */
    public function encerrar(Jornada $jornada, ?int $kmFinal = null): Jornada
    {
        if (! $jornada->estaAtiva()) {
            throw ValidationException::withMessages(['jornada' => 'Jornada já encerrada.']);
        }

        return DB::transaction(function () use ($jornada, $kmFinal) {
            $jornada->forceFill([
                'encerrada_em' => now(),
                'km_final' => $kmFinal,
                'status' => 'encerrada',
            ])->save();

            // Sincroniza o hodômetro do veículo (não regride).
            if ($kmFinal !== null && $jornada->veiculo_id) {
                Veiculo::query()->whereKey($jornada->veiculo_id)
                    ->where(fn ($q) => $q->whereNull('km_atual')->orWhere('km_atual', '<', $kmFinal))
                    ->update(['km_atual' => $kmFinal]);
            }

            return $jornada->refresh();
        });
    }

    /** A jornada ATIVA do entregador (ou null). */
    public function jornadaAtiva(int $entregadorUserId): ?Jornada
    {
        return Jornada::query()
            ->where('entregador_user_id', $entregadorUserId)
            ->where('status', 'ativa')
            ->latest('iniciada_em')
            ->first();
    }

    /** Exige jornada ativa (usado por posição/status no app do entregador). */
    public function exigirJornadaAtiva(int $entregadorUserId): Jornada
    {
        $jornada = $this->jornadaAtiva($entregadorUserId);
        if ($jornada === null) {
            throw ValidationException::withMessages(['jornada' => 'Inicie a jornada para operar.']);
        }

        return $jornada;
    }

    /** Valida e devolve o veículo (mesma empresa, ativo). Null se não informado. */
    private function resolverVeiculo(User $entregador, ?int $veiculoId): ?Veiculo
    {
        if ($veiculoId === null) {
            return null;
        }

        $veiculo = Veiculo::query()
            ->where('empresa_id', $entregador->empresa_id)
            ->where('id', $veiculoId)
            ->first();

        if ($veiculo === null) {
            throw ValidationException::withMessages(['veiculo_id' => 'Veículo inválido para esta empresa.']);
        }
        if (! $veiculo->ativo) {
            throw ValidationException::withMessages(['veiculo_id' => 'Veículo inativo.']);
        }

        return $veiculo;
    }
}
