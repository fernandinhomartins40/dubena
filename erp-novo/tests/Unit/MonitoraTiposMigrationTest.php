<?php

namespace Tests\Unit;

use Tests\TestCase;

class MonitoraTiposMigrationTest extends TestCase
{
    public function test_migration_reconhece_tabela_de_tipos_ja_existente_sem_recria_la(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_27_000200_f1_monitora_tipos_e_campos.php');

        $this->assertStringContainsString("! Schema::hasTable('monitora_veiculo_tipos')", $source);
    }
}
