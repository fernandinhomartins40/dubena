<?php

namespace Tests\Unit;

use App\Domain\Tenant\TableClassificationManifest;
use LogicException;
use PHPUnit\Framework\TestCase;

class TableClassificationManifestTest extends TestCase
{
    public function test_recusa_catalogo_parcial_em_vez_de_classificar_por_inferencia(): void
    {
        $manifest = new TableClassificationManifest([
            'empresas' => [
                'class' => 'COMPANY',
                'owner' => 'Tenant boundary',
                'justification' => 'Entidade operacional vinculada por tenant_companies aprovado.',
            ],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Ausentes: [pedidos]');
        $manifest->assertComplete(['empresas', 'pedidos']);
    }

    /**
     * O manifesto exigia `_bkp_autocadastro_20260820`, backup feito à mão na
     * base de agosto. No banco reinstalado ele não existe, e o portão F1
     * reprovava por uma tabela que nenhum banco novo vai ter.
     */
    public function test_tabela_opcional_ausente_nao_e_divergencia(): void
    {
        $manifest = new TableClassificationManifest([
            'empresas' => ['class' => 'COMPANY', 'owner' => 'x', 'justification' => 'y'],
            '_bkp' => ['class' => 'STAGING', 'owner' => 'x', 'justification' => 'y', 'opcional' => true],
        ]);

        $manifest->assertComplete(['empresas']);
        // Presente também passa: opcional não é "proibida".
        $manifest->assertComplete(['empresas', '_bkp']);
        $this->addToAssertionCount(2);
    }

    /** A folga é só para quem a declara: tabela comum ausente continua reprovando. */
    public function test_tabela_obrigatoria_ausente_continua_reprovando(): void
    {
        $manifest = new TableClassificationManifest([
            'empresas' => ['class' => 'COMPANY', 'owner' => 'x', 'justification' => 'y'],
            'pedidos' => ['class' => 'COMPANY', 'owner' => 'x', 'justification' => 'y'],
            '_bkp' => ['class' => 'STAGING', 'owner' => 'x', 'justification' => 'y', 'opcional' => true],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Obsoletas: [pedidos]');
        $manifest->assertComplete(['empresas']);
    }

    /** Opcional ainda precisa de classe, owner e justificativa. */
    public function test_tabela_opcional_ainda_precisa_estar_classificada(): void
    {
        $manifest = new TableClassificationManifest([
            '_bkp' => ['class' => 'STAGING', 'owner' => '', 'justification' => 'y', 'opcional' => true],
        ]);

        $this->expectException(LogicException::class);
        $manifest->assertComplete([]);
    }

    public function test_exige_classe_owner_e_justificativa_para_toda_tabela(): void
    {
        $manifest = new TableClassificationManifest([
            'empresas' => ['class' => 'UNKNOWN', 'owner' => '', 'justification' => ''],
        ]);

        $this->expectException(LogicException::class);
        $manifest->assertComplete(['empresas']);
    }

    public function test_manifesto_cobre_o_catalogo_vivo_certificado_e_as_tabelas_novas_de_f1(): void
    {
        $catalogPath = dirname(__DIR__, 3).'/docs/01-vigente/implementacao-saas/CATALOGO_VIVO.json';
        $catalog = json_decode((string) file_get_contents($catalogPath), true, flags: JSON_THROW_ON_ERROR);
        $tables = array_column($catalog['schema'], 'name');
        // Tabelas criadas DEPOIS do snapshot do catálogo vivo. Ficam listadas
        // aqui em vez de regravar o snapshot: ele é evidência de um estado
        // certificado do banco, e reescrevê-lo a cada migration nova destruiria
        // justamente o que ele serve para provar.
        $tables = array_merge($tables, [
            'tenant_accounts',
            'tenant_memberships',
            'tenant_companies',
            'tenant_company_grants',
            'tenant_legacy_group_scopes',
            'tenant_network_links',
            // F2-05 — break-glass e anti-replay de OTP.
            'break_glass_grants',
            'otp_consumidos',
            // F2-03 — limites numéricos por plano e override por empresa.
            'plano_limites',
            'limite_overrides',
            // F3-01 — papéis da pessoa com vigência (substitui os booleanos
            // paralelos `cliente`/`fornecedor`/`transportador`).
            'cliente_papeis',
            // F5-04 — conciliacao persistida: o lancamento do extrato bancario e
            // o par que ele formou (ou nao). Company: e dinheiro de UMA revenda.
            'conciliacao_lancamentos',
            // F7 — plano de controle da conversao. PLATFORM: e o processo que
            // CRIA os tenants a partir do legado, entao nao pode estar sujeito
            // ao escopo deles — uma RLS por tenant esconderia justamente a linha
            // cujo owner ficou ambiguo, que e o caso mais importante.
            // F6-01 — consumo de integracao por dono. COMPANY: a chamada e
            // cobrada da revenda, e ela precisa ver o proprio gasto. As linhas
            // sem empresa (chave da plataforma) ficam fora do alcance dela pela
            // policy, que e o certo.
            'integracao_consumos',
            'conversao_execucoes',
            'conversao_linhagem',
            'conversao_quarentena',
            // F7-03 — retrato da fonte legada.
            'conversao_snapshots',
            // F5-01 — plano de contas modelo. PLATFORM pelo criterio da
            // reclassificacao de 2026-08-29: nao tem `grupo_id` e a revenda nao
            // o edita — ela edita a COPIA dela, em `planos_conta`.
            'plano_conta_modelos',
            // F9-07 — uso das pontes legadas. COMPANY: e a revenda que chama, e
            // ela ve o proprio uso. As linhas sem empresa (`login` antes de
            // resolver tenant) ficam fora do alcance dela pela policy.
            'ponte_usos',
            // Inventário fiscal (SPED Bloco H). COMPANY: é o estoque que UMA
            // revenda declarou ao fisco, com o custo dela.
            'inventarios_fiscais',
            'inventario_fiscal_itens',
        ]);

        $entries = require dirname(__DIR__, 2).'/config/saas_table_classification.php';

        (new TableClassificationManifest($entries))->assertComplete($tables);
        $this->assertCount(count(array_unique($tables)), $entries);
    }
}
