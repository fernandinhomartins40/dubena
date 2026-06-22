<?php

namespace App\Domain\Monitora;

use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Models\Monitora\Veiculo;

/**
 * MonitoraSyncService (N11 — GATE SGCasa). Busca posições no provedor externo
 * (via SgcasaDriver) e as ingere via MonitoraService. Roda como job agendado
 * (report:positions) em produção.
 */
class MonitoraSyncService
{
    public function __construct(
        private SgcasaDriver $sgcasa,
        private MonitoraService $monitora,
    ) {
    }

    /** Sincroniza posições dos veículos ativos de uma empresa. @return int posições ingeridas */
    public function sincronizar(int $empresaId): int
    {
        $veiculos = Veiculo::query()->where('empresa_id', $empresaId)->where('ativo', true)
            ->whereNotNull('imei')->get()->keyBy('imei');

        if ($veiculos->isEmpty()) {
            return 0;
        }

        $posicoes = $this->sgcasa->buscarPosicoes($veiculos->keys()->all());
        $ingeridas = 0;

        foreach ($posicoes as $p) {
            $veiculo = $veiculos->get($p['imei']);
            if (! $veiculo) {
                continue;
            }
            $this->monitora->registrarPosicao($veiculo, [
                'latitude' => $p['latitude'],
                'longitude' => $p['longitude'],
                'velocidade' => $p['velocidade'] ?? 0,
                'ignicao' => $p['ignicao'] ?? false,
                'registrado_em' => $p['registrado_em'] ?? now(),
            ]);
            $ingeridas++;
        }

        return $ingeridas;
    }
}
