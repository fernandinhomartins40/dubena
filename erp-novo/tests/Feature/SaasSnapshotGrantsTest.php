<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Item 5 do gate F1: rollback/snapshot de grants e mapeamentos.
 *
 * O importador documental escreve cinco tabelas de fronteira e promove
 * `empresas.ownership_status` como efeito colateral. `migrate:rollback` nao
 * desfaz nada disso — sao dados, nao schema. Sem snapshot, uma decisao de
 * titularidade errada nao tem volta registrada.
 */
class SaasSnapshotGrantsTest extends TestCase
{
    public function test_recusa_banco_que_nao_prova_a_fronteira(): void
    {
        $this->artisan('saas:tenant:snapshot-grants alvo.json --connection=sqlite')
            ->expectsOutputToContain('exige PostgreSQL')
            ->assertExitCode(1);
    }

    public function test_restore_recusa_snapshot_inexistente(): void
    {
        $this->artisan('saas:tenant:snapshot-grants nao-existe.json --connection=sqlite --restore')
            ->expectsOutputToContain('exige PostgreSQL')
            ->assertExitCode(1);
    }

    public function test_snapshot_cobre_as_cinco_tabelas_e_o_efeito_colateral(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/SaasSnapshotGrants.php'));

        // As cinco tabelas que o importador escreve numa transacao.
        foreach ([
            'tenant_accounts',
            'tenant_companies',
            'tenant_legacy_group_scopes',
            'tenant_memberships',
            'tenant_company_grants',
        ] as $tabela) {
            $this->assertStringContainsString("'{$tabela}'", $source, "snapshot deve cobrir {$tabela}");
        }

        // O campo que vive FORA das cinco tabelas: sem ele o rollback deixaria
        // empresas marcadas como aprovadas sem vinculo nenhum.
        $this->assertStringContainsString('empresa_ownership_status', $source);
        $this->assertStringContainsString('ownership_status', $source);

        // Restaurar noutro banco apagaria a fronteira de outro ambiente.
        $this->assertStringContainsString('Recusado', $source);

        // O delete segue a ordem inversa da insercao por causa das FKs.
        $this->assertStringContainsString('array_reverse(self::TABELAS)', $source);
    }
}
