<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Monitora\Contracts\AjustadorDeVia;
use App\Models\Monitora\ViaCache;

/**
 * Cache persistente do encaixe nas vias (economia da Roads API).
 *
 * Mesma ideia de `rotas_cache`: o trecho é identificado por CÉLULAS DE GRADE de
 * ~100 m, e um trecho que já foi aprendido nunca mais custa. Como a frota
 * repete as mesmas ruas todo dia — é uma revenda com praça definida —, a base
 * cresce rápido e o custo despenca depois dos primeiros dias.
 *
 * A tabela é GLOBAL, sem `empresa_id`: por onde passa a Rua Martin Afonso é
 * fato geográfico público, não dado de tenant. Compartilhar entre empresas
 * maximiza o reuso, exatamente como já se faz no traçado de rotas.
 */
class AjustadorCacheado implements AjustadorDeVia
{
    public function __construct(private AjustadorDeVia $interno) {}

    /** @param  list<array{lat:float,lng:float}>  $pontos */
    public function ajustar(array $pontos): ?array
    {
        if (count($pontos) < 2) {
            return null;
        }

        $chave = ViaCache::chaveDoTrecho($pontos);

        $hit = ViaCache::query()->where('trecho', $chave)->first();
        if ($hit) {
            $hit->increment('hits');

            return $hit->pontos;
        }

        $ajustado = $this->interno->ajustar($pontos);
        if ($ajustado === null) {
            // Não grava falha: sem key ou com a API fora do ar, a próxima
            // tentativa deve consultar de novo em vez de servir um vazio.
            return null;
        }

        // `firstOrCreate` cobre duas requisições simultâneas pedindo o mesmo
        // trecho — o unique na chave decide quem grava.
        ViaCache::query()->firstOrCreate(
            ['trecho' => $chave],
            ['pontos' => $ajustado, 'hits' => 0],
        );

        return $ajustado;
    }
}
