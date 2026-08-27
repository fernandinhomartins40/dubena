<?php

return [
    /*
    | Temporary containment for the SaaS transformation.
    |
    | Until the tenancy kernel and conversion control plane pass their gates,
    | ETL writes and company creation are blocked by default. Diagnostics and
    | dry-runs remain available. Missing configuration stays fail-closed.
    */
    'freeze' => [
        'migration_writes' => env('SAAS_FREEZE_MIGRATION_WRITES', true),
        'company_creation' => env('SAAS_FREEZE_COMPANY_CREATION', true),
    ],
];
