<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteTelefone;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * FASE 3 do PLANO_SEGURANCA_MULTITENANT_APPS — separação de PAPEL nos tokens do app.
 *
 * O login do cliente emite role:cliente; o login por e-mail/senha (app do
 * entregador) emite role:entregador. Token de um papel não alcança as rotas do
 * outro (403); o refresh preserva o papel; staff stateful (SPA/actingAs) não é
 * afetado pela separação.
 */
class AppRoleTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
    }

    /** Token Bearer REAL de cliente (via endpoint de login com Firebase fake). */
    private function tokenCliente(): string
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Cliente App', 'ativo' => true, 'cliente' => true,
        ]);
        ClienteTelefone::create(['cliente_id' => $cliente->id, 'telefone' => '42999887766']);

        // O FakeFirebaseVerifier aceita tokens "fake:<telefone>".
        $resp = $this->postJson('/api/app/v1/cliente/login', [
            'firebase_id_token' => 'fake:+5542999887766',
            'empresa_id' => $this->empresa->id,
        ])->assertOk();

        return $resp->json('token');
    }

    /** Token Bearer REAL de entregador (login e-mail/senha do app). */
    private function tokenEntregador(): string
    {
        User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'email' => 'entregador@app.test', 'password' => Hash::make('segredo123'), 'ativo' => true,
        ]);

        $resp = $this->postJson('/api/app/v1/login', [
            'email' => 'entregador@app.test', 'password' => 'segredo123',
        ])->assertOk();

        return $resp->json('token');
    }

    public function test_token_de_cliente_nao_alcanca_rotas_de_entregador(): void
    {
        $token = $this->tokenCliente();

        $this->withToken($token)->getJson('/api/app/v1/entregador/veiculos')->assertStatus(403);
        $this->withToken($token)->getJson('/api/app/v1/entregador/pedidos')->assertStatus(403);
        $this->withToken($token)->postJson('/api/app/v1/entregador/jornada/iniciar', [])->assertStatus(403);
        $this->withToken($token)->getJson('/api/app/v1/entregador/missao')->assertStatus(403);
    }

    public function test_token_de_entregador_nao_alcanca_rotas_de_cliente(): void
    {
        $token = $this->tokenEntregador();

        $this->withToken($token)->getJson('/api/app/v1/pedidos')->assertStatus(403);
        $this->withToken($token)->getJson('/api/app/v1/perfil')->assertStatus(403);
        $this->withToken($token)->postJson('/api/app/v1/pedidos', ['itens' => [['produto_id' => 1, 'quantidade' => 1]]])->assertStatus(403);
    }

    public function test_token_de_cliente_segue_acessando_rotas_de_cliente(): void
    {
        $token = $this->tokenCliente();

        // Perfil do próprio cliente — a rota mais simples do papel.
        $this->withToken($token)->getJson('/api/app/v1/perfil')->assertOk();
    }

    public function test_token_de_entregador_segue_acessando_rotas_de_entregador(): void
    {
        $token = $this->tokenEntregador();

        $this->withToken($token)->getJson('/api/app/v1/entregador/jornada')->assertOk();
    }

    public function test_refresh_preserva_o_papel_do_token(): void
    {
        $token = $this->tokenCliente();

        $novo = $this->withToken($token)->postJson('/api/app/v1/token/refresh')->assertOk()->json('token');

        // O token rotacionado continua CLIENTE: acessa perfil, não acessa entregador.
        $this->withToken($novo)->getJson('/api/app/v1/perfil')->assertOk();
        $this->withToken($novo)->getJson('/api/app/v1/entregador/veiculos')->assertStatus(403);
    }

    public function test_staff_stateful_nao_e_afetado_pela_separacao(): void
    {
        // actingAs (sem token pessoal) = autenticação stateful do back-office.
        $staff = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => true,
        ]);

        $this->actingAs($staff, 'sanctum')->getJson('/api/app/v1/entregador/jornada')->assertOk();
    }
}
