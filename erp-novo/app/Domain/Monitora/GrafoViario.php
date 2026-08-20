<?php

namespace App\Domain\Monitora;

/**
 * Grafo planar das ruas — é o que transforma "linhas soltas" em "quadras".
 *
 * As ruas chegam do OpenStreetMap como linhas independentes, que se cruzam mas
 * não sabem disso. Aqui elas viram um grafo (nós = cruzamentos, arestas =
 * trechos), e as FACES desse grafo são exatamente os quarteirões: um ciclo
 * mínimo de ruas que não contém nenhuma outra rua por dentro.
 *
 * O algoritmo é o de caminhada pela face ("sempre a mais à direita"): a partir
 * de uma aresta, sempre virar para a via mais à direita fecha o menor ciclo
 * possível à esquerda. É o mesmo princípio de quem percorre um labirinto com a
 * mão na parede.
 */
class GrafoViario
{
    /**
     * Casas decimais para colar dois pontos no mesmo nó.
     *
     * 6 casas ≈ 0,1 m. Sem esse arredondamento, duas ruas que se cruzam com
     * coordenadas ligeiramente diferentes viram nós separados, o cruzamento não
     * existe no grafo e a face nunca fecha.
     */
    private const CASAS = 6;

    /** Máximo de arestas percorridas numa face antes de desistir (anti-laço). */
    private const PASSOS_MAXIMOS = 2000;

    /** @var array<string, array<string, array{lat:float,lng:float}>> nó => vizinhos */
    private array $adjacencia = [];

    /** @var array<string, array{lat:float,lng:float}> nó => coordenada */
    private array $pontos = [];

    /**
     * Cosseno da latitude, para corrigir a distorção do grau de longitude.
     *
     * Em Guarapuava (−25°) um grau de longitude vale ~0,9 grau de latitude em
     * metros. Sem corrigir, os ângulos saem errados e a caminhada vira na rua
     * errada nos cruzamentos oblíquos.
     */
    private float $cosLat = 1.0;

    /** @param  list<list<array{lat:float,lng:float}>>  $vias */
    public function __construct(array $vias)
    {
        $latitudes = [];

        foreach ($vias as $via) {
            $total = count($via);
            for ($i = 0; $i < $total - 1; $i++) {
                $a = $this->registrar($via[$i]);
                $b = $this->registrar($via[$i + 1]);
                if ($a === $b) {
                    continue;
                }
                $this->adjacencia[$a][$b] = $this->pontos[$b];
                $this->adjacencia[$b][$a] = $this->pontos[$a];
                $latitudes[] = $via[$i]['lat'];
            }
        }

        if ($latitudes !== []) {
            $this->cosLat = cos((array_sum($latitudes) / count($latitudes)) * M_PI / 180);
        }
    }

    /**
     * A menor face fechada que contém o ponto — o quarteirão daquele clique.
     *
     * "Menor" e não "qualquer": o ponto está dentro de várias faces aninhadas
     * (a quadra, o bairro que a contém, e assim por diante). A de menor área é
     * o quarteirão.
     *
     * @return list<array{lat:float,lng:float}>|null
     */
    public function quadraEm(float $lat, float $lng): ?array
    {
        $melhor = null;
        $menorArea = INF;

        foreach ($this->faces() as $face) {
            if (! self::contem($face, $lat, $lng)) {
                continue;
            }
            $area = abs($this->areaRelativa($face));
            if ($area < $menorArea) {
                $menorArea = $area;
                $melhor = $face;
            }
        }

        return $melhor;
    }

    /**
     * Todas as faces fechadas do grafo.
     *
     * Percorre cada aresta nos DOIS sentidos: cada aresta pertence a duas faces
     * (uma de cada lado), e visitar só um sentido perderia metade dos
     * quarteirões.
     *
     * @return list<list<array{lat:float,lng:float}>>
     */
    public function faces(): array
    {
        $faces = [];
        $vistos = [];

        foreach ($this->adjacencia as $origem => $vizinhos) {
            foreach (array_keys($vizinhos) as $destino) {
                if (isset($vistos[$origem.'>'.$destino])) {
                    continue;
                }

                $face = $this->caminharFace((string) $origem, (string) $destino, $vistos);
                // Menos de 3 nós não é polígono; é uma rua sem saída onde a
                // caminhada só foi e voltou.
                if ($face !== null && count($face) >= 3) {
                    $faces[] = $face;
                }
            }
        }

        return $faces;
    }

    /**
     * Caminha uma face a partir de uma aresta dirigida, marcando o que visitou.
     *
     * @param  array<string,bool>  $vistos
     * @return list<array{lat:float,lng:float}>|null
     */
    private function caminharFace(string $origem, string $destino, array &$vistos): ?array
    {
        $face = [$this->pontos[$origem]];
        $a = $origem;
        $b = $destino;

        for ($passo = 0; $passo < self::PASSOS_MAXIMOS; $passo++) {
            $vistos[$a.'>'.$b] = true;
            $face[] = $this->pontos[$b];

            $c = $this->maisADireita($a, $b);
            $a = $b;
            $b = $c;

            if ($a === $origem && $b === $destino) {
                // Fechou: o último ponto repete o primeiro e sobra.
                array_pop($face);

                return array_values($face);
            }
        }

        // Estourou o limite — grafo com defeito. Descartar é melhor que devolver
        // um contorno que dá a volta em meia cidade.
        return null;
    }

    /**
     * Chegando de $a em $b, para qual vizinho virar.
     *
     * Escolhe o de menor giro horário a partir do sentido de volta, o que
     * mantém a face sempre do mesmo lado. Numa rua sem saída (único vizinho é
     * de onde veio) retorna por ela — é o que contorna o beco e fecha a face.
     */
    private function maisADireita(string $a, string $b): string
    {
        $chegada = $this->angulo($b, $a);
        $melhor = null;
        $menorGiro = INF;

        foreach (array_keys($this->adjacencia[$b]) as $c) {
            if ($c === $a) {
                continue;
            }
            $giro = fmod($chegada - $this->angulo($b, (string) $c) + 2 * M_PI, 2 * M_PI);
            if ($giro < $menorGiro) {
                $menorGiro = $giro;
                $melhor = (string) $c;
            }
        }

        return $melhor ?? $a;
    }

    /** Ângulo do vetor de $de para $para, já corrigido pela latitude. */
    private function angulo(string $de, string $para): float
    {
        $p = $this->pontos[$de];
        $q = $this->pontos[$para];

        return atan2($q['lat'] - $p['lat'], ($q['lng'] - $p['lng']) * $this->cosLat);
    }

    /**
     * Área com sinal, em unidades arbitrárias — serve só para comparar faces.
     *
     * @param  list<array{lat:float,lng:float}>  $face
     */
    private function areaRelativa(array $face): float
    {
        $soma = 0.0;
        $n = count($face);

        for ($i = 0; $i < $n; $i++) {
            $a = $face[$i];
            $b = $face[($i + 1) % $n];
            $soma += ($a['lng'] * $this->cosLat) * $b['lat'] - ($b['lng'] * $this->cosLat) * $a['lat'];
        }

        return $soma / 2;
    }

    /** Registra o ponto como nó e devolve sua chave. */
    private function registrar(array $p): string
    {
        $chave = number_format($p['lat'], self::CASAS, '.', '').','.number_format($p['lng'], self::CASAS, '.', '');
        $this->pontos[$chave] ??= ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
        $this->adjacencia[$chave] ??= [];

        return $chave;
    }

    /**
     * O ponto está dentro do polígono? (regra do raio par/ímpar)
     *
     * @param  list<array{lat:float,lng:float}>  $poligono
     */
    public static function contem(array $poligono, float $lat, float $lng): bool
    {
        $dentro = false;
        $n = count($poligono);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = $poligono[$i]['lat'];
            $xi = $poligono[$i]['lng'];
            $yj = $poligono[$j]['lat'];
            $xj = $poligono[$j]['lng'];

            if (($yi > $lat) !== ($yj > $lat)
                && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }
}
