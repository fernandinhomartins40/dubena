<?php

namespace App\Etl\Contracts;

use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;

/**
 * Contrato de um migrador de módulo (ex.: EstadosMigrator, ClientesMigrator).
 *
 * Cada fase do plano (N1..N11) entrega um ou mais Migrators. O runner (etl:run)
 * descobre-os e executa em ordem de dependência. A regra de ouro do plano:
 * todo migrator declara suas INVARIANTES, validadas após a carga (contagem,
 * Σ=saldo, integridade).
 */
interface Migrator
{
    /** Nome curto/estável (ex.: "estados", "clientes"). */
    public function nome(): string;

    /** Nomes de migrators que devem rodar ANTES deste (dependências). */
    public function dependeDe(): array;

    /** Lê do legado, transforma e grava no schema novo. */
    public function migrar(MigrationContext $ctx): MigrationResult;

    /**
     * Invariantes a validar após a carga.
     *
     * @return list<\App\Etl\Contracts\Invariant>
     */
    public function invariantes(): array;
}
