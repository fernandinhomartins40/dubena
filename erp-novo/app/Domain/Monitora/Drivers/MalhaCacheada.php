<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Monitora\Contracts\MalhaViaria;
use App\Models\Monitora\MalhaCache;

/**
 * Cache persistente da malha viária.
 *
 * A consulta ao Overpass leva ~2 s — tempo demais para um gesto que precisa
 * responder no clique. Como a praça da revenda é fixa, poucos retângulos
 * cobrem toda a operação e a partir do segundo dia quase tudo vem do banco.
 *
 * Guarda o retângulo ARREDONDADO para a grade, e não o pedido: dois cliques em
 * quadras vizinhas caem na mesma célula e reaproveitam o mesmo download.
 */
class MalhaCacheada implements MalhaViaria
{
    public function __construct(private MalhaViaria $interno) {}

    /** @return list<list<array{lat:float,lng:float}>> */
    public function vias(float $sul, float $oeste, float $norte, float $leste): array
    {
        $chave = MalhaCache::chaveDaRegiao($sul, $oeste, $norte, $leste);

        $hit = MalhaCache::query()->where('regiao', $chave)->first();
        if ($hit) {
            $hit->increment('hits');

            return $hit->vias;
        }

        // Busca a célula INTEIRA da grade, não o retângulo pedido: é o que faz
        // o cache valer para o próximo clique ali perto. Sem isso, guardaríamos
        // um recorte que só serve para o pedido exato que o gerou.
        [$s, $o, $n, $l] = $this->celula($sul, $oeste, $norte, $leste);

        $vias = $this->interno->vias($s, $o, $n, $l);
        if ($vias === []) {
            // Não grava vazio: Overpass fora do ar não deve virar "esta região
            // não tem ruas" para sempre.
            return [];
        }

        MalhaCache::query()->firstOrCreate(
            ['regiao' => $chave],
            ['vias' => $vias, 'hits' => 0],
        );

        return $vias;
    }

    /**
     * Retângulo expandido até as bordas da grade de 0,01°.
     *
     * Uma margem extra de uma célula em volta evita o pior defeito do
     * fechamento de quadra: rua que sai da borda do retângulo deixa a quadra
     * aberta, e o contorno vaza para o quarteirão vizinho.
     *
     * @return array{0:float,1:float,2:float,3:float}
     */
    private function celula(float $sul, float $oeste, float $norte, float $leste): array
    {
        $piso = fn (float $v): float => floor($v * 100) / 100;
        $teto = fn (float $v): float => ceil($v * 100) / 100;

        return [$piso($sul) - 0.01, $piso($oeste) - 0.01, $teto($norte) + 0.01, $teto($leste) + 0.01];
    }
}
