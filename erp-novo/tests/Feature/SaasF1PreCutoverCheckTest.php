<?php

namespace Tests\Feature;

use Tests\TestCase;

class SaasF1PreCutoverCheckTest extends TestCase
{
    public function test_recusa_banco_que_nao_prova_pre_cutover_rls(): void
    {
        $this->artisan('saas:f1:pre-cutover-check --connection=sqlite')
            ->expectsOutputToContain('exige PostgreSQL')
            ->assertExitCode(1);
    }

    public function test_comando_e_somente_leitura(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/SaasF1PreCutoverCheck.php'));

        foreach (['->insert(', '->update(', '->delete(', 'DB::statement('] as $write) {
            $this->assertStringNotContainsString($write, $source, "comando deveria ser somente-leitura: {$write}");
        }
    }
}
