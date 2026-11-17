<?php

namespace Tests\Feature;

use App\Domain\Cliente\BuscaCliente;
use App\Domain\Identidade\DocumentoFiscal;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteTelefone;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Produto\UnidadeMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rodada 3 — Agente A (atendimento): busca sem acento e por telefone (P2-D-05),
 * documento com dígito verificador (PIE-35) e quantidade do item (P2-D-12).
 * Cada teste aqui foi visto FALHAR com a correção retirada (ver
 * docs/pie/rodada3/A_ATENDIMENTO.md).
 */
class PieR3AtendimentoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
    }

    private function cliente(string $nome, ?string $fantasia = null): Cliente
    {
        return Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => $nome, 'fantasia' => $fantasia,
        ]);
    }

    /** @return list<int> */
    private function ids(string $url): array
    {
        return array_column($this->actingAs($this->user, 'sanctum')->getJson($url)->assertOk()->json('data'), 'id');
    }

    // ---- P2-D-05 ---------------------------------------------------------

    public function test_lookup_acha_cliente_digitado_sem_acento(): void
    {
        $joao = $this->cliente('João Haick');
        $acougue = $this->cliente('Açougue Bastos Ltda');
        $this->cliente('Maria Souza');

        $this->assertSame([$joao->id], $this->ids('/api/admin/lookups/clientes?q=joao'));
        $this->assertSame([$joao->id], $this->ids('/api/admin/lookups/clientes?q=JOAO%20HAICK'));
        $this->assertSame([$acougue->id], $this->ids('/api/admin/lookups/clientes?q=acougue'));
        // Com acento continua achando.
        $this->assertSame([$joao->id], $this->ids('/api/admin/lookups/clientes?q='.urlencode('João')));
    }

    public function test_listagem_acha_sem_acento_pela_fantasia_e_pelo_telefone(): void
    {
        $patricia = $this->cliente('Patrícia Lustosa');
        $mercado = $this->cliente('Comercial XYZ', 'Mercadão São José');
        ClienteTelefone::factory()->create(['cliente_id' => $patricia->id, 'telefone' => '(42) 94725-0763']);

        $this->assertSame([$patricia->id], $this->ids('/api/admin/clientes?q=patricia'));
        $this->assertSame([$mercado->id], $this->ids('/api/admin/clientes?q=mercadao%20sao'));
        // A listagem não buscava por telefone (R2, J03): "94725" dava total 0.
        foreach (['94725', '(42) 94725-0763', '42947250763'] as $fone) {
            $this->assertSame([$patricia->id], $this->ids('/api/admin/clientes?q='.urlencode($fone)), $fone);
        }
    }

    public function test_termo_nao_atravessa_nome_e_fantasia(): void
    {
        // "lustosa mercad" juntaria o fim do nome com o começo da fantasia.
        $this->cliente('Ana Lustosa', 'Mercado Bom');
        $this->assertSame([], $this->ids('/api/admin/clientes?q=lustosa%20mercad'));
    }

    public function test_termo_so_de_pontuacao_nao_devolve_a_base(): void
    {
        $this->cliente('Qualquer Um');
        $this->assertSame([], $this->ids('/api/admin/clientes?q=--'));
    }

    public function test_lookup_de_produto_acha_sem_acento(): void
    {
        $p = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Botijão P13', 'ativo' => true]);
        $this->assertSame([$p->id], $this->ids('/api/admin/lookups/produtos?q=botijao'));
    }

    public function test_traducao_do_postgres_cobre_maiusculas_acentuadas(): void
    {
        ['de' => $de, 'para' => $para] = BuscaCliente::traducaoPostgres();
        $this->assertSame(mb_strlen($de), mb_strlen($para));
        $this->assertStringContainsString('Ã', $de);
        $this->assertStringContainsString('Ç', $de);
        $this->assertSame('joao haick|acougue', BuscaCliente::chave('JOÃO  Haick', 'Açougue'));
    }

    // ---- PIE-35 ----------------------------------------------------------

    public function test_cpf_e_cnpj_sem_digito_verificador_sao_recusados(): void
    {
        foreach (['111.111.111-11', '123', '123.456.789-0123456', '529.982.247-26'] as $cpf) {
            $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/admin/clientes', ['nome' => 'Fulano', 'cpf' => $cpf, 'telefones' => [['telefone' => '(42) 99999-0000']]])
                ->assertStatus(422)->assertJsonValidationErrors('cpf');
        }
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/clientes', ['nome' => 'Empresa X', 'cnpj' => '11.222.333/0001-80', 'telefones' => [['telefone' => '(42) 99999-0001']]])
            ->assertStatus(422)->assertJsonValidationErrors('cnpj');
    }

    public function test_documento_valido_e_gravado_so_com_digitos(): void
    {
        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/clientes', ['nome' => 'Fulano Válido', 'cpf' => '529.982.247-25', 'telefones' => [['telefone' => '(42) 99999-0002']]])
            ->assertCreated();
        $this->assertSame('52998224725', Cliente::query()->findOrFail($r->json('data.id'))->cpf);

        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/clientes', ['nome' => 'Empresa Válida', 'cnpj' => '11.222.333/0001-81', 'telefones' => [['telefone' => '(42) 99999-0003']]])
            ->assertCreated();
        $this->assertSame('11222333000181', Cliente::query()->findOrFail($r->json('data.id'))->cnpj);
    }

    public function test_edicao_nao_trava_por_documento_legado_que_nao_mudou(): void
    {
        $legado = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Cliente Migrado', 'cpf' => '11111111111',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/clientes/{$legado->id}", ['nome' => 'Cliente Migrado Editado', 'cpf' => '111.111.111-11'])
            ->assertOk();

        // Trocar por outro inválido é recusado.
        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/clientes/{$legado->id}", ['nome' => 'Cliente Migrado', 'cpf' => '222.222.222-22'])
            ->assertStatus(422)->assertJsonValidationErrors('cpf');
    }

    public function test_digito_verificador(): void
    {
        $this->assertTrue(DocumentoFiscal::cpfValido('529.982.247-25'));
        $this->assertFalse(DocumentoFiscal::cpfValido('52998224726'));
        $this->assertFalse(DocumentoFiscal::cpfValido('00000000000'));
        $this->assertTrue(DocumentoFiscal::cnpjValido('11.222.333/0001-81'));
        $this->assertFalse(DocumentoFiscal::cnpjValido('11.222.333/0001-80'));
    }

    // ---- P2-D-12 ---------------------------------------------------------

    /** @return array<string, mixed> */
    private function pedido(float $quantidade, string $sigla = 'UN'): array
    {
        $unidade = UnidadeMedida::query()->firstOrCreate(['grupo_id' => $this->empresa->grupo_id, 'descricao' => $sigla], ['sigla' => $sigla, 'ativo' => true]);
        $produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Botijão P13', 'unidademedida_id' => $unidade->id, 'preco_venda' => 120,
        ]);
        $situacao = PedidoSituacao::query()->where('grupo_id', $this->empresa->grupo_id)->first()
            ?? PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);

        return [
            'cliente_id' => $this->cliente('Comprador')->id,
            'pedidosituacao_id' => $situacao->id,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => $quantidade]],
        ];
    }

    public function test_produto_por_unidade_nao_aceita_fracao(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', $this->pedido(1.5))
            ->assertStatus(422)
            ->assertJsonValidationErrors('itens.0.quantidade')
            ->assertJsonFragment(['"Botijão P13" é vendido por unidade: informe uma quantidade inteira.']);
    }

    public function test_produto_por_peso_aceita_fracao(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', $this->pedido(1.5, 'KG'))
            ->assertCreated();
    }

    public function test_teto_por_item_vem_da_configuracao_da_revenda(): void
    {
        // Sem configuração não há teto: o número depende da revenda.
        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', $this->pedido(1000))->assertCreated();

        EmpresaConfig::query()->create(['empresa_id' => $this->empresa->id, 'dados' => ['pedido_quantidade_maxima_item' => 100]]);

        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', $this->pedido(1000000))
            ->assertStatus(422)->assertJsonValidationErrors('itens.0.quantidade');
        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', $this->pedido(100))->assertCreated();
    }
}
