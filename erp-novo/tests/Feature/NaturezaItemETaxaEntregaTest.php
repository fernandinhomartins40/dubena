<?php

namespace Tests\Feature;

use App\Domain\Logistica\CalculadoraTaxaEntrega;
use App\Domain\Pedido\PedidoService;
use App\Domain\Produto\NaturezaItem;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Logistica\TaxaEntrega;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Serviço não movimenta estoque, e a taxa de entrega tem regra.
 *
 * Medido em produção antes desta fase: o item "Manutenção e Instalação" ficou
 * com saldo de estoque de **−2 unidades**, porque o PedidoService dava baixa em
 * TODOS os itens sem perguntar a natureza deles.
 */
class NaturezaItemETaxaEntregaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Setor $setor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->setor = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
    }

    private function produto(string $descricao, NaturezaItem $natureza, float $preco = 100): Produto
    {
        return Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => $descricao, 'natureza' => $natureza->value, 'preco_venda' => $preco,
        ]);
    }

    private function situacaoConcluido(): PedidoSituacao
    {
        return PedidoSituacao::query()->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Concluído', 'efeito' => 'CONCLUIDO',
        ]);
    }

    // ── Natureza do item ────────────────────────────────────────────────────

    /**
     * O caso real que motivou a mudança: um serviço vendido não pode virar
     * saída de estoque de algo que nunca entrou.
     */
    public function test_servico_nao_movimenta_estoque(): void
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $servico = $this->produto('Manutenção e Instalação', NaturezaItem::SERVICO, 450);

        app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $this->situacaoConcluido()->id,
            'setor_id' => $this->setor->id,
        ], [
            ['produto_id' => $servico->id, 'quantidade' => 2, 'valor_unitario' => 450],
        ]);

        // Nenhum saldo negativo: o serviço nem entrou no controle de estoque.
        $this->assertDatabaseMissing('estoquesaldos', ['produto_id' => $servico->id]);
    }

    /** Mercadoria continua baixando normalmente — a mudança não pode afrouxar isso. */
    public function test_produto_continua_movimentando_estoque(): void
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $botijao = $this->produto('Botijão P13', NaturezaItem::PRODUTO, 120);

        app(\App\Domain\Estoque\EstoqueService::class)
            ->entrada($this->setor->id, $botijao->id, 10, 80);

        app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $this->situacaoConcluido()->id,
            'setor_id' => $this->setor->id,
        ], [
            ['produto_id' => $botijao->id, 'quantidade' => 3, 'valor_unitario' => 120],
        ]);

        $this->assertDatabaseHas('estoquesaldos', [
            'produto_id' => $botijao->id, 'setor_id' => $this->setor->id, 'quantidade' => 7,
        ]);
    }

    /**
     * Pedido SÓ de serviço não precisa de setor: não há armazém envolvido.
     * Exigi-lo travaria a venda de uma manutenção avulsa.
     */
    public function test_pedido_so_de_servico_dispensa_setor(): void
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $servico = $this->produto('Teste de estanqueidade', NaturezaItem::SERVICO, 80);

        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $this->situacaoConcluido()->id,
            // setor_id ausente de propósito.
        ], [
            ['produto_id' => $servico->id, 'quantidade' => 1, 'valor_unitario' => 80],
        ]);

        $this->assertNotNull($pedido->id);
    }

    public function test_natureza_governa_estoque_e_fiscal(): void
    {
        $this->assertTrue(NaturezaItem::PRODUTO->movimentaEstoque());
        $this->assertFalse(NaturezaItem::SERVICO->movimentaEstoque());
        $this->assertFalse(NaturezaItem::TAXA->movimentaEstoque());

        // Serviço é ISS (municipal), não ICMS.
        $this->assertTrue(NaturezaItem::SERVICO->tributaIss());
        $this->assertFalse(NaturezaItem::SERVICO->ehMercadoria());
        $this->assertFalse(NaturezaItem::SERVICO->exigeClassificacaoFiscal());
    }

    // ── Taxa de entrega ─────────────────────────────────────────────────────

    private function clienteEm(?Bairro $bairro = null, ?Cidade $cidade = null): Cliente
    {
        return Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'bairro_id' => $bairro?->id, 'cidade_id' => $cidade?->id,
        ]);
    }

    /** @param array<string,mixed> $attrs */
    private function regra(array $attrs): TaxaEntrega
    {
        return TaxaEntrega::query()->create(array_merge([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Regra', 'ativo' => true,
        ], $attrs));
    }

    public function test_taxa_por_bairro(): void
    {
        $cidade = Cidade::factory()->create(['grupo_id' => $this->empresa->grupo_id]);
        $bairro = Bairro::factory()->create([
            'grupo_id' => $this->empresa->grupo_id, 'cidade_id' => $cidade->id,
        ]);

        $this->regra([
            'criterio' => 'bairro', 'bairro_id' => $bairro->id,
            'valor' => 7.50, 'custo_estimado' => 4.00, 'prioridade' => 100,
        ]);

        $r = app(CalculadoraTaxaEntrega::class)
            ->calcular((int) $this->empresa->id, $this->clienteEm($bairro, $cidade), 100);

        $this->assertSame(7.50, $r->valor);
        $this->assertSame(4.00, $r->custo);
        // Margem e o que permite responder "esta entrega da lucro?".
        $this->assertSame(3.50, $r->margem());
    }

    /**
     * A isenção por valor mínimo VENCE a taxa de bairro: é promessa comercial
     * feita ao cliente e não pode ser anulada por uma regra de lugar.
     */
    public function test_isencao_por_valor_minimo_vence_a_taxa_de_bairro(): void
    {
        $cidade = Cidade::factory()->create(['grupo_id' => $this->empresa->grupo_id]);
        $bairro = Bairro::factory()->create([
            'grupo_id' => $this->empresa->grupo_id, 'cidade_id' => $cidade->id,
        ]);

        $this->regra(['criterio' => 'bairro', 'bairro_id' => $bairro->id, 'valor' => 7.50, 'prioridade' => 100]);
        $this->regra([
            'descricao' => 'Frete grátis acima de R$ 150',
            'criterio' => 'valor_pedido', 'faixa_de' => 150, 'isenta' => true, 'valor' => 0,
        ]);

        $calc = app(CalculadoraTaxaEntrega::class);
        $cliente = $this->clienteEm($bairro, $cidade);

        // Abaixo do mínimo: paga a taxa do bairro.
        $this->assertSame(7.50, $calc->calcular((int) $this->empresa->id, $cliente, 100)->valor);

        // Acima do mínimo: grátis.
        $acima = $calc->calcular((int) $this->empresa->id, $cliente, 200);
        $this->assertSame(0.0, $acima->valor);
        $this->assertTrue($acima->isenta);
    }

    /** Regra de bairro é mais específica que a de cidade. */
    public function test_bairro_vence_cidade(): void
    {
        $cidade = Cidade::factory()->create(['grupo_id' => $this->empresa->grupo_id]);
        $bairro = Bairro::factory()->create([
            'grupo_id' => $this->empresa->grupo_id, 'cidade_id' => $cidade->id,
        ]);

        $this->regra(['criterio' => 'cidade', 'cidade_id' => $cidade->id, 'valor' => 12.00]);
        $this->regra(['criterio' => 'bairro', 'bairro_id' => $bairro->id, 'valor' => 5.00]);

        $r = app(CalculadoraTaxaEntrega::class)
            ->calcular((int) $this->empresa->id, $this->clienteEm($bairro, $cidade), 50);

        $this->assertSame(5.00, $r->valor);
    }

    /**
     * Sem regra configurada, a entrega é GRATUITA.
     *
     * Fail-safe para o cliente final: silêncio na configuração não pode virar
     * cobrança surpresa.
     */
    public function test_sem_regra_a_entrega_e_gratuita(): void
    {
        $r = app(CalculadoraTaxaEntrega::class)
            ->calcular((int) $this->empresa->id, $this->clienteEm(), 100);

        $this->assertSame(0.0, $r->valor);
        $this->assertNull($r->regraId);
    }

    /** A regra `padrao` é o fallback da empresa quando nada mais casa. */
    public function test_regra_padrao_atende_quem_nao_casa_em_nada(): void
    {
        $this->regra(['descricao' => 'Taxa única', 'criterio' => 'padrao', 'valor' => 6.00]);

        $r = app(CalculadoraTaxaEntrega::class)
            ->calcular((int) $this->empresa->id, $this->clienteEm(), 100);

        $this->assertSame(6.00, $r->valor);
        $this->assertSame('Taxa única', $r->descricao);
    }

    /**
     * Cliente SEM coordenada não casa na regra de distância.
     *
     * Assumir distância zero daria a faixa mais barata justamente a quem o
     * sistema não sabe onde mora.
     */
    public function test_distancia_e_ignorada_sem_geocodificacao(): void
    {
        $this->regra(['criterio' => 'distancia', 'faixa_de' => 0, 'faixa_ate' => 5, 'valor' => 3.00]);
        $this->regra(['criterio' => 'padrao', 'valor' => 20.00]);

        // Cliente sem lat/lng: cai no padrão, não na faixa barata.
        $r = app(CalculadoraTaxaEntrega::class)
            ->calcular((int) $this->empresa->id, $this->clienteEm(), 100);

        $this->assertSame(20.00, $r->valor);
    }

    /** Regra de outra empresa não pode ser aplicada aqui. */
    public function test_taxa_nao_cruza_empresa(): void
    {
        $outra = Empresa::factory()->create();
        TaxaEntrega::query()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'descricao' => 'Taxa da outra', 'criterio' => 'padrao', 'valor' => 99.00, 'ativo' => true,
        ]);

        $r = app(CalculadoraTaxaEntrega::class)
            ->calcular((int) $this->empresa->id, $this->clienteEm(), 100);

        $this->assertSame(0.0, $r->valor);
    }

    /** Regra inativa não decide nada. */
    public function test_regra_inativa_e_ignorada(): void
    {
        $this->regra(['criterio' => 'padrao', 'valor' => 15.00, 'ativo' => false]);

        $r = app(CalculadoraTaxaEntrega::class)
            ->calcular((int) $this->empresa->id, $this->clienteEm(), 100);

        $this->assertSame(0.0, $r->valor);
        unset($r);
    }

    public function test_sem_permissao_a_api_de_taxas_recusa(): void
    {
        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => false,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/taxas-entrega')->assertStatus(403);
    }
}
