<?php

namespace Tests\Migration;

use App\Etl\Migrators\EstadosMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prova o pipeline ETL ponta-a-ponta (N0): carga + invariante de contagem.
 * Sem banco legado disponível, usa o conjunto-semente (27 UFs).
 */
class EstadosMigratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_migra_estados_e_invariante_passa(): void
    {
        $migrator = new EstadosMigrator();
        $ctx = new MigrationContext();

        $res = $migrator->migrar($ctx);

        $this->assertSame(27, $res->lidos);
        $this->assertSame(27, $res->gravados);
        $this->assertSame(27, Estado::count());

        foreach ($migrator->invariantes() as $inv) {
            $this->assertTrue($inv->verificar()->ok, $inv->nome().' deveria passar');
        }
    }

    public function test_dry_run_nao_grava(): void
    {
        $res = (new EstadosMigrator())->migrar(new MigrationContext(dryRun: true));

        $this->assertSame(27, $res->lidos);
        $this->assertSame(0, $res->gravados);
        $this->assertSame(0, Estado::count());
    }

    public function test_idempotente_nao_duplica(): void
    {
        $m = new EstadosMigrator();
        $m->migrar(new MigrationContext());
        $m->migrar(new MigrationContext()); // roda 2x

        $this->assertSame(27, Estado::count());
    }
}
