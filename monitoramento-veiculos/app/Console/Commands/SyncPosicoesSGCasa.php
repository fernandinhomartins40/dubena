<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * FASE 4: substitui o módulo legado `integration/` (scripts procedurais em
 * PHP 4/5 com `mysql_*`, SQL Injection por concatenação, credenciais e mapa
 * de dispositivos hardcoded — que nem rodam em PHP 7+).
 *
 * Reescrito como Artisan Command usando o Query Builder do Laravel:
 *  - conexões via config/.env (sem credenciais no código);
 *  - bindings parametrizados (sem SQL Injection);
 *  - vínculo device→veículo lido do banco (sem array hardcoded);
 *  - inserção em lote + transação.
 *
 * Fluxo: lê posições pendentes no banco SGCasa (origem) → grava em `positions`
 * (monitora) → atualiza `ultimaposicaos` → marca como processadas na origem.
 */
class SyncPosicoesSGCasa extends Command
{
    protected $signature = 'sync:posicoes-sgcasa {--limit=500 : máximo de posições por execução}';

    protected $description = 'Sincroniza posições GPS pendentes do SGCasa para o monitoramento';

    /** Conexão de origem (banco SGCasa). */
    private $origem = 'sgcasa';

    public function handle()
    {
        $limit = (int) $this->option('limit');

        $pendentes = $this->buscarPendentes($limit);
        if ($pendentes->isEmpty()) {
            $this->info('Nenhuma posição pendente.');
            return 0;
        }

        // Mapa device(id_tca) → veículo, lido do banco (não hardcoded).
        $idsTca = $pendentes->pluck('id_tca')->unique()->filter()->values()->all();
        $veiculosPorTca = $this->mapaVeiculosPorTca($idsTca);

        $processados = [];
        DB::connection('mysql')->transaction(function () use ($pendentes, $veiculosPorTca, &$processados) {
            $linhas = [];
            $ultimas = [];

            foreach ($pendentes as $pos) {
                if (! isset($veiculosPorTca[$pos->id_tca])) {
                    continue; // device sem veículo vinculado — ignora
                }
                $veiculo = $veiculosPorTca[$pos->id_tca];
                $now = Carbon::now();

                $linhas[] = [
                    'latitude'   => $pos->latitude,
                    'longitude'  => $pos->longitude,
                    'altitude'   => $pos->altitude,
                    'course'     => $pos->azimute,
                    'speed'      => $pos->velocidade,
                    'deviceid'   => $veiculo->deviceid,
                    'dhposition' => $pos->data_hora,
                    'address'    => '',
                    'power'      => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Mantém só a última posição por veículo.
                $ultimas[$veiculo->id] = $pos;
                $processados[] = $pos->cd_posicionamento;
            }

            if (! empty($linhas)) {
                // Insere em blocos para não estourar limites do driver.
                foreach (array_chunk($linhas, 200) as $bloco) {
                    DB::connection('mysql')->table('positions')->insert($bloco);
                }
            }

            foreach ($ultimas as $veiculoId => $pos) {
                $this->atualizarUltimaPosicao($veiculoId, $pos);
            }
        });

        if (! empty($processados)) {
            $this->marcarProcessadas($processados);
        }

        $this->info('Posições sincronizadas: ' . count($processados));
        return 0;
    }

    private function buscarPendentes($limit)
    {
        return DB::connection($this->origem)
            ->table('posicionamento as a')
            ->join('veiculos as b', 'b.cd_veiculo', '=', 'a.cd_veiculo')
            ->where('a.processado', 0)
            ->whereNotNull('b.id_tca')
            ->orderBy('a.data_hora')
            ->limit($limit)
            ->get([
                'a.cd_posicionamento', 'a.latitude', 'a.longitude', 'a.altitude',
                'a.azimute', 'a.velocidade', 'a.data_hora', 'b.id_tca',
            ]);
    }

    private function mapaVeiculosPorTca(array $idsTca)
    {
        if (empty($idsTca)) {
            return [];
        }
        // Vínculo lido do banco do monitoramento (coluna deviceid ↔ id_tca da origem).
        $veiculos = DB::connection('mysql')
            ->table('veiculos')
            ->whereIn('deviceid', $idsTca)
            ->get(['id', 'deviceid', 'grupo_id', 'empresa_id']);

        $mapa = [];
        foreach ($veiculos as $v) {
            $mapa[$v->deviceid] = $v;
        }
        return $mapa;
    }

    private function atualizarUltimaPosicao($veiculoId, $pos)
    {
        DB::connection('mysql')->table('ultimaposicaos')
            ->where('veiculo_id', $veiculoId)
            ->update([
                'latitude'   => $pos->latitude,
                'longitude'  => $pos->longitude,
                'altitude'   => $pos->altitude,
                'azimute'    => $pos->azimute,
                'velocidade' => $pos->velocidade,
                'datahora'   => $pos->data_hora,
                'updated_at' => Carbon::now(),
            ]);
    }

    private function marcarProcessadas(array $ids)
    {
        // Bindings parametrizados via whereIn (sem concatenação).
        DB::connection($this->origem)
            ->table('posicionamento')
            ->whereIn('cd_posicionamento', $ids)
            ->update(['processado' => 1]);
    }
}
