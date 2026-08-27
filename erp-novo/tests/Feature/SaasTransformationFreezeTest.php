<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SaasTransformationFreezeTest extends TestCase
{
    use RefreshDatabase;

    public function test_carga_etl_fica_bloqueada_por_padrao(): void
    {
        config()->set('saas_transformation.freeze.migration_writes', true);

        $codigo = Artisan::call('etl:run', ['migrator' => 'estados']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('bloqueada', mb_strtolower(Artisan::output()));
    }

    public function test_dry_run_continua_disponivel_durante_o_freeze(): void
    {
        config()->set('saas_transformation.freeze.migration_writes', true);

        $codigo = Artisan::call('etl:run', [
            'migrator' => 'estados',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $codigo);
    }
}
