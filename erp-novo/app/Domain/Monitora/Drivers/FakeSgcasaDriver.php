<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Monitora\Contracts\SgcasaDriver;

/**
 * Driver SGCasa para DEV/HOMOLOG/CI (sem serviço externo). Retorna posições vazias
 * por padrão; em testes pode ser substituído por um stub que devolve posições fixas.
 * Em produção, o driver real consome a API do SGCasa.
 */
class FakeSgcasaDriver implements SgcasaDriver
{
    /** @var list<array<string,mixed>> posições pré-carregadas (para teste) */
    public array $posicoes = [];

    public function buscarPosicoes(array $imeis): array
    {
        return array_values(array_filter(
            $this->posicoes,
            fn ($p) => in_array($p['imei'], $imeis, true),
        ));
    }
}
