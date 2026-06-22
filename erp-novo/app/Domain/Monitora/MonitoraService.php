<?php

namespace App\Domain\Monitora;

use App\Models\Monitora\Cerca;
use App\Models\Monitora\Posicao;
use App\Models\Monitora\UltimaPosicao;
use App\Models\Monitora\Veiculo;
use Illuminate\Support\Facades\DB;

/**
 * MonitoraService (N11) — ingestão de posições + geofencing. Bounded context
 * isolado: registra a posição (append-only) e mantém o snapshot da última posição
 * numa transação. Geofencing por Haversine (centro+raio), sem PostGIS.
 */
class MonitoraService
{
    /**
     * Registra uma posição do veículo e atualiza o snapshot da última posição.
     *
     * @param array{latitude:float, longitude:float, velocidade?:float, direcao?:int, ignicao?:bool, registrado_em?:string} $dados
     */
    public function registrarPosicao(Veiculo $veiculo, array $dados): Posicao
    {
        $quando = $dados['registrado_em'] ?? now();

        return DB::transaction(function () use ($veiculo, $dados, $quando) {
            $posicao = $veiculo->posicoes()->create([
                'latitude' => $dados['latitude'],
                'longitude' => $dados['longitude'],
                'velocidade' => $dados['velocidade'] ?? 0,
                'direcao' => $dados['direcao'] ?? null,
                'ignicao' => $dados['ignicao'] ?? false,
                'registrado_em' => $quando,
            ]);

            UltimaPosicao::updateOrCreate(
                ['veiculo_id' => $veiculo->id],
                [
                    'latitude' => $dados['latitude'],
                    'longitude' => $dados['longitude'],
                    'velocidade' => $dados['velocidade'] ?? 0,
                    'ignicao' => $dados['ignicao'] ?? false,
                    'registrado_em' => $quando,
                ],
            );

            return $posicao;
        });
    }

    /** O ponto está dentro da cerca? */
    public function dentroDaCerca(Cerca $cerca, float $lat, float $lng): bool
    {
        $metros = $this->distanciaMetros((float) $cerca->centro_lat, (float) $cerca->centro_lng, $lat, $lng);

        return $metros <= (float) $cerca->raio_metros;
    }

    /**
     * Cercas (da empresa) que contêm o ponto informado.
     *
     * @return \Illuminate\Support\Collection<int,Cerca>
     */
    public function cercasNoPonto(int $empresaId, float $lat, float $lng)
    {
        return Cerca::query()->where('empresa_id', $empresaId)->where('ativo', true)->get()
            ->filter(fn (Cerca $c) => $this->dentroDaCerca($c, $lat, $lng))
            ->values();
    }

    /** Distância Haversine em METROS. */
    private function distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
