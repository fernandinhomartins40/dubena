<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A escrita da PLATAFORMA (sem empresa) não pode ser rejeitada pela RLS.
 *
 * ## O defeito que este teste existe para impedir
 *
 * `integracao_consumos` (F6-01) foi criada para responder *quem gastou quanto*,
 * e o cabeçalho da migration diz que os nulos significam "chave da plataforma —
 * **o caso que hoje some no log, e que é justamente o que se quer enxergar**".
 *
 * A policy, porém, saiu com `WITH CHECK (empresa_id IS NOT NULL AND ...)`. Com
 * `FORCE ROW LEVEL SECURITY`, o Postgres rejeita todo insert de `empresa_id`
 * nulo — a tabela criada para enxergar o consumo da plataforma era exatamente a
 * que nunca o registrava.
 *
 * Conferido ao vivo em homologação antes de escrever isto:
 * `ERROR: new row violates row-level security policy`.
 *
 * ## Por que nada acusava
 *
 *  - o registrador engole toda exceção, de propósito (contar chamada não pode
 *    derrubar uma geocodificação) — o que transforma a rejeição em nada;
 *  - a suíte roda em sqlite, que não tem RLS.
 *
 * Este teste fecha o segundo furo. O primeiro continua certo como está: a
 * instrumentação **deve** ser silenciosa; o que não pode é ninguém verificar o
 * banco de verdade.
 */
class EscritaDePlataformaSobRlsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function escritasDePlataforma(): array
    {
        return [
            'consumo de integração com chave da plataforma' => [
                'integracao_consumos',
                [
                    'servico' => 'geocoding',
                    'dia' => '2026-09-01',
                    'chamadas' => 1,
                    'erros' => 0,
                    'custo_centavos' => 0,
                ],
            ],
            'chamada de ponte antes de resolver tenant (login/init)' => [
                'ponte_usos',
                [
                    'ponte' => 'nfweb',
                    'endpoint' => 'login',
                    'dia' => '2026-09-01',
                    'chamadas' => 1,
                    'recusas' => 0,
                ],
            ],
        ];
    }

    /**
     * @dataProvider escritasDePlataforma
     *
     * @param  array<string, mixed>  $linha
     */
    public function test_linha_sem_empresa_pode_ser_gravada(string $tabela, array $linha): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é do PostgreSQL; o CI roda este teste com a role real');
        }

        // A role do runtime é quem sofre a policy: como owner, `FORCE ROW LEVEL
        // SECURITY` ainda se aplica, mas é `erp_app` que o sistema usa em
        // produção — testar com ela é testar o caminho real.
        DB::statement('SET ROLE erp_app');

        try {
            DB::table($tabela)->insert($linha + [
                'empresa_id' => null,
                'tenant_account_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('RESET ROLE');
        }

        $this->assertSame(
            1,
            DB::table($tabela)->whereNull('empresa_id')->count(),
            "{$tabela}: a linha da plataforma precisa ser gravável. ".
            'Com `WITH CHECK (empresa_id IS NOT NULL AND ...)` o Postgres a rejeita, '.
            'e o registrador engole a exceção — o dado some sem ninguém notar.',
        );
    }

    /**
     * O que a policy CONTINUA impedindo.
     *
     * Aceitar nulo não é afrouxar: a linha sem empresa não pertence a tenant
     * nenhum, então não há sigilo a violar. O que não pode é uma revenda gravar
     * linha de OUTRA — e isso segue barrado.
     */
    public function test_revenda_alheia_continua_barrada(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é do PostgreSQL; o CI roda este teste com a role real');
        }

        $empresa = \App\Models\Empresa::factory()->create();

        DB::statement('SET ROLE erp_app');

        try {
            // Sem envelope de tenant no contexto, `app_tenant_can_operate`
            // devolve falso — é o comportamento fail-closed.
            $barrou = false;

            try {
                DB::table('ponte_usos')->insert([
                    'ponte' => 'nfweb',
                    'endpoint' => 'getCliente',
                    'empresa_id' => $empresa->id,
                    'tenant_account_id' => $empresa->tenant_account_id,
                    'dia' => '2026-09-01',
                    'chamadas' => 1,
                    'recusas' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {
                $barrou = true;
            }
        } finally {
            DB::statement('RESET ROLE');
        }

        $this->assertTrue(
            $barrou,
            'escrita COM empresa e sem envelope de tenant tem de ser barrada — senão a policy não protege nada',
        );
    }
}
