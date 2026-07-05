<?php

namespace Tests\Feature;

use App\Domain\Integracao\CredencialNaoConfiguradaException;
use App\Domain\Integracao\IntegracaoTenant;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Mobile\PagamentoOnline;
use App\Models\Pedido\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FASE 2 do PLANO_SEGURANCA_MULTITENANT_APPS — credenciais de DINHEIRO fail-closed.
 *
 * Em produção, empresa sem credenciamento de cartão próprio NUNCA herda o env
 * (cobraria na conta de outra entidade): a operação falha com 503 neutro e
 * NENHUMA chamada sai para o gateway. Fora de produção o fallback env (conta de
 * teste) é preservado.
 */
class IntegracaoFailClosedTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Cliente App', 'user_id' => $this->user->id,
        ]);
    }

    private function simularProducao(): void
    {
        $this->app['env'] = 'production';
    }

    private function pedido(): Pedido
    {
        $situacaoId = DB::table('pedidosituacoes')->insertGetId([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Aberto',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Pedido::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $situacaoId,
            'datahora' => now(), 'valor_venda' => 120, 'valor_desconto' => 0,
        ]);
    }

    // ── Resolver: fail-closed em produção ───────────────────────────────────────

    public function test_producao_cartao_sem_credencial_da_empresa_lanca(): void
    {
        config(['services.erede.pv' => 'PV-ENV', 'services.erede.token' => 'TOKEN-ENV']);
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->simularProducao();

        $this->expectException(CredencialNaoConfiguradaException::class);
        app(IntegracaoTenant::class)->cartao();
    }

    public function test_fora_de_producao_fallback_env_preservado(): void
    {
        config(['services.erede.pv' => 'PV-ENV', 'services.erede.token' => 'TOKEN-ENV']);
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);

        $cred = app(IntegracaoTenant::class)->cartao();
        $this->assertSame('PV-ENV', $cred['pv']);
        $this->assertSame('TOKEN-ENV', $cred['token']);
    }

    // ── Endpoint: 503 neutro, sem chamada ao gateway, sem registro pendente ─────

    public function test_producao_pagar_sem_credencial_responde_503_sem_chamar_gateway(): void
    {
        config([
            'services.pagamento.driver' => 'erede',
            'services.erede.pv' => 'PV-ENV', 'services.erede.token' => 'TOKEN-ENV',
            'services.erede.url' => 'https://erede.test/v1',
        ]);
        Http::fake();
        $pedido = $this->pedido();
        $this->simularProducao();

        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$pedido->id}/pagar", ['token' => 'card-tok']);

        $r->assertStatus(503)->assertJsonPath('message', 'Pagamento online indisponível nesta revenda.');
        // Nada interno vaza na resposta.
        $this->assertStringNotContainsString('Credencial', json_encode($r->json()));

        Http::assertNothingSent();
        // Transação PENDENTE não fica órfã (rollback).
        $this->assertSame(0, PagamentoOnline::withoutTenant()->count());
    }

    public function test_producao_pagar_usa_credencial_da_propria_empresa(): void
    {
        config([
            'services.pagamento.driver' => 'erede',
            'services.erede.pv' => 'PV-ENV', 'services.erede.token' => 'TOKEN-ENV',
        ]);
        EmpresaConfig::query()->create([
            'empresa_id' => $this->empresa->id,
            'dados' => ['integracoes' => ['cartao' => [
                'gateway' => 'erede', 'pv' => 'PV-EMP',
                'token' => Crypt::encryptString('TOKEN-EMP'),
                'url' => 'https://erede.test/v1',
            ]]],
        ]);
        Http::fake(['erede.test/*' => Http::response(['returnCode' => '00', 'tid' => 'T1', 'nsu' => 'N1', 'authorizationCode' => 'A1', 'brand' => 'visa', 'returnMessage' => 'Aprovado'], 200)]);
        $pedido = $this->pedido();
        $this->simularProducao();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$pedido->id}/pagar", ['token' => 'card-tok'])
            ->assertStatus(201)->assertJsonPath('data.situacao', 'AUTORIZADO');

        // A autorização saiu com o basic auth do credenciamento DA EMPRESA — não o env.
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Basic '.base64_encode('PV-EMP:TOKEN-EMP'));
        });
    }
}
