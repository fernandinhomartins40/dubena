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
        ]);

        $entries = require dirname(__DIR__, 2).'/config/saas_table_classification.php';

        (new TableClassificationManifest($entries))->assertComplete($tables);
        $this->assertCount(count(array_unique($tables)), $entries);
    }
}
