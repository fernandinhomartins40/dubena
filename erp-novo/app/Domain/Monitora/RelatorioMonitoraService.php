<?php

namespace App\Domain\Monitora;

use App\Models\Monitora\Veiculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * RelatorioMonitoraService (F2) — porta o ReportController@reportEventosVeiculo do
 * legado (app/Monitora): a partir do histórico de posições de um veículo num período,
 * apura PARADAS (tempo parado acima de um limiar) e EXCESSOS de velocidade (acima da
 * velocidade-máxima do tipo do veículo). Sem Traccar — usa as posições já ingeridas.
 */
class RelatorioMonitoraService
{
    /** Limiar de parada (segundos) — igual ao legado (>300s = 5min). */
    private const PARADA_MINIMA_SEGUNDOS = 300;

    /** Velocidade (km/h) abaixo da qual o veículo é considerado parado. */
    private const VELOCIDADE_PARADO = 1.0;

    /**
     * Apura eventos de um veículo no período.
     *
     * @return array{
     *   veiculo: array{id:int, placa:string, descricao:?string, tipo:?string, velocidade_maxima:?int},
     *   paradas: list<array<string,mixed>>,
     *   excessos: list<array<string,mixed>>,
     *   resumo: array{total_paradas:int, total_excessos:int, posicoes:int}
     * }
     */
    public function eventosVeiculo(Veiculo $veiculo, string $inicio, string $fim): array
    {
        $dtInicio = Carbon::parse($inicio)->startOfDay();
        $dtFim = Carbon::parse($fim)->endOfDay();

        $posicoes = $veiculo->posicoes()
            ->whereBetween('registrado_em', [$dtInicio, $dtFim])
            ->orderBy('registrado_em')
            ->get(['latitude', 'longitude', 'velocidade', 'registrado_em']);

        $velMax = $veiculo->tipo?->velocidade_maxima;

        return [
            'veiculo' => [
                'id' => $veiculo->id,
                'placa' => $veiculo->placa,
                'descricao' => $veiculo->descricao,
                'tipo' => $veiculo->tipo?->descricao,
                'velocidade_maxima' => $velMax,
            ],
            'paradas' => $this->paradas($posicoes),
            'excessos' => $this->excessos($posicoes, $velMax),
            'resumo' => [
                'total_paradas' => count($this->paradas($posicoes)),
                'total_excessos' => count($this->excessos($posicoes, $velMax)),
                'posicoes' => $posicoes->count(),
            ],
        ];
    }

    /**
     * Linhas "achatadas" (paradas + excessos) para exportação CSV/PDF.
     *
     * @return list<array<string,mixed>>
     */
    public function linhasEventos(Veiculo $veiculo, string $inicio, string $fim): array
    {
        $ev = $this->eventosVeiculo($veiculo, $inicio, $fim);
        $linhas = [];

        foreach ($ev['paradas'] as $p) {
            $linhas[] = [
                'evento' => 'Parada',
                'inicio' => $p['inicio'],
                'fim' => $p['fim'],
                'duracao_min' => $p['duracao_min'],
                'velocidade' => '',
                'latitude' => $p['latitude'],
                'longitude' => $p['longitude'],
            ];
        }
        foreach ($ev['excessos'] as $e) {
            $linhas[] = [
                'evento' => 'Excesso de velocidade',
                'inicio' => $e['registrado_em'],
                'fim' => $e['registrado_em'],
                'duracao_min' => '',
                'velocidade' => $e['velocidade'],
                'latitude' => $e['latitude'],
                'longitude' => $e['longitude'],
            ];
        }

        return $linhas;
    }

    /**
     * Paradas: sequências consecutivas de posições com velocidade < limiar cujo tempo
     * acumulado (do início ao fim da sequência) supera PARADA_MINIMA_SEGUNDOS.
     *
     * @param  Collection<int,Model>  $posicoes
     * @return list<array<string,mixed>>
     */
    private function paradas(Collection $posicoes): array
    {
        $paradas = [];
        $n = $posicoes->count();
        $i = 0;

        while ($i < $n) {
            if ((float) $posicoes[$i]->velocidade >= self::VELOCIDADE_PARADO) {
                $i++;

                continue;
            }

            $inicio = $i;
            while ($i + 1 < $n && (float) $posicoes[$i + 1]->velocidade < self::VELOCIDADE_PARADO) {
                $i++;
            }

            $dtIni = Carbon::parse($posicoes[$inicio]->registrado_em);
            $dtFim = Carbon::parse($posicoes[$i]->registrado_em);
            $segundos = $dtIni->diffInSeconds($dtFim);

            if ($segundos > self::PARADA_MINIMA_SEGUNDOS) {
                $paradas[] = [
                    'inicio' => $dtIni->toIso8601String(),
                    'fim' => $dtFim->toIso8601String(),
                    'duracao_min' => round($segundos / 60, 1),
                    'latitude' => (float) $posicoes[$inicio]->latitude,
                    'longitude' => (float) $posicoes[$inicio]->longitude,
                ];
            }
            $i++;
        }

        return $paradas;
    }

    /**
     * Excessos: posições com velocidade acima da máxima do tipo. Sem tipo/limite
     * definido, não há como apurar excesso → lista vazia.
     *
     * @param  Collection<int,Model>  $posicoes
     * @return list<array<string,mixed>>
     */
    private function excessos(Collection $posicoes, ?int $velocidadeMaxima): array
    {
        if ($velocidadeMaxima === null || $velocidadeMaxima <= 0) {
            return [];
        }

        return $posicoes
            ->filter(fn ($p) => (float) $p->velocidade > $velocidadeMaxima)
            ->map(fn ($p) => [
                'registrado_em' => Carbon::parse($p->registrado_em)->toIso8601String(),
                'velocidade' => (float) $p->velocidade,
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
            ])
            ->values()
            ->all();
    }
}
