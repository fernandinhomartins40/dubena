<?php

namespace Tests\Feature;

use App\Domain\Pedido\PapelSituacao;
use App\Models\Saas\Plano;
use App\Models\Saas\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIE P2-A-01 / P2-A-07 / P2-D-13 — revenda criada pelo superadmin (sqlite).
 *
 * Aqui: o que a revenda nasce tendo, validação, e-mail duplicado e a
 * transação inteira desfeita quando uma etapa falha. O isolamento entre
 * tenants e o login sob RLS ficam no `ProvisionamentoRevendaSobRlsTest`.
 */
class ProvisionamentoRevendaTest extends TestCase
{
    use RefreshDatabase;

    private const TABELAS = ['grupos', 'empresas', 'users', 'tenant_accounts', 'tenant_companies', 'tenant_memberships',
        'tenant_company_grants', 'roles', 'role_user', 'planos_conta', 'produtos', 'condicaopagamentos', 'assinaturas', 'password_reset_tokens'];

    private function token(): string
    {
        return PlatformAdmin::factory()->create()->createToken('teste')->plainTextToken;
    }

    private function plano(array $attrs = []): Plano
    {
        return Plano::create($attrs + ['slug' => 'essencial', 'nome' => 'Essencial', 'preco_mensal' => 100, 'ativo' => true]);
    }

    /** @return array<string, mixed> */
    private function pedido(array $extra = []): array
    {
        return $extra + [
            'razao_social' => 'Revenda Nova Ltda', 'nome_fantasia' => 'Revenda Nova',
            'cnpj' => '11.222.333/0001-81', 'dono_nome' => 'Dona da Revenda', 'dono_email' => 'dona@revenda-nova.test',
        ];
    }

    /**
     * No mesmo processo, o `auth:platform` do request anterior deixou o guard
     * padrão no da plataforma; o login usa o `web` (em produção cada request é novo).
     */
    private function login(string $email, string $senha): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $r = $this->postJson('/api/login', ['email' => $email, 'password' => $senha]);
        $this->app['auth']->forgetGuards();

        return $r;
    }

    /** @return array<string, int> */
    private function contagens(): array
    {
        return collect(self::TABELAS)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    public function test_revenda_nasce_completa_e_o_dono_entra_pelo_convite(): void
    {
        $plano = $this->plano();
        $r = $this->withToken($this->token())->postJson('/api/superadmin/revendas', $this->pedido(['trial_dias' => 7]))
            ->assertCreated();
        $d = $r->json('data');
        $empresa = (int) $d['empresa_id'];
        $grupo = (int) $d['grupo_id'];

        // Titularidade aprovada, com o dono como OWNER e grant de leitura+operação.
        $this->assertDatabaseHas('tenant_companies', ['empresa_id' => $empresa, 'tenant_account_id' => $d['tenant_account_id'], 'status' => 'APPROVED']);
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $d['dono_user_id'], 'membership_role' => 'OWNER', 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('tenant_company_grants', ['empresa_id' => $empresa, 'can_read' => true, 'can_operate' => true]);
        $this->assertDatabaseHas('tenant_legacy_group_scopes', ['grupo_id' => $grupo, 'tenant_account_id' => $d['tenant_account_id']]);

        // Papéis padrão, e o dono é Administrador na empresa.
        $this->assertSame(['Administrador', 'Entregador', 'Gerente', 'Operador'],
            DB::table('roles')->where('grupo_id', $grupo)->orderBy('nome')->pluck('nome')->all());
        $admin = DB::table('roles')->where('grupo_id', $grupo)->where('nome', 'Administrador')->value('id');
        $this->assertDatabaseHas('role_user', ['user_id' => $d['dono_user_id'], 'role_id' => $admin, 'empresa_id' => $empresa]);

        $this->assertGreaterThan(0, DB::table('planos_conta')->where('grupo_id', $grupo)->count(), 'plano de contas copiado do modelo');

        // Catálogo inicial: preço ZERO, condições (P2-D-13) e "Em Rota" com papel.
        $this->assertSame(4, DB::table('produtos')->where('empresa_id', $empresa)->count());
        $this->assertSame(0, DB::table('produtos')->where('empresa_id', $empresa)->where('preco_venda', '>', 0)->count());
        $this->assertSame(2, DB::table('condicaopagamentos')->where('grupo_id', $grupo)->where('ativo', true)->count());
        $this->assertDatabaseHas('pedidosituacoes', ['grupo_id' => $grupo, 'papel' => PapelSituacao::EM_ROTA->value]);
        $this->assertSame(3, DB::table('setores')->where('empresa_id', $empresa)->count());
        $this->assertSame(2, DB::table('pedidooperacoes')->where('grupo_id', $grupo)->count());

        // Assinatura pelo serviço existente: trial com prazo vindo do pedido.
        $this->assertDatabaseHas('assinaturas', ['empresa_id' => $empresa, 'plano_id' => $plano->id, 'status' => 'trial']);
        $this->assertSame(now()->addDays(7)->toDateString(), $d['trial_ate']);

        // Trilha da plataforma, sem o token do convite.
        $trilha = DB::table('platform_audit_logs')->where('acao', 'revenda.provisionada')->where('empresa_id', $empresa)->first();
        $this->assertNotNull($trilha);
        $this->assertStringNotContainsString($d['convite']['token'], (string) $trilha->depois);

        // Sem senha conhecida: nem a senha "vazia" nem a do convite entram.
        $email = 'dona@revenda-nova.test';
        $this->login($email, $d['convite']['token'])->assertStatus(401);

        $this->postJson('/api/definir-senha', [
            'email' => $email, 'token' => $d['convite']['token'],
            'password' => 'Senha-da-Dona-2026', 'password_confirmation' => 'Senha-da-Dona-2026',
        ])->assertOk();
        $this->login($email, 'Senha-da-Dona-2026')->assertOk();

        // Uso único: o mesmo convite não troca a senha de novo.
        $this->postJson('/api/definir-senha', [
            'email' => $email, 'token' => $d['convite']['token'],
            'password' => 'Outra-senha-2026', 'password_confirmation' => 'Outra-senha-2026',
        ])->assertStatus(422);
    }

    public function test_sem_trial_a_assinatura_nasce_ativa_com_o_plano_escolhido(): void
    {
        $this->plano();
        $outro = $this->plano(['slug' => 'completo', 'nome' => 'Completo']);

        $d = $this->withToken($this->token())->postJson('/api/superadmin/revendas', $this->pedido(['plano_id' => $outro->id, 'trial_dias' => 0]))
            ->assertCreated()->json('data');

        $this->assertDatabaseHas('assinaturas', ['empresa_id' => $d['empresa_id'], 'plano_id' => $outro->id, 'status' => 'ativa']);
    }

    public function test_validacao_recusa_pedido_incompleto(): void
    {
        $this->plano();
        $antes = $this->contagens();

        $this->withToken($this->token())->postJson('/api/superadmin/revendas', [])
            ->assertStatus(422)->assertJsonValidationErrors(['razao_social', 'dono_nome', 'dono_email']);
        $this->withToken($this->token())->postJson('/api/superadmin/revendas', $this->pedido(['cnpj' => '11.111.111/1111-11']))
            ->assertStatus(422)->assertJsonValidationErrors(['cnpj']);
        $this->withToken($this->token())->postJson('/api/superadmin/revendas', $this->pedido(['dono_email' => 'nao-e-email']))
            ->assertStatus(422)->assertJsonValidationErrors(['dono_email']);

        $this->assertSame($antes, $this->contagens());
    }

    public function test_email_existente_e_recusado_e_nunca_reaproveitado(): void
    {
        $this->plano();
        $existente = User::factory()->create(['email' => 'dona@revenda-nova.test', 'password' => Hash::make('a-senha-dela')]);
        $token = $this->token();
        $antes = $this->contagens();

        $this->withToken($token)->postJson('/api/superadmin/revendas', $this->pedido())
            ->assertStatus(422)->assertJsonValidationErrors(['dono_email']);

        // Mesmo e-mail com outra caixa: a regra `unique` do sqlite compara com
        // diferença de caixa, então quem segura é a conferência do serviço.
        DB::table('users')->where('id', $existente->id)->update(['email' => 'Dona@Revenda-Nova.test']);
        $this->withToken($token)->postJson('/api/superadmin/revendas', $this->pedido())
            ->assertStatus(422)->assertJsonValidationErrors(['dono_email']);

        $this->assertSame($antes, $this->contagens(), 'nada criado, e a conta existente intacta');
        $this->assertTrue(Hash::check('a-senha-dela', (string) DB::table('users')->where('id', $existente->id)->value('password')));
    }

    /**
     * A última etapa falha (plano de transição não se atribui pelo painel) e
     * tudo o que as anteriores criaram — grupo, empresa, dono, tenant, papéis,
     * plano de contas, catálogo — desaparece junto.
     */
    public function test_falha_numa_etapa_desfaz_a_revenda_inteira(): void
    {
        $transitorio = $this->plano(['slug' => 'legado-teste', 'nome' => 'Transição']);
        $transitorio->update(['transitorio' => true]);
        $antes = $this->contagens();

        $this->withToken($this->token())->postJson('/api/superadmin/revendas', $this->pedido(['plano_id' => $transitorio->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['plano_id']);

        $this->assertSame($antes, $this->contagens());
    }

    public function test_plano_padrao_ausente_recusa_em_vez_de_criar_sem_licenca(): void
    {
        config(['superadmin.provisionamento.plano_padrao' => 'nao-existe']);
        $antes = $this->contagens();

        $this->withToken($this->token())->postJson('/api/superadmin/revendas', $this->pedido())
            ->assertStatus(422)->assertJsonValidationErrors(['plano_id']);

        $this->assertSame($antes, $this->contagens());
    }

    public function test_so_a_plataforma_cria_revenda(): void
    {
        $this->plano();
        $this->postJson('/api/superadmin/revendas', $this->pedido())->assertStatus(401);

        $tenant = User::factory()->create()->createToken('spa')->plainTextToken;
        $this->withToken($tenant)->postJson('/api/superadmin/revendas', $this->pedido())->assertStatus(401);
    }

    /** R3-A-11 / R3-G-22 — o checklist da revenda nova aponta o que falta, e muda quando resolvido. */
    public function test_checklist_pronto_para_vender_da_revenda_nova(): void
    {
        $this->plano();
        $d = $this->withToken($this->token())->postJson('/api/superadmin/revendas', $this->pedido())->assertCreated()->json('data');
        $this->postJson('/api/definir-senha', [
            'email' => 'dona@revenda-nova.test', 'token' => $d['convite']['token'],
            'password' => 'Senha-da-Dona-2026', 'password_confirmation' => 'Senha-da-Dona-2026',
        ])->assertOk();
        $sessao = $this->login('dona@revenda-nova.test', 'Senha-da-Dona-2026')->assertOk()->json('token');

        $itens = fn () => collect($this->withToken($sessao)->getJson('/api/admin/dashboard/pronto-para-vender')
            ->assertOk()->json('data.itens'))->keyBy('chave');

        $antes = $itens();
        $this->assertSame(['fiscal', 'preco', 'estoque', 'entregador', 'area', 'condicao'], $antes->keys()->all());
        $this->assertTrue($antes['condicao']['ok'], 'as condições padrão já vêm no catálogo');
        foreach (['fiscal', 'preco', 'estoque', 'entregador', 'area'] as $chave) {
            $this->assertFalse($antes[$chave]['ok'], "{$chave} começa pendente");
            $this->assertNotEmpty($antes[$chave]['porque']);
            $this->assertStringStartsWith('/', $antes[$chave]['link']);
        }

        DB::table('produtos')->where('empresa_id', $d['empresa_id'])->limit(1)->update(['preco_venda' => 120]);
        $this->assertTrue($itens()['preco']['ok']);
    }
}
