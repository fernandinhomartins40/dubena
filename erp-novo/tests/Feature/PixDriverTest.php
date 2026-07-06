<?php

namespace Tests\Feature;

use App\Domain\Cobranca\Contracts\PixDriver;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Cobranca\PixCobranca;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * FASE 6 do PLANO_SEGURANCA_MULTITENANT_APPS — PIX por empresa via PixDriver.
 *
 * O registro no PSP sai do PixService para o driver (gate), com a credencial
 * resolvida pela EMPRESA DO PEDIDO (id explícito). Driver real sem credencial da
 * empresa = 503 neutro; PIX_DRIVER desconhecido explode em vez de fingir com o
 * fake; nenhuma resposta do app carrega credencial.
 */
class PixDriverTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->user->id,
        ]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create([
            'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->pedido = Pedido::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'datahora' => now(), 'valor_venda' => 75, 'valor_desconto' => 0,
        ]);
    }

    /** Stub de driver REAL que grava a credencial recebida para inspeção. */
    private function stubDriverReal(): object
    {
        $stub = new class implements PixDriver
        {
            public array $credencialRecebida = [];

            public function nome(): string
            {
                return 'stub-psp';
            }

            public function criarCobranca(array $dados, array $credencial): array
            {
                $this->credencialRecebida = $credencial;

                return ['copia_e_cola' => 'BRCODE-REAL-'.$dados['txid'], 'qrcode' => 'data:image/png;base64,QR'];
            }
        };
        $this->app->instance(PixDriver::class, $stub);

        return $stub;
    }

    public function test_driver_fake_segue_gerando_cobranca_sem_credencial(): void
    {
        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$this->pedido->id}/pix")
            ->assertCreated();

        $this->assertNotEmpty($r->json('data.copia_e_cola'));
    }

    public function test_driver_real_sem_credencial_da_empresa_responde_503(): void
    {
        $this->stubDriverReal();

        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$this->pedido->id}/pix");

        $r->assertStatus(503)->assertJsonPath('message', 'PIX indisponível nesta revenda.');
        $this->assertSame(0, PixCobranca::withoutTenant()->count());
    }

    public function test_driver_real_usa_credencial_da_empresa_do_pedido(): void
    {
        $stub = $this->stubDriverReal();
        EmpresaConfig::query()->create([
            'empresa_id' => $this->empresa->id,
            'dados' => ['integracoes' => ['pix' => [
                'psp' => 'itau',
                'client_id' => 'CID-EMP',
                'client_secret' => Crypt::encryptString('CSECRET-EMP'),
                'chave' => 'pix@revenda.com.br',
            ]]],
        ]);

        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$this->pedido->id}/pix")
            ->assertCreated();

        // O driver recebeu a credencial DA EMPRESA (decifrada só no uso).
        $this->assertSame('CID-EMP', $stub->credencialRecebida['client_id']);
        $this->assertSame('CSECRET-EMP', $stub->credencialRecebida['client_secret']);

        // E NADA de credencial vaza na resposta ao app.
        $json = json_encode($r->json());
        $this->assertStringNotContainsString('CSECRET-EMP', $json);
        $this->assertStringNotContainsString('CID-EMP', $json);
        $this->assertStringContainsString('BRCODE-REAL-', (string) $r->json('data.copia_e_cola'));
    }

    public function test_pix_driver_desconhecido_explode_em_vez_de_fingir(): void
    {
        config(['services.pix.driver' => 'itau']); // ainda não implementado

        $this->expectException(\RuntimeException::class);
        app(PixDriver::class);
    }

    public function test_config_do_app_expoe_meios_disponiveis_sem_credencial(): void
    {
        // Gate real de PIX/cartão sem credenciamento da empresa → indisponível.
        $stub = $this->stubDriverReal();
        config(['services.pix.driver' => 'stub-psp']); // o config() decide a disponibilidade
        config(['services.pagamento.driver' => 'erede']);

        $r = $this->actingAs($this->user, 'sanctum')->getJson('/api/app/v1/config')->assertOk();
        $this->assertFalse($r->json('data.pagamentos_online.pix'));
        $this->assertFalse($r->json('data.pagamentos_online.cartao'));

        // Configurou PIX próprio → aparece disponível (booleano, sem segredo).
        EmpresaConfig::query()->create([
            'empresa_id' => $this->empresa->id,
            'dados' => ['integracoes' => ['pix' => [
                'client_id' => 'CID-EMP', 'client_secret' => Crypt::encryptString('S1'),
            ]]],
        ]);
        $r = $this->actingAs($this->user, 'sanctum')->getJson('/api/app/v1/config')->assertOk();
        $this->assertTrue($r->json('data.pagamentos_online.pix'));
        $this->assertStringNotContainsString('S1', json_encode($r->json()));
    }
}
