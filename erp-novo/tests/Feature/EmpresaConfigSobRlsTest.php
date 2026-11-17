<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\RevendaSobRls;
use Tests\TestCase;

/**
 * PIE R3-F-01 — revenda nova (sem linha em `empresa_configs`) abre a
 * configuração da empresa sob RLS.
 *
 * O GET fazia `firstOrCreate` e a linha nascia sem `tenant_account_id`: a policy
 * (`app_tenant_can_operate(tenant_account_id, …)`) recusava o insert e config,
 * certificado e integrações davam 500 em toda revenda nova. Agora o GET não
 * grava, e a escrita nasce com a chave do envelope.
 */
class EmpresaConfigSobRlsTest extends TestCase
{
    use RevendaSobRls;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }

        config(['saas_transformation.enforcement.tenant_envelope' => true]);
    }

    public function test_revenda_sem_configuracao_le_e_grava_com_a_chave(): void
    {
        $r = $this->revendaComUsuario(['empresa.view', 'empresa.edit']);
        $base = "/api/admin/empresas/{$r['empresa']}";

        $resp = $this->withToken($r['token'])->getJson("{$base}/config");
        $this->assertSame(200, $resp->status(), (string) $resp->getContent());
        $this->withToken($r['token'])->getJson("{$base}/certificado")->assertOk();
        $this->withToken($r['token'])->getJson("{$base}/integracoes")->assertOk();

        $this->withToken($r['token'])->putJson("{$base}/config", ['tempoentrega' => 40])->assertOk();

        $linha = DB::connection('pgsql_owner')->table('empresa_configs')->where('empresa_id', $r['empresa'])->first();
        $this->assertNotNull($linha);
        $this->assertSame($r['tenant'], (int) $linha->tenant_account_id);
        $this->assertSame(40, (int) $linha->tempoentrega);
    }
}
