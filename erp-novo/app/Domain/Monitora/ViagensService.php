<?php

namespace App\Domain\Monitora;

use App\Models\Monitora\Veiculo;
use App\Models\Monitora\ViagemCache;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Segmenta o histórico de posições em VIAGENS (trechos entre paradas).
 *
 * A tela de rota mostrava um emaranhado de linhas do período inteiro. Um dia de
 * entrega tem 300+ posições e passa pelas mesmas ruas várias vezes: o desenho
 * resultante não dizia para onde o veículo foi nem quando. Aqui o dia vira uma
 * lista de trechos — saiu às 08:12 da base, chegou às 08:31 no bairro tal — e
 * cada um pode ser desenhado sozinho.
 *
 * **O traçado NÃO passa pelo Google.** As posições vêm a cada ~30 s, então
 * ligá-las em sequência já acompanha as ruas de perto. Usar a Roads API para
 * "grudar" a linha no asfalto custaria por 100 pontos — e são 16 milhões de
 * posições históricas. O ganho visual não paga a conta.
 *
 * O resultado é gravado em `monitora_viagens_cache`: apurar viagens varre todas
 * as posições do dia, e a mesma consulta repetida (é a tela que o dono deixa
 * aberta) não pode pagar isso de novo.
 */
class ViagensService
{
    /**
     * Parada que encerra uma viagem (segundos).
     *
     * Mesmo limiar do relatório de paradas, herdado do legado: uma entrega
     * típica passa de 5 min, e o dia se divide naturalmente entre elas.
     */
    private const PARADA_MINIMA_SEGUNDOS = 300;

    /** Velocidade (km/h) abaixo da qual o veículo é considerado parado. */
    private const VELOCIDADE_PARADO = 1.0;

    /**
     * Viagem com menos que isto é ruído de GPS parado, não deslocamento.
     *
     * Rastreador em veículo estacionado oscila alguns metros e produziria
     * dezenas de "viagens" de 20 segundos poluindo a lista.
     */
    private const DISTANCIA_MINIMA_KM = 0.3;

    /** Raio médio da Terra em km — para a distância percorrida. */
    private const RAIO_TERRA_KM = 6371.0088;

    /**
     * Viagens de um veículo no período.
     *
     * @return array{viagens: list<array<string,mixed>>, resumo: array<string,mixed>}
     */
    public function doVeiculo(Veiculo $veiculo, string $de, string $ate): array
    {
        $inicio = Carbon::parse($de)->startOfDay();
        $fim = Carbon::parse($ate)->endOfDay();

        // `whereDate` e não `where`: o sqlite guarda a coluna `date` como
        // datetime completo, e a comparação de string não casaria — o cache
        // nunca daria hit, silenciosamente.
        $cache = ViagemCache::query()
            ->where('veiculo_id', $veiculo->id)
            ->whereDate('de', $inicio->toDateString())
            ->whereDate('ate', $fim->toDateString())
            ->first();

        // Período que inclui HOJE não é reaproveitado: o veículo ainda está
        // rodando, e servir o cache congelaria o trajeto no meio do dia.
        $encerrado = $fim->lessThan(now()->startOfDay());

        if ($cache && $encerrado) {
            $cache->increment('hits');

            return $cache->conteudo;
        }

        $posicoes = $veiculo->posicoes()
            ->whereBetween('registrado_em', [$inicio, $fim])
            ->orderBy('registrado_em')
            ->get(['latitude', 'longitude', 'velocidade', 'ignicao', 'registrado_em']);

        $viagens = $this->segmentar($posicoes);
        $saida = [
            'viagens' => $viagens,
            'resumo' => [
                'total' => count($viagens),
                'distancia_km' => round(array_sum(array_column($viagens, 'distancia_km')), 1),
                'duracao_min' => (int) array_sum(array_column($viagens, 'duracao_min')),
                'posicoes' => $posicoes->count(),
            ],
        ];

        if ($encerrado) {
            // `empresa_id` explícito: o serviço também roda fora de requisição
            // HTTP (comando, job), onde não há tenant no contexto para herdar.
            ViagemCache::query()->updateOrCreate(
                [
                    'veiculo_id' => $veiculo->id,
                    'de' => $inicio->toDateString(),
                    'ate' => $fim->toDateString(),
                ],
                ['empresa_id' => $veiculo->empresa_id, 'conteudo' => $saida, 'hits' => 0],
            );
        }

        return $saida;
    }

    /**
     * Corta a sequência de posições nos pontos de parada longa.
     *
     * @param  Collection<int,\Illuminate\Database\Eloquent\Model>  $posicoes
     * @return list<array<string,mixed>>
     */
    private function segmentar(Collection $posicoes): array
    {
        $n = $posicoes->count();
        if ($n < 2) {
            return [];
        }

        $viagens = [];
        $trecho = [];

        for ($i = 0; $i < $n; $i++) {
            $trecho[] = $posicoes[$i];

            if ($i + 1 >= $n) {
                continue;
            }

            // O corte é o INTERVALO entre duas posições consecutivas, e não a
            // velocidade zero: rastreador desligado ou fora de área também
            // deixa um buraco no tempo, e emendar os dois lados desenharia uma
            // reta atravessando a cidade por onde o veículo nunca passou.
            $atual = Carbon::parse($posicoes[$i]->registrado_em);
            $proxima = Carbon::parse($posicoes[$i + 1]->registrado_em);
            $intervalo = $atual->diffInSeconds($proxima);

            $paradoAgora = (float) $posicoes[$i]->velocidade < self::VELOCIDADE_PARADO;

            if ($intervalo > self::PARADA_MINIMA_SEGUNDOS && $paradoAgora) {
                $viagem = $this->montar($trecho);
                if ($viagem !== null) {
                    $viagens[] = $viagem;
                }
                $trecho = [];
            }
        }

        $viagem = $this->montar($trecho);
        if ($viagem !== null) {
            $viagens[] = $viagem;
        }

        return $viagens;
    }

    /**
     * Monta uma viagem a partir das posições do trecho.
     *
     * @param  list<\Illuminate\Database\Eloquent\Model>  $pontos
     * @return array<string,mixed>|null  null quando o trecho é ruído
     */
    private function montar(array $pontos): ?array
    {
        if (count($pontos) < 2) {
            return null;
        }

        $distancia = 0.0;
        $velocidadeMaxima = 0.0;
        $caminho = [];

        foreach ($pontos as $i => $p) {
            $lat = (float) $p->latitude;
            $lng = (float) $p->longitude;
            $caminho[] = ['lat' => $lat, 'lng' => $lng];
            $velocidadeMaxima = max($velocidadeMaxima, (float) $p->velocidade);

            if ($i > 0) {
                $distancia += $this->kmEntre(
                    (float) $pontos[$i - 1]->latitude, (float) $pontos[$i - 1]->longitude,
                    $lat, $lng,
                );
            }
        }

        if ($distancia < self::DISTANCIA_MINIMA_KM) {
            return null;
        }

        $primeiro = $pontos[0];
        $ultimo = $pontos[count($pontos) - 1];
        $inicio = Carbon::parse($primeiro->registrado_em);
        $fim = Carbon::parse($ultimo->registrado_em);
        $minutos = max(1, (int) round($inicio->diffInSeconds($fim) / 60));

        return [
            'inicio' => $inicio->toIso8601String(),
            'fim' => $fim->toIso8601String(),
            'duracao_min' => $minutos,
            'distancia_km' => round($distancia, 2),
            'velocidade_media' => round($distancia / max(0.01, $minutos / 60), 1),
            'velocidade_maxima' => round($velocidadeMaxima, 1),
            'origem' => ['lat' => (float) $primeiro->latitude, 'lng' => (float) $primeiro->longitude],
            'destino' => ['lat' => (float) $ultimo->latitude, 'lng' => (float) $ultimo->longitude],
            'pontos' => count($caminho),
            'caminho' => $this->reduzir($caminho),
        ];
    }

    /**
     * Enxuga o caminho preservando a FORMA do trajeto (Ramer–Douglas–Peucker).
     *
     * A primeira versão amostrava de N em N pontos, e isso desenhava triângulos
     * cortando quarteirões: numa esquina, se os pontos da conversão caíam entre
     * as amostras, a linha emendava reto por cima do quarteirão. As posições
     * reais ficam a ~58 m uma da outra (mediana medida em produção), então
     * perder duas seguidas numa curva já inventa um atalho que não existe.
     *
     * O RDP descarta apenas pontos que quase não mudam a linha — num trecho
     * reto sobra quase nada, e numa curva todos os vértices ficam. É o oposto
     * da amostragem cega: gasta pontos onde a forma exige.
     *
     * @param  list<array{lat:float,lng:float}>  $caminho
     * @return list<array{lat:float,lng:float}>
     */
    private function reduzir(array $caminho, int $maximo = 400): array
    {
        $total = count($caminho);
        if ($total <= $maximo) {
            return $caminho;
        }

        // 12 m é menos da metade do erro típico de um GPS urbano: nessa
        // tolerância o desvio some no ruído do próprio aparelho, e a linha
        // continua encostada na rua.
        $saida = $this->rdp($caminho, 12.0);

        // Trajeto muito sinuoso pode não caber no teto nem depois do RDP.
        // Afrouxa até 40 m e PARA: além disso a linha começa a cortar esquina,
        // que é justamente o defeito que este método existe para evitar. Uma
        // rota de bairro tem centenas de curvas legítimas — nesse caso o certo
        // é devolver mais pontos, não um desenho errado.
        $tolerancia = 12.0;
        while (count($saida) > $maximo && $tolerancia < 40.0) {
            $tolerancia *= 1.5;
            $saida = $this->rdp($caminho, $tolerancia);
        }

        return $saida;
    }

    /**
     * Ramer–Douglas–Peucker: mantém o ponto que mais se afasta da reta entre as
     * pontas, e repete nos dois lados até tudo caber na tolerância (metros).
     *
     * @param  list<array{lat:float,lng:float}>  $pontos
     * @return list<array{lat:float,lng:float}>
     */
    private function rdp(array $pontos, float $toleranciaMetros): array
    {
        $n = count($pontos);
        if ($n < 3) {
            return $pontos;
        }

        $pior = 0.0;
        $indice = 0;
        for ($i = 1; $i < $n - 1; $i++) {
            $d = $this->metrosAteReta($pontos[$i], $pontos[0], $pontos[$n - 1]);
            if ($d > $pior) {
                $pior = $d;
                $indice = $i;
            }
        }

        if ($pior <= $toleranciaMetros) {
            return [$pontos[0], $pontos[$n - 1]];
        }

        $esquerda = $this->rdp(array_slice($pontos, 0, $indice + 1), $toleranciaMetros);
        $direita = $this->rdp(array_slice($pontos, $indice), $toleranciaMetros);

        // O ponto do corte pertence às duas metades: sem tirar a duplicata ele
        // apareceria repetido na saída.
        return array_merge(array_slice($esquerda, 0, -1), $direita);
    }

    /**
     * Distância em metros de um ponto até o segmento a→b.
     *
     * A longitude é corrigida pelo cosseno da latitude antes da conta plana:
     * em Guarapuava (−25°) um grau de longitude vale ~10% menos que um de
     * latitude, e ignorar isso distorceria qual ponto o RDP julga relevante.
     *
     * @param  array{lat:float,lng:float}  $p
     * @param  array{lat:float,lng:float}  $a
     * @param  array{lat:float,lng:float}  $b
     */
    private function metrosAteReta(array $p, array $a, array $b): float
    {
        $metrosPorGrau = 111_320.0;
        $cos = cos($a['lat'] * M_PI / 180);

        $px = ($p['lng'] - $a['lng']) * $metrosPorGrau * $cos;
        $py = ($p['lat'] - $a['lat']) * $metrosPorGrau;
        $bx = ($b['lng'] - $a['lng']) * $metrosPorGrau * $cos;
        $by = ($b['lat'] - $a['lat']) * $metrosPorGrau;

        $comprimento = $bx * $bx + $by * $by;
        if ($comprimento < 1e-9) {
            return sqrt($px * $px + $py * $py);
        }

        // Projeção limitada a [0,1]: fora disso o ponto mais próximo é uma das
        // pontas, não a reta infinita que passa por elas.
        $t = max(0.0, min(1.0, ($px * $bx + $py * $by) / $comprimento));
        $dx = $px - $t * $bx;
        $dy = $py - $t * $by;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /** Distância em km entre duas coordenadas (haversine). */
    private function kmEntre(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rad = M_PI / 180;
        $dLat = ($lat2 - $lat1) * $rad;
        $dLng = ($lng2 - $lng1) * $rad;
        $h = sin($dLat / 2) ** 2
            + cos($lat1 * $rad) * cos($lat2 * $rad) * sin($dLng / 2) ** 2;

        return 2 * self::RAIO_TERRA_KM * asin(min(1.0, sqrt($h)));
    }
}
