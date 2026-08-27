<?php

namespace App\Domain\Saas;

final class TransformationFreeze
{
    public function assertMigrationWritesAllowed(): void
    {
        if ((bool) config('saas_transformation.freeze.migration_writes', true)) {
            throw new TransformationFrozenException(
                'migration_writes',
                'Carga de migração bloqueada durante a transformação SaaS. Use diagnóstico ou dry-run.',
            );
        }
    }

    public function assertCompanyCreationAllowed(): void
    {
        if ((bool) config('saas_transformation.freeze.company_creation', true)) {
            throw new TransformationFrozenException(
                'company_creation',
                'Criação de empresa bloqueada até a definição da fronteira de propriedade do tenant.',
            );
        }
    }
}
