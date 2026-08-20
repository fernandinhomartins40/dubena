<?php

namespace App\Domain\Monitora;

use App\Domain\Monitora\Contracts\AjustadorDeVia;
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
 * **O traçado passa pelo Google só onde precisa.** A frota tem dois perfis de
 * rastreador: o que reporta a cada 10 s deixa 80–100 m entre posições, e ali a
 * reta já segue a rua; o que reporta a cada 2 MINUTOS deixa ~937 m, e a reta
 * atravessa quadras inteiras. Só o segundo caso vai para a Roads API, sempre
 * atrás do cache de `monitora_vias_cache`.
 *
 * O resultado é gravado em `monitora_viagens_cache`: apurar viagens varre todas
 * as posições do dia, e a mesma consulta repetida (é a tela que o dono deixa
 * aberta) não pode pagar isso de novo.
 */
class ViagensService
{
    public function __construct(private AjustadorDeVia $vias) {}

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

    /**
     * Desvio a partir do qual um vaivém é ruído, não manobra real.
     *
     * Abaixo de 30 m está dentro do erro do próprio GPS e não aparece no mapa;
     * acima disso o bico fica visível saindo da rua.
     */
    private const VAIVEM_MINIMO = 30.0;

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
            // Limpa DUAS vezes, e não é redundância: antes do encaixe tira o
            // vaivém do próprio GPS (que faria a Roads API grudar pontos em
            // ruas transversais erradas), e depois tira o que o encaixe
            // introduziu — ela devolve coordenadas repetidas e, às vezes, um
            // ponto na via vizinha. Limpar só antes deixava 21 desvios de até
            // 95 m no traçado entregue.
            'caminho' => $this->limparVaivem(
                $this->encaixarNasVias($this->reduzir($this->limparVaivem($caminho)))
            ),
        ];
    }

    /**
     * Salto a partir do qual a reta entre duas posições vira corte de quarteirão.
     *
     * Medido na frota: o rastreador que reporta a cada 10 s deixa 82–104 m entre
     * posições, e a linha reta ali segue a rua. O que reporta a cada 2 MINUTOS
     * deixa ~937 m — quase 1 km de linha reta atravessando quadras. 150 m separa
     * um caso do outro com folga.
     */
    private const SALTO_QUE_CORTA_QUARTEIRAO = 150.0;

    /** Teto da Roads API por chamada. */
    private const PONTOS_POR_CHAMADA = 100;

    /**
     * Espaçamento máximo entre pontos enviados à Roads API.
     *
     * A API precisa de pistas de por onde o caminho passa. Medido contra o
     * serviço: um salto de 1,8 km enviado como DOIS pontos volta com 2 — ela
     * desiste. O mesmo salto, pré-interpolado em 9 pontos ao longo da reta,
     * volta com 98 encaixados na via.
     *
     * Os pontos intermediários são chutes sobre a linha reta; o serviço os usa
     * só como direção geral e devolve o traçado da rua de verdade.
     */
    private const ESPACAMENTO_PARA_API = 250.0;

    /**
     * Salto acima do qual nem pré-interpolar resolve.
     *
     * Um vão muito grande tem caminhos demais entre as pontas, e o palpite da
     * reta pode arrastar o resultado para a rua errada — pior que assumir a
     * reta. 5 km cobre com folga o deslocamento de 2 min do rastreador lento
     * (937 m de mediana) e ainda barra o buraco de horas sem sinal.
     */
    private const SALTO_SEM_SOLUCAO = 5000.0;

    /**
     * Encaixa nas ruas apenas os TRECHOS com salto grande.
     *
     * A Roads API cobra por chamada, e a maior parte do trajeto não precisa
     * dela: onde as posições estão a 80 m uma da outra a reta já segue a rua.
     * Gastar chamada no percurso inteiro seria pagar para consertar o que não
     * está quebrado.
     *
     * O que sai daqui é o traçado com os buracos preenchidos; se o ajuste
     * falhar (sem chave, API fora), devolve o original — linha reta é
     * degradação aceitável, distância e horários não dependem disto.
     *
     * @param  list<array{lat:float,lng:float}>  $caminho
     * @return list<array{lat:float,lng:float}>
     */
    private function encaixarNasVias(array $caminho): array
    {
        $total = count($caminho);
        if ($total < 2) {
            return $caminho;
        }

        $saida = [$caminho[0]];

        for ($i = 1; $i < $total; $i++) {
            $anterior = $caminho[$i - 1];
            $atual = $caminho[$i];
            $salto = $this->kmEntre($anterior['lat'], $anterior['lng'], $atual['lat'], $atual['lng']) * 1000;

            // Nem toda reta longa vale uma chamada: abaixo do piso a linha ja
            // segue a rua, e acima do teto o Google desiste e devolve a mesma
            // reta — pagar nos dois casos e desperdicio.
            if ($salto <= self::SALTO_QUE_CORTA_QUARTEIRAO || $salto > self::SALTO_SEM_SOLUCAO) {
                $saida[] = $atual;

                continue;
            }

            // Junta saltos consecutivos numa chamada só: num trecho de rodovia
            // eles vêm em sequência, e uma chamada por par desperdiçaria cota.
            $bloco = [$anterior, $atual];
            // O bloco sera DENSIFICADO antes de ir para a API, entao o limite
            // aqui e menor: cada salto vira varios pontos, e passar de 100 na
            // chamada faria a API recusar tudo.
            while ($i + 1 < $total && count($bloco) < 8) {
                $proximo = $this->kmEntre(
                    $caminho[$i]['lat'], $caminho[$i]['lng'],
                    $caminho[$i + 1]['lat'], $caminho[$i + 1]['lng'],
                ) * 1000;
                if ($proximo <= self::SALTO_QUE_CORTA_QUARTEIRAO || $proximo > self::SALTO_SEM_SOLUCAO) {
                    break;
                }
                $i++;
                $bloco[] = $caminho[$i];
            }

            $ajustado = $this->vias->ajustar($this->densificar($bloco));
            if ($ajustado === null) {
                // Sem o primeiro ponto: ele já está na saída.
                array_shift($bloco);
                foreach ($bloco as $p) {
                    $saida[] = $p;
                }

                continue;
            }

            // O ajustado começa no mesmo lugar do último já gravado.
            array_shift($ajustado);
            foreach ($ajustado as $p) {
                $saida[] = $p;
            }
        }

        return $saida;
    }

    /**
     * Remove o vaivem do GPS parado ou manobrando.
     *
     * Medido no dado real: um ponto a 0 km/h, 30 s depois outro 58 m adiante a
     * 17 km/h, 30 s depois de volta. O rastreador oscila enquanto o veiculo
     * manobra ou fica parado no sinal — e no mapa isso vira um bico saindo da
     * rua, o triangulo que o dono viu na tela.
     *
     * O snap-to-road NAO corrige: cada ponto isolado e grudado na via mais
     * proxima, que pode ser a transversal errada. A limpeza tem de vir antes.
     *
     * O criterio e ir e voltar: se o ponto do meio se afasta dos vizinhos mas
     * os vizinhos estao perto entre si, ele nao pertence ao percurso. Uma curva
     * legitima nao tem essa forma — nela o trajeto SEGUE, nao retorna.
     *
     * @param  list<array{lat:float,lng:float}>  $caminho
     * @return list<array{lat:float,lng:float}>
     */
    private function limparVaivem(array $caminho): array
    {
        $total = count($caminho);
        if ($total < 3) {
            return $caminho;
        }

        $saida = [$caminho[0]];

        for ($i = 1; $i < $total - 1; $i++) {
            $anterior = end($saida);
            $atual = $caminho[$i];
            $proximo = $caminho[$i + 1];

            // O critério é a distância do ponto até a RETA entre os vizinhos,
            // e não a razão entre os lados: razão depende de quanto o percurso
            // avança, e um bico grande num trecho longo escapava por pouco.
            // Aqui a pergunta é direta — este ponto está fora do caminho?
            $foraDaLinha = $this->metrosAteReta($atual, $anterior, $proximo);

            // A distância entre os vizinhos separa desvio de curva: numa
            // esquina o trajeto SEGUE (vizinhos distantes um do outro), no
            // vaivém ele RETORNA (vizinhos próximos, ponto do meio longe).
            $entreVizinhos = $this->kmEntre(
                $anterior['lat'], $anterior['lng'], $proximo['lat'], $proximo['lng'],
            ) * 1000;

            // Ponto repetido: a Roads API devolve a mesma coordenada quando
            // dois pontos caem no mesmo segmento de via. Não desenha nada e só
            // engorda o JSON.
            if ($this->kmEntre($anterior['lat'], $anterior['lng'], $atual['lat'], $atual['lng']) * 1000 < 1.0) {
                continue;
            }

            // O que separa vaivém de esquina é o ÂNGULO, não a distância.
            // Numa esquina o trajeto vira e SEGUE (ângulo aberto, 90° numa
            // quadra); no vaivém ele RETORNA por onde veio (ângulo agudo).
            // Tentei distinguir por distância até a reta e por razão entre os
            // lados: as duas ou deixavam bicos de 48 m passar, ou comiam o
            // vértice da esquina — porque em ambos os casos o ponto do meio
            // fica longe da reta, e só o ângulo diz se houve retorno.
            $ida = $this->kmEntre($anterior['lat'], $anterior['lng'], $atual['lat'], $atual['lng']) * 1000;
            $volta = $this->kmEntre($atual['lat'], $atual['lng'], $proximo['lat'], $proximo['lng']) * 1000;

            if ($ida > 1.0 && $volta > 1.0 && $foraDaLinha > self::VAIVEM_MINIMO) {
                // Lei dos cossenos no vértice do meio.
                $cos = ($ida ** 2 + $volta ** 2 - $entreVizinhos ** 2) / (2 * $ida * $volta);
                $angulo = rad2deg(acos(max(-1.0, min(1.0, $cos))));

                // Abaixo de 60° o trajeto praticamente voltou sobre si mesmo.
                // Uma esquina de quadra dá ~90°; uma curva suave, mais.
                if ($angulo < 60.0) {
                    continue;
                }
            }

            $saida[] = $atual;
        }

        $saida[] = $caminho[$total - 1];

        return $saida;
    }

    /**
     * Acrescenta pontos ao longo das retas antes de mandar para a Roads API.
     *
     * Sem isso a API desiste em saltos grandes: 1,8 km enviado como dois pontos
     * volta com dois. Com pontos a cada 250 m ao longo da reta, o mesmo trecho
     * volta com 98 encaixados na via — os intermediarios sao chutes, e servem
     * so como direcao geral para o servico achar o caminho.
     *
     * @param  list<array{lat:float,lng:float}>  $pontos
     * @return list<array{lat:float,lng:float}>
     */
    private function densificar(array $pontos): array
    {
        $total = count($pontos);
        if ($total < 2) {
            return $pontos;
        }

        $saida = [$pontos[0]];

        for ($i = 1; $i < $total; $i++) {
            $a = $pontos[$i - 1];
            $b = $pontos[$i];
            $metros = $this->kmEntre($a['lat'], $a['lng'], $b['lat'], $b['lng']) * 1000;
            $fatias = (int) ceil($metros / self::ESPACAMENTO_PARA_API);

            for ($k = 1; $k < $fatias; $k++) {
                $t = $k / $fatias;
                $saida[] = [
                    'lat' => $a['lat'] + ($b['lat'] - $a['lat']) * $t,
                    'lng' => $a['lng'] + ($b['lng'] - $a['lng']) * $t,
                ];
                // A API recusa acima de 100 pontos; parar aqui e melhor que
                // perder a chamada inteira.
                if (count($saida) >= self::PONTOS_POR_CHAMADA - 1) {
                    $saida[] = $pontos[$total - 1];

                    return $saida;
                }
            }
            $saida[] = $b;
        }

        return $saida;
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
