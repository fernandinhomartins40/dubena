<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Saas\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regressões da auditoria PIE (docs/pie/) — lote de segurança. Cada teste
 * nasceu de uma PoC que PASSAVA explorando a falha; aqui a asserção é a
 * inversa. Achados: R1-01 (tomada de conta pelo cadastro do app), R1-02 (IDOR
 * do perfil por `cliente_id`), R1-03 (empresa suspensa operando), R1-04 (IP do
 * proxy no lugar do cliente).
 */
class PieSegurancaRegressaoTest extends TestCase
{
    use RefreshDatabase;

    private function adminCom(Empresa $empresa, string $email, string $senha = 'senha-forte-desconhecida'): User
    {
        $admin = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'email' => $email, 'password' => Hash::make($senha),
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Admin']);
        $role->permissions()->sync([Permission::firstOrCreate(['chave' => 'cliente.view'])->id]);
        $admin->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return $admin;
    }

    public function test_r1_01_cadastro_do_app_com_email_de_admin_nao_entrega_o_usuario_do_admin(): void
    {
        $empresa = Empresa::factory()->create();
        $admin = $this->adminCom($empresa, 'dono@revenda.test');

        $resp = $this->postJson('/api/app/v1/cliente/cadastro', [
            'firebase_id_token' => 'fake:+5542911112222',
            'empresa_id' => $empresa->id,
            'nome' => 'Atacante',
            'email' => 'dono@revenda.test',
        ])->assertCreated();

        $this->assertNotSame($admin->id, $resp->json('user.id'));
        // O e-mail informado continua guardado no CLIENTE — só não vira chave de usuário.
        $this->assertSame('dono@revenda.test', Cliente::query()->withoutGlobalScopes()
            ->where('user_id', $resp->json('user.id'))->value('email'));

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$resp->json('token'))
            ->getJson('/api/admin/clientes')
            ->assertForbidden();
    }

    public function test_r1_01_token_de_app_nao_alcanca_o_painel_mesmo_com_rbac(): void
    {
        $empresa = Empresa::factory()->create();
        $admin = $this->adminCom($empresa, 'op@revenda.test');
        app(TenantContext::class)->clear();

        // Token com papel de app emitido para quem TEM permissão no painel:
        // o RBAC sozinho deixaria passar; a trava é o tipo do token.
        $tokenApp = $admin->createToken('app-cliente-x', ['role:cliente'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$tokenApp)
            ->getJson('/api/admin/clientes')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $tokenPainel = $admin->createToken('spa')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$tokenPainel)
            ->getJson('/api/admin/clientes')
            ->assertOk();
    }

    public function test_r1_02_perfil_do_app_nao_aceita_cliente_id_de_terceiro(): void
    {
        $empresa = Empresa::factory()->create();
        $staff = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $vitima = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'Vitima', 'cpf' => '12345678909',
        ]);
        app(TenantContext::class)->clear();

        $resp = $this->withHeader('Authorization', 'Bearer '.$staff->createToken('spa')->plainTextToken)
            ->getJson('/api/app/v1/perfil?cliente_id='.$vitima->id);

        $resp->assertStatus(422);
        $this->assertStringNotContainsString('12345678909', $resp->getContent());
    }

    public function test_r1_03_empresa_suspensa_nao_opera(): void
    {
        $empresa = Empresa::factory()->create(['ativo' => true]);
        $this->adminCom($empresa, 'op@revenda.test', 'segredo-123');
        app(TenantContext::class)->clear();

        $sa = PlatformAdmin::factory()->create(['password' => Hash::make('super-123')]);
        $this->withToken($sa->createToken('t')->plainTextToken)
            ->postJson("/api/superadmin/empresas/{$empresa->id}/suspender")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $token = $this->postJson('/api/login', ['email' => 'op@revenda.test', 'password' => 'segredo-123'])->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken((string) $token)->getJson('/api/admin/clientes')->assertForbidden();

        // Reativada, volta a operar — a trava é o estado, não um efeito permanente.
        $empresa->refresh()->forceFill(['ativo' => true])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken((string) $token)->getJson('/api/admin/clientes')->assertOk();
    }

    public function test_r1_04_ip_do_cliente_vem_do_forwarded_for_do_proxy_confiavel(): void
    {
        $ipVisto = function (string $remoto, string $xff): ?string {
            $request = Request::create('/api/login', 'POST', server: [
                'REMOTE_ADDR' => $remoto,
                'HTTP_X_FORWARDED_FOR' => $xff,
            ]);

            return app(TrustProxies::class)->handle($request, fn (Request $r) => response($r->ip()))->getContent();
        };

        // nginx do contêiner (rede docker) → o IP real anexado pelo nginx do host.
        $this->assertSame('200.100.50.25', $ipVisto('172.18.0.5', '200.100.50.25'));
        // Valor forjado pelo cliente fica à esquerda e é ignorado.
        $this->assertSame('200.100.50.25', $ipVisto('172.18.0.5', '9.9.9.9, 200.100.50.25'));
        // Conexão direta de IP público não é proxy: o cabeçalho não vale.
        $this->assertSame('200.100.50.25', $ipVisto('200.100.50.25', '9.9.9.9'));
    }
}
