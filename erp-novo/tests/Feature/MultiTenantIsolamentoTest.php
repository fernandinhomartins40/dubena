<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FASE 8 do PLANO_SEGURANCA_MULTITENANT_APPS — regressão de ISOLAMENTO entre
 * empresas na interação dos apps. Duas empresas completas (A e B); nada de uma
 * vaza para a outra: pedidos, cobrança de cartão (credencial da empresa DO
 * PEDIDO) e o header X-Empresa-Id forjado por usuário de app.
 *
 * Complementa (sem duplicar): PixWebhookFailClosedTest (webhook), AppRoleTest
 * (papéis), AppPedidoEscopoClienteTest (mesmo tenant), IntegracaoTenantTest
 * (HMAC cruzado), IntegracaoFailClosedTest (fail-closed de credencial).
 */
class MultiTenantIsolamentoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresaA;

    private User $clienteUserA;

    private Empresa $empresaB;

    private Pedido $pedidoDeB;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresaA, $this->clienteUserA] = $this->empresaComCliente();
        [$this->empresaB, $userB] = $this->empresaComCliente();

        $this->pedidoDeB = $this->pedidoDaEmpresa($this->empresaB, $userB);
    }

    /** Empresa + cliente do app (user vinculado). @return array{0:Empresa,1:User} */
    private function empresaComCliente(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'user_id' => $user->id,
        ]);

        return [$empresa, $user];
    }

    private function pedidoDaEmpresa(Empresa $empresa, User $userDoCliente): Pedido
    {
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create([
            'grupo_id' => $empresa->grupo_id,
        ]);
        $cliente = Cliente::query()->where('user_id', $userDoCliente->id)->firstOrFail();

        return Pedido::withoutTenant()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'datahora' => now(), 'valor_venda' => 200, 'valor_desconto' => 0,
        ]);
    }

    // ── Pedidos não cruzam empresas (todas as rotas de pedido do app) ──────────

    public function test_cliente_da_empresa_a_nao_toca_pedido_da_empresa_b(): void
    {
        $rotas = [
            ['GET', "/api/app/v1/pedidos/{$this->pedidoDeB->id}"],
            ['GET', "/api/app/v1/pedidos/{$this->pedidoDeB->id}/rota-entregador"],
            ['GET', "/api/app/v1/pedidos/{$this->pedidoDeB->id}/pix/status"],
            ['POST', "/api/app/v1/pedidos/{$this->pedidoDeB->id}/pix"],
            ['POST', "/api/app/v1/pedidos/{$this->pedidoDeB->id}/pagar", ['token' => 'tok-ok']],
            ['POST', "/api/app/v1/pedidos/{$this->pedidoDeB->id}/cancelar"],
            ['POST', "/api/app/v1/pedidos/{$this->pedidoDeB->id}/avaliar", ['rating' => 5]],
        ];

        foreach ($rotas as $rota) {
            [$metodo, $url] = $rota;
            $resp = $this->actingAs($this->clienteUserA, 'sanctum')->json($metodo, $url, $rota[2] ?? []);
            $this->assertSame(404, $resp->status(), "Rota {$metodo} {$url} deveria dar 404 cross-tenant.");
        }
    }

    public function test_entregador_da_empresa_a_nao_toca_pedido_da_empresa_b(): void
    {
        $entregadorA = User::factory()->create([
            'empresa_id' => $this->empresaA->id, 'grupo_id' => $this->empresaA->grupo_id,
        ]);
        // Pedido de B até ATRIBUÍDO a este user (cenário de colisão de ids) — o
        // escopo por empresa continua barrando.
        $this->pedidoDeB->update(['entregador_user_id' => $entregadorA->id]);

        foreach ([
            ['POST', "/api/app/v1/entregador/pedidos/{$this->pedidoDeB->id}/aceitar"],
            ['POST', "/api/app/v1/entregador/pedidos/{$this->pedidoDeB->id}/status", ['pedidosituacao_id' => 1]],
            ['POST', "/api/app/v1/entregador/pedidos/{$this->pedidoDeB->id}/concluir"],
        ] as $rota) {
            [$metodo, $url] = $rota;
            $resp = $this->actingAs($entregadorA, 'sanctum')->json($metodo, $url, $rota[2] ?? []);
            $this->assertContains($resp->status(), [404, 422], "Rota {$metodo} {$url} não pode operar cross-tenant.");
        }
    }

    // ── Cartão: credencial da empresa DO PEDIDO, nunca a da outra ──────────────

    public function test_cobranca_usa_credencial_da_empresa_do_pedido_mesmo_com_outra_cadastrada(): void
    {
        config(['services.pagamento.driver' => 'erede']);
        foreach ([[$this->empresaA, 'A'], [$this->empresaB, 'B']] as [$empresa, $tag]) {
            EmpresaConfig::query()->create([
                'empresa_id' => $empresa->id,
                'dados' => ['integracoes' => ['cartao' => [
                    'gateway' => 'erede', 'pv' => "PV-{$tag}",
                    'token' => Crypt::encryptString("TOKEN-{$tag}"),
                    'url' => 'https://erede.test/v1',
                ]]],
            ]);
        }
        Http::fake(['erede.test/*' => Http::response(['returnCode' => '00', 'tid' => 'T', 'nsu' => 'N', 'authorizationCode' => 'A', 'brand' => 'visa', 'returnMessage' => 'ok'])]);

        $pedidoDeA = $this->pedidoDaEmpresa($this->empresaA, $this->clienteUserA);
        $this->actingAs($this->clienteUserA, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$pedidoDeA->id}/pagar", ['token' => 'tok'])
            ->assertCreated();

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Basic '.base64_encode('PV-A:TOKEN-A')));
        Http::assertNotSent(fn ($req) => $req->hasHeader('Authorization', 'Basic '.base64_encode('PV-B:TOKEN-B')));
    }

    // ── X-Empresa-Id forjado não troca o tenant de usuário do app ──────────────

    public function test_header_x_empresa_id_forjado_nao_troca_tenant(): void
    {
        $resp = $this->actingAs($this->clienteUserA, 'sanctum')
            ->withHeader('X-Empresa-Id', (string) $this->empresaB->id)
            ->getJson('/api/me')
            ->assertOk();

        // O tenant continua o da empresa A — cliente do app não tem pivot de empresas.
        $this->assertSame($this->empresaA->id, (int) $resp->json('tenant.empresa_id'));
    }

    public function test_historico_do_app_com_header_forjado_nao_vaza_pedidos_da_outra_empresa(): void
    {
        $this->actingAs($this->clienteUserA, 'sanctum')
            ->withHeader('X-Empresa-Id', (string) $this->empresaB->id)
            ->getJson('/api/app/v1/pedidos')
            ->assertOk()
            ->assertJsonCount(0, 'data'); // nada da empresa B
    }
}
