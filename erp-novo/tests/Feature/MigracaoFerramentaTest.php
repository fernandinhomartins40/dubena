<?php

namespace Tests\Feature;

use App\Etl\Contracts\Migrator;
use App\Etl\Migrators\EstadosMigrator;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Jobs\ExecutarMigracaoJob;
use App\Models\Empresa;
use App\Models\Migracao\Migracao;
use App\Models\Migracao\MigracaoDescarte;
use App\Models\Saas\PlatformAdmin;
use App\Models\User;
use App\Services\Migracao\MigracaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ferramenta de migração de sistemas antigos (SuperAdmin).
 *
 * O que importa garantir aqui:
 *  - só o SuperAdmin acessa (é cross-tenant e cria empresas);
 *  - as credenciais do banco de origem NÃO ficam legíveis no banco nem na API;
 *  - o diagnóstico não grava nada;
 *  - a execução vai para a fila (uma migração real leva horas);
 *  - o mapeamento empresa→tenant é validado (não dá para "mapear" sem destino);
 *  - o que não entra fica registrado e é exportável — nada some em silêncio.
 */
class MigracaoFerramentaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('saas_transformation.freeze.migration_writes', false);
    }

    private function token(): string
    {
        $admin = PlatformAdmin::factory()->create(['password' => Hash::make('super-123')]);

        return $admin->createToken('teste')->plainTextToken;
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'descricao' => 'ERP da revenda',
            'origem_tipo' => 'erp_pg',
            'config' => [
                'host' => '127.0.0.1',
                'port' => 5432,
                'database' => 'ctrl_legado',
                'username' => 'leitor',
                'password' => 'segredo-do-cliente',
            ],
        ], $extra);
    }

    public function test_usuario_de_tenant_nao_acessa_a_ferramenta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/superadmin/migracoes')
            ->assertUnauthorized();
    }

    public function test_superadmin_cria_migracao_e_a_senha_da_origem_nao_vaza(): void
    {
        $resposta = $this->withToken($this->token())
            ->postJson('/api/superadmin/migracoes', $this->payload());

        $resposta->assertCreated();

        // A resposta não devolve as credenciais.
        $resposta->assertJsonMissing(['password' => 'segredo-do-cliente']);
        $this->assertStringNotContainsString('segredo-do-cliente', $resposta->getContent());

        // E no banco elas estão cifradas (cast encrypted:array).
        $bruto = (string) \DB::table('migracoes')->value('config');
        $this->assertStringNotContainsString('segredo-do-cliente', $bruto);

        // Mas continuam utilizáveis pela aplicação.
        $this->assertSame('segredo-do-cliente', Migracao::first()->config['password']);
    }

    public function test_origem_desconhecida_e_recusada(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/superadmin/migracoes', $this->payload(['origem_tipo' => 'oracle_magico']))
            ->assertStatus(422);
    }

    public function test_mapear_empresa_sem_destino_e_recusado(): void
    {
        $token = $this->token();
        $id = $this->withToken($token)
            ->postJson('/api/superadmin/migracoes', $this->payload())
            ->json('data.id');

        // "mapear" exige dizer para QUAL empresa — senão o dado iria para o
        // tenant errado, que é pior do que não migrar.
        $this->withToken($token)
            ->putJson("/api/superadmin/migracoes/{$id}/mapeamento", [
                'mapa' => [['id_origem' => 1, 'acao' => 'mapear']],
            ])
            ->assertStatus(422);
    }

    public function test_mapeamento_valido_e_gravado(): void
    {
        $token = $this->token();
        $empresa = Empresa::factory()->create();
        $id = $this->withToken($token)
            ->postJson('/api/superadmin/migracoes', $this->payload())
            ->json('data.id');

        $this->withToken($token)
            ->putJson("/api/superadmin/migracoes/{$id}/mapeamento", [
                'mapa' => [
                    ['id_origem' => 1, 'acao' => 'mapear', 'empresa_id' => $empresa->id],
                    ['id_origem' => 2, 'acao' => 'criar'],
                ],
            ])
            ->assertOk();

        $this->assertCount(2, Migracao::find($id)->mapa_empresas);
    }

    public function test_executar_enfileira_e_nao_bloqueia_a_requisicao(): void
    {
        Queue::fake();
        $token = $this->token();
        $id = $this->withToken($token)
            ->postJson('/api/superadmin/migracoes', $this->payload())
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/superadmin/migracoes/{$id}/executar")
            ->assertStatus(202);

        Queue::assertPushed(ExecutarMigracaoJob::class, fn (ExecutarMigracaoJob $job): bool => $job->platformJob && $job->platformAdminId > 0);
        $this->assertSame(Migracao::STATUS_MIGRANDO, Migracao::find($id)->status);
    }

    public function test_nao_executa_duas_vezes_em_paralelo(): void
    {
        Queue::fake();
        $token = $this->token();
        $id = $this->withToken($token)
            ->postJson('/api/superadmin/migracoes', $this->payload())
            ->json('data.id');

        $this->withToken($token)->postJson("/api/superadmin/migracoes/{$id}/executar")->assertStatus(202);
        $this->withToken($token)->postJson("/api/superadmin/migracoes/{$id}/executar")->assertStatus(409);
    }

    public function test_retomada_com_migrador_desconhecido_e_recusada(): void
    {
        Queue::fake();
        $token = $this->token();
        $id = $this->withToken($token)
            ->postJson('/api/superadmin/migracoes', $this->payload())
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/superadmin/migracoes/{$id}/executar", [
                'apenas' => ['nome-digitado-errado'],
            ])
            ->assertStatus(422);

        Queue::assertNothingPushed();
        $this->assertSame(Migracao::STATUS_PENDENTE, Migracao::find($id)->status);
    }

    public function test_etapa_com_excecao_impede_status_concluida(): void
    {
        $this->app->instance(EstadosMigrator::class, new class implements Migrator
        {
            public function nome(): string
            {
                return 'estados';
            }

            public function dependeDe(): array
            {
                return [];
            }

            public function migrar(MigrationContext $ctx): MigrationResult
            {
                throw new \RuntimeException('falha deterministica da etapa');
            }

            public function invariantes(): array
            {
                return [];
            }
        });

        $admin = PlatformAdmin::factory()->create();
        $migracao = Migracao::create([
            'descricao' => 'Teste de falha parcial',
            'origem_tipo' => 'erp_pg',
            'config' => $this->payload()['config'],
            'status' => Migracao::STATUS_PENDENTE,
            'platform_admin_id' => $admin->id,
        ]);

        try {
            (new ExecutarMigracaoJob($migracao->id, ['estados'], $admin->id))->handle(app(MigracaoService::class));
            $this->fail('O job deveria propagar a falha da etapa.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('estados', $e->getMessage());
        }

        $migracao->refresh();
        $this->assertSame(Migracao::STATUS_FALHOU, $migracao->status);
        $this->assertLessThan(100, $migracao->progresso);
        $this->assertSame('falha deterministica da etapa', $migracao->resultado['estados']['erro']);
    }

    public function test_freeze_saas_bloqueia_execucao_sem_enfileirar(): void
    {
        Queue::fake();
        config()->set('saas_transformation.freeze.migration_writes', true);
        $token = $this->token();
        $id = $this->withToken($token)
            ->postJson('/api/superadmin/migracoes', $this->payload())
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/superadmin/migracoes/{$id}/executar")
            ->assertStatus(423)
            ->assertJsonPath('operation', 'migration_writes');

        Queue::assertNothingPushed();
        $this->assertSame(Migracao::STATUS_PENDENTE, Migracao::find($id)->status);
    }

    public function test_conectar_em_origem_inacessivel_responde_erro_tratado(): void
    {
        $token = $this->token();
        $id = $this->withToken($token)
            ->postJson('/api/superadmin/migracoes', $this->payload([
                'config' => [
                    'host' => '203.0.113.1', 'port' => 5432, 'database' => 'nao_existe',
                    'username' => 'x', 'password' => 'y',
                ],
            ]))
            ->json('data.id');

        // Origem fora do ar é erro de entrada do usuário (422), não 500.
        $this->withToken($token)
            ->postJson("/api/superadmin/migracoes/{$id}/conectar")
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'erro']);
    }

    public function test_descartes_ficam_registrados_e_exportaveis_em_csv(): void
    {
        $token = $this->token();
        $id = $this->withToken($token)
            ->postJson('/api/superadmin/migracoes', $this->payload())
            ->json('data.id');

        MigracaoDescarte::create([
            'migracao_id' => $id,
            'migrador' => 'clientes',
            'entidade' => 'clientes',
            'motivo' => 'cliente sem empresa correspondente',
            'chave_origem' => '4242',
            'dados' => ['nome' => 'Fulano de Tal'],
        ]);

        $this->withToken($token)
            ->getJson("/api/superadmin/migracoes/{$id}/descartes")
            ->assertOk()
            ->assertJsonPath('data.0.motivo', 'cliente sem empresa correspondente');

        $csv = $this->withToken($token)->get("/api/superadmin/migracoes/{$id}/descartes.csv");
        $csv->assertOk();
        $this->assertStringContainsString('Fulano de Tal', $csv->streamedContent());
    }
}
