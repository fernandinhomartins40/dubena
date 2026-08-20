<?php

namespace App\Domain\Monitora\Contracts;

/**
 * Encaixa um trecho de GPS nas ruas por onde o veículo passou.
 *
 * Existe porque parte da frota reporta a cada 2 MINUTOS — quase 1 km entre
 * posições consecutivas. Ligar dois pontos assim em reta atravessa quarteirão,
 * e nenhuma redução de pontos conserta isso: o dado bruto não tem por onde o
 * veículo passou, é preciso deduzir pelo traçado das vias.
 *
 * Isolado atrás de um contrato para o serviço de viagens ser testável sem
 * chamar o Google, e para trocar de provedor sem mexer na apuração.
 */
interface AjustadorDeVia
{
    /**
     * Devolve o trecho encaixado nas vias, ou null se não foi possível.
     *
     * `null` (e não uma exceção) porque traçado é degradação aceitável: sem o
     * ajuste a linha fica reta, mas distância, horários e paradas continuam
     * corretos. Não é dado financeiro para justificar fail-closed.
     *
     * @param  list<array{lat:float,lng:float}>  $pontos
     * @return list<array{lat:float,lng:float}>|null
     */
    public function ajustar(array $pontos): ?array;
}
