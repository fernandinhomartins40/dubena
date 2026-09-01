<?php

namespace App\Etl\Support;

/**
 * Resultado da carga de um migrador (quantos lidos/gravados/pulados).
 */
final class MigrationResult
{
    public function __construct(
        public readonly string $migrator,
        public readonly int $lidos = 0,
        public readonly int $gravados = 0,
        public readonly int $pulados = 0,
        public readonly array $avisos = [],
    ) {}

    public function resumo(): string
    {
        return sprintf(
            '%s: lidos=%d gravados=%d pulados=%d',
            $this->migrator, $this->lidos, $this->gravados, $this->pulados
        );
    }
}
