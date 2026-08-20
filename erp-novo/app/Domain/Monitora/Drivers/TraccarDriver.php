<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Monitora\Contracts\SgcasaDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver de rastreamento Traccar — o provedor que a Dubena realmente usa.
 *
 * O servidor Traccar recebe as posições direto dos aparelhos GPS instalados nos
 * veículos e expõe uma API REST. O legado (`ctrl-web/app/Monitora`) consome esse
 * mesmo servidor; as credenciais vieram da tabela `configs` do dump `monitora`.
 *
 * **Por que polling e não webhook.** O Traccar até sabe empurrar posição por
 * HTTP — é como ele alimenta o legado hoje. Mas mudar essa configuração mexeria
 * num servidor compartilhado com o sistema em produção, e um erro ali cega a
 * frota inteira. Consultando `/api/positions` (uma chamada devolve a posição
 * atual de todos os aparelhos) nada precisa ser alterado do lado do Traccar, e
 * o legado segue recebendo normalmente até o cutover.
 *
 * A ligação entre os dois mundos é o **IMEI**: `monitora_veiculos.imei` no ERP é
 * o mesmo valor de `uniqueId` no Traccar. Isso já era verdade nos dados
 * migrados — não foi preciso inventar mapeamento.
 */
class TraccarDriver implements SgcasaDriver
{
    /** Nós → km/h. O Traccar reporta velocidade náutica; o legado fazia o mesmo. */
    private const NOS_PARA_KMH = 1.852;

    /**
     * Acima disto a posição é considerada relógio errado do aparelho.
     *
     * O histórico migrado tem 8 posições em 2080 — GPS com data furada existe, e
     * gravá-las estragaria qualquer consulta por período.
     */
    private const TOLERANCIA_FUTURO_MINUTOS = 60;

    /**
     * @param  list<string>  $imeis
     * @return list<array{imei:string, latitude:float, longitude:float, velocidade:float, direcao:int, ignicao:bool, registrado_em:string}>
     */
    public function buscarPosicoes(array $imeis): array
    {
        if ($imeis === []) {
            return [];
        }

        $url = rtrim((string) config('services.traccar.url'), '/');
        $usuario = (string) config('services.traccar.usuario');
        $senha = (string) config('services.traccar.senha');

        if ($url === '' || $usuario === '') {
            return [];
        }

        try {
            // Duas chamadas: `devices` dá o uniqueId (o IMEI), `positions` dá a
            // posição. A API não devolve as duas coisas juntas, e é o deviceId
            // numérico que liga uma à outra.
            $dispositivos = Http::timeout(20)->withBasicAuth($usuario, $senha)
                ->acceptJson()->get("{$url}/api/devices");
            $posicoes = Http::timeout(20)->withBasicAuth($usuario, $senha)
                ->acceptJson()->get("{$url}/api/positions");

            if (! $dispositivos->successful() || ! $posicoes->successful()) {
                Log::warning('Traccar respondeu com erro', [
                    'devices' => $dispositivos->status(),
                    'positions' => $posicoes->status(),
                ]);

                return [];
            }

            $imeiPorDevice = [];
            foreach ($dispositivos->json() ?? [] as $d) {
                $imeiPorDevice[(int) ($d['id'] ?? 0)] = (string) ($d['uniqueId'] ?? '');
            }

            $procurados = array_flip($imeis);
            $limite = now()->addMinutes(self::TOLERANCIA_FUTURO_MINUTOS);
            $saida = [];

            foreach ($posicoes->json() ?? [] as $p) {
                $imei = $imeiPorDevice[(int) ($p['deviceId'] ?? 0)] ?? '';
                if ($imei === '' || ! isset($procurados[$imei])) {
                    continue;
                }

                // `valid: false` é posição que o próprio aparelho marcou como sem
                // fix de satélite — costuma vir com coordenada do último ponto
                // bom, o que faria o veículo "teleportar" no mapa.
                if (isset($p['valid']) && $p['valid'] === false) {
                    continue;
                }

                $quando = $this->momento($p);
                if ($quando === null || $quando->greaterThan($limite)) {
                    continue;
                }

                $saida[] = [
                    'imei' => $imei,
                    'latitude' => (float) ($p['latitude'] ?? 0),
                    'longitude' => (float) ($p['longitude'] ?? 0),
                    'velocidade' => round(((float) ($p['speed'] ?? 0)) * self::NOS_PARA_KMH, 2),
                    // `course` é o azimute em graus: é o que gira o ícone no
                    // mapa para o sentido em que o veículo segue. O `+ 360` e o
                    // módulo normalizam ângulo negativo que alguns aparelhos
                    // reportam — a coluna é unsigned e recusaria o valor.
                    'direcao' => ((int) round((float) ($p['course'] ?? 0)) % 360 + 360) % 360,
                    'ignicao' => $this->ignicao($p),
                    'registrado_em' => $quando->toDateTimeString(),
                ];
            }

            return $saida;
        } catch (\Throwable $e) {
            // Falha de rede não pode derrubar o job: na próxima rodada tenta de
            // novo, e o mapa apenas continua mostrando a última posição boa.
            Log::warning('Falha ao consultar o Traccar: '.$e->getMessage());

            return [];
        }
    }

    /** @return list<array{imei:string, nome:string}> */
    public function listarAparelhos(): array
    {
        $url = rtrim((string) config('services.traccar.url'), '/');
        $usuario = (string) config('services.traccar.usuario');
        $senha = (string) config('services.traccar.senha');

        if ($url === '' || $usuario === '') {
            return [];
        }

        try {
            $resp = Http::timeout(20)->withBasicAuth($usuario, $senha)
                ->acceptJson()->get("{$url}/api/devices");

            if (! $resp->successful()) {
                return [];
            }

            $saida = [];
            foreach ($resp->json() ?? [] as $d) {
                $imei = (string) ($d['uniqueId'] ?? '');
                if ($imei === '') {
                    continue;
                }
                // Aparelho desativado no Traccar foi retirado de circulação:
                // cadastrar veículo para ele só sujaria a frota.
                if (($d['disabled'] ?? false) === true) {
                    continue;
                }
                $saida[] = ['imei' => $imei, 'nome' => (string) ($d['name'] ?? $imei)];
            }

            return $saida;
        } catch (\Throwable $e) {
            Log::warning('Falha ao listar aparelhos no Traccar: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Momento do fix, preferindo o relógio do aparelho.
     *
     * `fixTime` é quando o GPS obteve a posição; `serverTime` é quando o
     * servidor a recebeu. Numa área sem sinal o aparelho guarda e envia depois —
     * usar o horário do servidor colocaria no mapa um trajeto que não aconteceu
     * naquela hora.
     */
    private function momento(array $p): ?\Illuminate\Support\Carbon
    {
        foreach (['fixTime', 'deviceTime', 'serverTime'] as $campo) {
            if (! empty($p[$campo])) {
                try {
                    return \Illuminate\Support\Carbon::parse($p[$campo]);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Ignição ligada.
     *
     * Nem todo rastreador reporta `ignition` — os que são aplicativo de celular
     * (protocolo osmand, que a frota usa em parte) não têm como saber. Nesses,
     * `motion` do próprio Traccar é o melhor sinal disponível, e movimento
     * implica motor ligado.
     */
    private function ignicao(array $p): bool
    {
        $attr = $p['attributes'] ?? [];

        if (array_key_exists('ignition', $attr)) {
            return (bool) $attr['ignition'];
        }
        if (array_key_exists('motion', $attr)) {
            return (bool) $attr['motion'];
        }

        return ((float) ($p['speed'] ?? 0)) > 0;
    }
}
