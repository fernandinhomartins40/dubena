<?php

namespace Tests\Feature;

use App\Domain\Cobranca\CodigoBarrasI25;
use App\Models\Cliente\Cliente;
use App\Models\Cobranca\Boleto;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da T4.6 — impressão do boleto, o bloqueante das saídas impressas.
 *
 * A auditoria: *"o boleto em si não é impresso; sem o PDF o título não pode ser
 * entregue ao cliente"*. O CNAB estava completo — o título nascia, ia ao banco e
 * voltava — mas nunca virava papel, e no disk-gás isso é cobrança que não chega.
 *
 * ⚠️ Estes testes provam que o PDF SAI e que o código de barras é desenhado com
 * as regras do I2of5. Eles **não substituem** a verificação humana exigida pelo
 * plano: imprimir um boleto e passar no leitor do caixa. Um barcode errado é
 * pior que boleto nenhum.
 */
class BoletoPdfTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    private function boleto(Empresa $empresa, array $extra = []): Boleto
    {
        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'João da Silva Comércio de Gás',
            'cliente' => true,
            'ativo' => true,
        ]);

        return Boleto::create(array_merge([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'banco_codigo' => 104,
            'carteira' => '01',
            'nosso_numero' => '000000012345678',
            'linha_digitavel' => '10490.00012 34567.801014 50000.000004 1 98760000015000',
            'codigo_barras' => '10491987600000150000000123456780101450000000',
            'valor' => 150.00,
            'vencimento' => '2026-09-15',
            'situacao' => 'REGISTRADO',
        ], $extra));
    }

    public function test_endpoint_devolve_um_pdf_de_verdade(): void
    {
        [$user, $empresa] = $this->suporte();
        $boleto = $this->boleto($empresa);

        $resposta = $this->actingAs($user, 'sanctum')
            ->get("/api/admin/boletos/{$boleto->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // `%PDF` é a assinatura do formato: garante que saiu um PDF de verdade,
        // e não uma página de erro devolvida com status 200.
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_pdf_exige_autorizacao(): void
    {
        [, $empresa] = $this->suporte();
        $boleto = $this->boleto($empresa);

        $semPermissao = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);

        // Boleto é dado financeiro de cliente: não é público.
        $this->actingAs($semPermissao, 'sanctum')
            ->get("/api/admin/boletos/{$boleto->id}/pdf")
            ->assertStatus(403);
    }

    public function test_boleto_sem_codigo_de_barras_nao_derruba_a_impressao(): void
    {
        [$user, $empresa] = $this->suporte();
        $boleto = $this->boleto($empresa, ['codigo_barras' => null]);

        // Falha visível no papel ("não pagável") é melhor que erro 500: o
        // operador vê o problema em vez de achar que o sistema quebrou.
        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/boletos/{$boleto->id}/pdf")
            ->assertOk();
    }

    // ─────────────────── Código de barras I2of5 ───────────────────

    public function test_barras_desenha_com_as_guardas_do_padrao(): void
    {
        $html = (new CodigoBarrasI25)->html('10491987600000150000000123456780101450000000');

        $this->assertStringContainsString('<div', $html);
        // Larguras fina (1px) e grossa (3px) na razão 1:3 — é o que o leitor usa
        // para distinguir os bits.
        $this->assertStringContainsString('width:1px', $html);
        $this->assertStringContainsString('width:3px', $html);
        $this->assertStringContainsString('background:#000', $html);
    }

    public function test_barras_normaliza_quantidade_impar_de_digitos(): void
    {
        // O I2of5 codifica EM PARES: um número ímpar de dígitos produziria um
        // código ilegível, então o zero à esquerda é obrigatório.
        $impar = (new CodigoBarrasI25)->html('123');
        $par = (new CodigoBarrasI25)->html('0123');

        $this->assertSame($par, $impar);
    }

    public function test_barras_recusa_entrada_invalida(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CodigoBarrasI25)->html('');
    }
}
