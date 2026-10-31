<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\RevendaSobRls;
use Tests\TestCase;

/**
 * PIE P2-D-07 — a configuração do grupo nasce na primeira leitura, sob RLS.
 *
 * `ConfigGlobal` não declara `tenant_account_id` no `$fillable`, e o
 * `BelongsToGrupo` só preenchia a chave para quem declarava: a linha nascia sem
 * ela, a policy recusava e toda revenda nova levava 500 em `/config-global` —
 * que alimenta o rastreamento e a chave do Google Maps.
 */
class ConfigGlobalSobRlsTest extends TestCase
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

    public function test_revenda_sem_configuracao_le_e_a_linha_nasce_com_a_chave(): void
    {
        $r = $this->revendaComUsuario(['grupo.view']);

        $this->withToken($r['token'])->getJson('/api/admin/config-global')->assertOk();

        $this->assertSame($r['tenant'], (int) DB::connection('pgsql_owner')->table('config_globais')
            ->where('grupo_id', $r['grupo'])->value('tenant_account_id'));
    }
}
