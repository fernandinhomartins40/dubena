<?php

namespace Tests\Unit;

use Tests\TestCase;

class LegacyGroupConfigurationMigrationTest extends TestCase
{
    public function test_ponte_de_configuracao_exige_evidencia_e_policy_canonica(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001100_protect_legacy_group_configuration.php');

        $this->assertStringContainsString("'tenant_legacy_group_scopes'", $source);
        $this->assertStringContainsString("'evidence_ref'", $source);
        $this->assertStringContainsString('app_tenant_can_read_group_config', $source);
        $this->assertStringContainsString('app_tenant_can_operate_group_config', $source);
        $this->assertStringContainsString('nao pertencem a migration', $source);

        $protector = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Tenant/TenantLegacyGroupConfigurationProtector.php');
        $this->assertStringContainsString('configuracao(oes) sem ponte documental aprovada', $protector);
        $this->assertStringContainsString("'clientecontatotipos'", $protector);
        $this->assertStringContainsString("'clientecontatosituacoes'", $protector);
        $this->assertStringContainsString("'motivos_nao_venda'", $protector);
        $this->assertStringContainsString("'contamovimentotipos'", $protector);
        $this->assertStringContainsString("'cargos'", $protector);
        $this->assertStringContainsString("'veiculo_tipos'", $protector);
        $this->assertStringContainsString("'promocoes'", $protector);
        $this->assertStringContainsString("'monitora_veiculo_tipos'", $protector);
        $this->assertStringContainsString("'checklists'", $protector);
        $this->assertStringContainsString("'sorteios'", $protector);
        $this->assertStringContainsString('protectChildTable', $protector);
        $this->assertStringContainsString('invalid_children', $protector);
        $this->assertStringContainsString('invalid_hierarchies', $protector);
        $this->assertStringContainsString("'ready' => \$ready", $protector);
        $this->assertStringContainsString("'condicaopagamentos'", $protector);
        $this->assertStringContainsString("'condicaopagamento_parcelas'", $protector);
        $this->assertStringContainsString('assertHierarchyTenant', $protector);
        $this->assertStringContainsString('ENABLE ROW LEVEL SECURITY', $protector);
        $this->assertStringContainsString('FORCE ROW LEVEL SECURITY', $protector);

        $hierarchyConstraint = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001300_enforce_tenant_financial_hierarchies.php');
        $this->assertStringContainsString('app_enforce_tenant_financial_hierarchy', $hierarchyConstraint);
        $this->assertStringContainsString('centros_custo_tenant_hierarchy', $hierarchyConstraint);
        $this->assertStringContainsString('planos_conta_tenant_hierarchy', $hierarchyConstraint);
        $this->assertStringContainsString('UPDATE OF pai_id, tenant_account_id', $hierarchyConstraint);
        $this->assertStringContainsString("ERRCODE = '23514'", $hierarchyConstraint);
        $this->assertStringContainsString("Schema::hasColumn('planos_conta', 'tenant_account_id')", $hierarchyConstraint);

        $this->assertStringContainsString("Schema::hasTable('tenant_legacy_group_scopes')", $source);

        $financialFkConstraint = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001400_enforce_financial_configuration_tenant_keys.php');
        $this->assertStringContainsString('app_enforce_financial_configuration_tenant', $financialFkConstraint);
        $this->assertStringContainsString('financeiros_configuration_tenant', $financialFkConstraint);
        $this->assertStringContainsString('financeirorateios_configuration_tenant', $financialFkConstraint);
        $this->assertStringContainsString('NEW.planoconta_id', $financialFkConstraint);
        $this->assertStringContainsString('NEW.centrocusto_id', $financialFkConstraint);

        $scopePolicy = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001200_protect_tenant_legacy_group_scopes.php');
        $this->assertStringContainsString('ALTER TABLE tenant_legacy_group_scopes ENABLE ROW LEVEL SECURITY', $scopePolicy);
        $this->assertStringContainsString('WITH CHECK (false)', $scopePolicy);
    }

    /**
     * Recertificacao da cobertura: estas tabelas nao tinham `empresa_id`, entao a
     * conversao COMPANY as pulava em silencio. `transportadoras` e `malha_fiscal`
     * seguiam na policy legada por grupo; os dois pivots estavam sem RLS alguma.
     */
    public function test_protetor_cobre_as_tabelas_que_a_conversao_company_pulava(): void
    {
        $protector = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Tenant/TenantLegacyGroupConfigurationProtector.php');

        $this->assertStringContainsString("'transportadoras'", $protector);
        $this->assertStringContainsString("'malha_fiscal'", $protector);

        // Pivots sem coluna de escopo: o tenant vem do pai escopado por empresa,
        // e por isso a policy usa as funcoes COMPANY, nao as de grupo.
        $this->assertStringContainsString('COMPANY_CHILDREN', $protector);
        $this->assertStringContainsString("'produto_operacao_fiscal' => ['produtos', 'produto_id']", $protector);
        $this->assertStringContainsString('protectCompanyChildTable', $protector);
        $this->assertStringContainsString('app_tenant_can_operate(parent_row.tenant_account_id, parent_row.empresa_id)', $protector);
    }

    /**
     * Achado da homologação: os dois pivots ficavam com `rls=false` mesmo após o
     * deploy. A causa é de desenho — eles recebem a COLUNA numa migration mas a
     * POLICY só pelo comando de conversão documental, que existe para legado.
     * Estando VAZIOS não há titularidade a decidir, e num tenant novo (que nunca
     * roda a conversão) eles nasceriam permanentemente sem RLS.
     */
    public function test_pivot_vazio_e_protegido_pela_estrutura_e_nao_pela_conversao(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001800_protect_empty_company_pivots.php');

        $this->assertStringContainsString("'produto_operacao_fiscal' => ['produtos', 'produto_id']", $source);
        $this->assertStringContainsString("'convenio_fechamento_pedidos'", $source);
        $this->assertStringContainsString('FORCE ROW LEVEL SECURITY', $source);
        $this->assertStringContainsString('app_tenant_can_operate(parent_row.tenant_account_id, parent_row.empresa_id)', $source);

        // Com dados, a decisão continua sendo documental: a migration não pode
        // inferir dono de linha existente.
        $this->assertStringContainsString('->count() > 0', $source);
        $this->assertStringContainsString('continue', $source);
    }

    /**
     * Mesma lacuna da `001800`, agora para configuração group-scoped:
     * `transportadoras` e `malha_fiscal` seguiam na policy legada por `grupo_id`
     * e só trocariam pela conversão documental. Vazias, num tenant novo,
     * ficariam indefinidamente confiando em `app.grupo_id` — a barreira
     * fail-open que F1 substitui.
     */
    public function test_configuracao_vazia_troca_a_policy_legada_por_grupo(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001900_protect_empty_group_configuration.php');

        $this->assertStringContainsString("'transportadoras'", $source);
        $this->assertStringContainsString("'malha_fiscal'", $source);
        // Group-scoped usa as funções de configuração de grupo, não as COMPANY.
        $this->assertStringContainsString('app_tenant_can_read_group_config(tenant_account_id, grupo_id)', $source);
        $this->assertStringContainsString('app_tenant_can_operate_group_config(tenant_account_id, grupo_id)', $source);
        // Com dados, a decisão continua documental.
        $this->assertStringContainsString('->count() > 0', $source);
        $this->assertStringContainsString('continue', $source);
    }

    /**
     * Item 4 do gate: dos 175 relacionamentos entre tabelas com chave SaaS, 168
     * tem `empresa_id` no filho (a policy ja valida na escrita) e 6 dos 7
     * restantes sao grafos ja cobertos. `sorteio_numeros.cliente_id` era o
     * unico sem guarda — provado cruzando tenants em PostgreSQL.
     */
    public function test_numero_de_sorteio_nao_aponta_para_cliente_de_outro_tenant(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001700_enforce_sorteio_numero_cliente_tenant.php');

        $this->assertStringContainsString('app_enforce_sorteio_numero_cliente_tenant', $source);
        $this->assertStringContainsString('sorteio_numeros_cliente_tenant', $source);
        $this->assertStringContainsString('UPDATE OF tenant_account_id, cliente_id', $source);
        $this->assertStringContainsString("ERRCODE = '23514'", $source);
        // Numero sem dono e legitimo: nao pode ser recusado.
        $this->assertStringContainsString('IF NEW.cliente_id IS NULL THEN', $source);
    }
}
