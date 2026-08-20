<?php

namespace App\Domain\Monitora\Contracts;

/**
 * Fornece as ruas de uma região, como linhas.
 *
 * Existe porque a Roads API do Google NÃO serve para isto: ela encaixa uma
 * linha que já existe, mas não devolve a malha — não há como pedir a ela "quais
 * ruas cercam este ponto". Fechar uma quadra exige o grafo das vias, e é o
 * OpenStreetMap que o entrega.
 *
 * Isolado atrás de um contrato pelos mesmos motivos de `AjustadorDeVia`: a
 * suíte não pode depender de um serviço externo, e trocar de provedor não deve
 * mexer na geometria.
 */
interface MalhaViaria
{
    /**
     * Ruas que cruzam o retângulo, cada uma como lista de pontos.
     *
     * Devolve `[]` (e não exceção) quando não foi possível consultar: sem malha
     * as ferramentas inteligentes ficam indisponíveis, mas o desenho manual —
     * que é o modo principal — continua funcionando. Não é dado financeiro para
     * justificar fail-closed.
     *
     * @return list<list<array{lat:float,lng:float}>>
     */
    public function vias(float $sul, float $oeste, float $norte, float $leste): array;
}
