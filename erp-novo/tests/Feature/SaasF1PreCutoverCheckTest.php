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

    /**
     * O portao aprovava (exit 0) um banco onde `sequencias` — a numeracao
     * fiscal — estava sem RLS alguma, porque so conferia a existencia da coluna
     * `tenant_account_id`. Ter a chave nao prova isolamento: a conversao COMPANY
     * so alcanca tabelas com `empresa_id` e pula as demais em silencio.
     */
    public function test_gate_exige_policy_canonica_e_nao_apenas_a_coluna(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/SaasF1PreCutoverCheck.php'));

        $this->assertStringContainsString('companyTablesWithoutCanonicalPolicy', $source);
        $this->assertStringContainsString('relforcerowsecurity', $source);
        $this->assertStringContainsString('app_tenant_can_read', $source);
    }

    public function test_comando_e_somente_leitura(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/SaasF1PreCutoverCheck.php'));

        foreach (['->insert(', '->update(', '->delete(', 'DB::statement('] as $write) {
            $this->assertStringNotContainsString($write, $source, "comando deveria ser somente-leitura: {$write}");
        }
    }
}
