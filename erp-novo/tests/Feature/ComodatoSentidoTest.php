<?php

namespace Tests\Feature;

use App\Domain\Satelite\ComodatoPdfService;
use App\Domain\Satelite\ComodatoService;
use App\Domain\Satelite\VigilanciaComodatoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comodato tem dois sentidos, e confundi-los inverte o sinal da conta.
 *
 * CONCEDIDO: a revenda emprestou ao cliente — patrimônio dela na rua, a receber.
 * RECEBIDO:  a distribuidora emprestou à revenda — patrimônio dela aqui, a devolver.
 *
 * Confirmado com o dono em 2026-08-24: *"Temos ambas opções: de nós pra cliente,
 * e de terceiros pra nós."*
 */
class ComodatoSentidoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Produto $vasilhame;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();
        $this->vasilhame = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Vasilha P13 Kg',
            'ativo' => true,
        ]);
    }

    private function comodato(string $sentido, float $qtd, array $extra = []): Comodato
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ] + $extra);

        return Comodato::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'produto_id' => $this->vasilhame->id,
            'quantidade' => $qtd,
            'quantidade_devolvida' => 0,
            'situacao' => 'ATIVO',
            'sentido' => $sentido,
            'data_emprestimo' => now()->subYear()->toDateString(),
            'data_vencimento' => now()->subMonth()->toDateString(),
        ]);
    }

    public function test_novo_comodato_nasce_concedido(): void
    {
        $c = $this->comodato(Comodato::CONCEDIDO, 5);
        $c->refresh();

        $this->assertSame(Comodato::CONCEDIDO, $c->sentido);
    }

    /** Os escopos são o vocabulário que as consultas passam a usar. */
    public function test_escopos_separam_as_duas_contas(): void
    {
        $this->comodato(Comodato::CONCEDIDO, 5);
        $this->comodato(Comodato::RECEBIDO, 5583);

        $this->assertSame(1, Comodato::query()->concedidos()->count());
        $this->assertSame(1, Comodato::query()->recebidos()->count());
        $this->assertSame(5.0, (float) Comodato::query()->concedidos()->sum('quantidade'));
    }

    /**
     * Cobrar giro de quem nos empresta não faz sentido: ele nunca vai "comprar
     * de volta", e o giro daria zero para sempre.
     */
    public function test_vigilancia_ignora_o_recebido(): void
    {
        $this->comodato(Comodato::RECEBIDO, 5583);

        $avaliacoes = app(VigilanciaComodatoService::class)->avaliarEmpresa($this->empresa->id);

        $this->assertCount(0, $avaliacoes, 'O comodato recebido não pode virar avaliação.');
    }

    /**
     * O que substituiu a flag `fornecedor`. Medido em produção: 42 comodatos
     * ficavam fora da vigilância por causa dela, e 38 daqueles clientes tinham
     * comprado no último ano — clientes comuns que um dia emitiram nota para a
     * revenda, cegando a vigilância para 6.255 vasilhames.
     */
    public function test_cliente_marcado_fornecedor_continua_vigiado(): void
    {
        $c = $this->comodato(Comodato::CONCEDIDO, 40, ['fornecedor' => true]);

        $avaliacoes = app(VigilanciaComodatoService::class)->avaliarEmpresa($this->empresa->id);

        $this->assertCount(
            1,
            $avaliacoes,
            'A flag `fornecedor` do cadastro não pode mais tirar um comodato concedido da vigilância.',
        );
        $this->assertSame($c->cliente_id, $avaliacoes[0]->cliente_id);
    }

    /** Um contrato descreve uma direção de obrigação, não as duas. */
    public function test_contrato_nao_mistura_os_sentidos(): void
    {
        $concedido = $this->comodato(Comodato::CONCEDIDO, 5);

        // O mesmo parceiro, com uma conta em cada direção.
        Comodato::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $concedido->cliente_id,
            'produto_id' => $this->vasilhame->id,
            'quantidade' => 900,
            'quantidade_devolvida' => 0,
            'situacao' => 'ATIVO',
            'sentido' => Comodato::RECEBIDO,
            'data_emprestimo' => now()->toDateString(),
        ]);

        $metodo = new \ReflectionMethod(ComodatoPdfService::class, 'outrosDoCliente');
        $metodo->setAccessible(true);

        $outros = $metodo->invoke(app(ComodatoPdfService::class), $concedido);

        $this->assertCount(0, $outros, 'O recebido não pode entrar no contrato do concedido.');
    }

    /** Somar um sentido no outro faria o saldo se anular em vez de crescer. */
    public function test_acrescimo_nao_soma_no_sentido_oposto(): void
    {
        $recebido = $this->comodato(Comodato::RECEBIDO, 100);

        $outro = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Vasilha P45 Kg',
            'ativo' => true,
        ]);

        $novo = app(ComodatoService::class)->acrescentarProduto($recebido, $outro->id, 10);

        $this->assertSame(
            Comodato::RECEBIDO,
            $novo->refresh()->sentido,
            'A linha nova tem que herdar o sentido da relação.',
        );
    }

    /**
     * O alerta de vencimento existe para cobrar quem deve devolver. No comodato
     * recebido quem deve somos nós — a providência é outra.
     */
    public function test_alerta_de_vencimento_nao_cobra_o_fornecedor(): void
    {
        $this->comodato(Comodato::RECEBIDO, 5583);

        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum');

        $gerador = app(\App\Domain\Satelite\GerarAlertasComodato::class);
        $metodo = new \ReflectionMethod($gerador, 'vencendo');
        $metodo->setAccessible(true);

        $vencendo = $metodo->invoke(
            $gerador,
            $this->empresa->id,
            \App\Models\Satelite\ComodatoConfig::daEmpresa($this->empresa->id),
            now(),
        );

        $this->assertCount(0, $vencendo, 'Comodato recebido não gera cobrança de vencimento.');
    }
}
