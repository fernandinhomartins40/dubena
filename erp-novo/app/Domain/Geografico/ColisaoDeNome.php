<?php

namespace App\Domain\Geografico;

use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;

/**
 * Corrigir a grafia esbarraria em outra cidade do grupo com o nome oficial.
 *
 * Não é erro de programação nem falha a contornar: é a DUPLICATA se revelando.
 * "CORENEL VIVIDA" e "Coronel Vivida" são o mesmo município em dois registros,
 * e renomear o primeiro colidiria com o segundo.
 *
 * O que falta não é renomear — é decidir qual registro sobrevive e para onde
 * vão os clientes do outro. Essa fusão é decisão humana, então o serviço recusa
 * carregando a informação necessária para tomá-la.
 */
class ColisaoDeNome extends \RuntimeException
{
    public function __construct(
        public readonly Cidade $cidade,
        public readonly Cidade $existente,
        public readonly MunicipioIbge $oficial,
    ) {
        parent::__construct(sprintf(
            'Renomear #%d "%s" para "%s" colide com #%d, que já tem esse nome. São a mesma cidade em dois registros — a fusão é decisão humana.',
            $cidade->id,
            $cidade->descricao,
            $oficial->nome,
            $existente->id,
        ));
    }
}
