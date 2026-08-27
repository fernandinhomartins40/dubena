<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionedSecretsTest extends TestCase
{
    public function test_scripts_de_conversao_exigem_credenciais_do_ambiente(): void
    {
        $oracle = file_get_contents(base_path('database/etl/espelhar_oracle.py'));
        $posicoes = file_get_contents(base_path('database/etl/migrar_posicoes.py'));

        $this->assertStringContainsString('ORA_CONN = require_env("ORACLE_CONNECTION")', $oracle);
        $this->assertStringContainsString('PG_DSN = require_env("ETL_PG_DSN")', $oracle);
        $this->assertDoesNotMatchRegularExpression('/ORA_CONN\s*=\s*[\'\"]/', $oracle);
        $this->assertDoesNotMatchRegularExpression('/PG_DSN\s*=\s*[\'\"]/', $oracle);

        foreach ([
            'MONITORA_MYSQL_HOST',
            'MONITORA_MYSQL_USER',
            'MONITORA_MYSQL_PASSWORD',
            'MONITORA_MYSQL_DATABASE',
            'ETL_PG_DSN',
        ] as $variavel) {
            $this->assertStringContainsString("require_env(\"{$variavel}\")", $posicoes);
        }

        $this->assertDoesNotMatchRegularExpression('/password\s*=\s*[\'\"][^\'\"]+[\'\"]/', $posicoes);
        $this->assertDoesNotMatchRegularExpression('/PG_DSN\s*=\s*[\'\"]/', $posicoes);
    }

    public function test_scripts_python_respeitam_o_freeze_e_falham_fechado(): void
    {
        $oracle = file_get_contents(base_path('database/etl/espelhar_oracle.py'));
        $posicoes = file_get_contents(base_path('database/etl/migrar_posicoes.py'));

        foreach ([$oracle, $posicoes] as $script) {
            $this->assertStringContainsString('SAAS_FREEZE_MIGRATION_WRITES', $script);
            $this->assertStringContainsString('require_write_unfrozen()', $script);
            $this->assertStringContainsString('sys.exit(main())', $script);
        }

        $this->assertStringContainsString('__stage_', $oracle);
        $this->assertStringContainsString('descartadas or total != esperado', $oracle);
        $this->assertStringNotContainsString('SUBSTR({nome},1,200)', $oracle);
        $this->assertStringNotContainsString('SUBSTR({nome},1,500)', $oracle);
        $this->assertStringNotContainsString('criar_veiculos_orfaos', $posicoes);
        $this->assertStringNotContainsString('ORDER BY COUNT(*) DESC LIMIT 1', $posicoes);
    }
}
