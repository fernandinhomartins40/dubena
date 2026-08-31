<?php

namespace Tests\Feature;

use App\Domain\Pedido\CanalVenda;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3-05 — o pedido registra por qual porta entrou.
 *
 * Quatro caminhos criam pedido: o painel admin, o app do consumidor, o app do
 * entregador (venda em campo) e a central de vendas. No banco, os quatro
 * ficavam idênticos.
 *
 * O que a revenda não conseguia perguntar:
 *
 *  - "quanto do meu faturamento já vem do app?" — que é exatamente a decisão de
 *    investir ou não no canal digital;
 *  - "o ticket do telefone é maior que o do balcão?";
 *  - "esse pedido veio de onde?", quando algo deu errado nele.
 *
 * O legado respondia parte disso com booleanos paralelos por canal — que
 * permitem estados impossíveis (dois verdadeiros) e exigem coluna nova a cada
 * canal. É o que F3-05 manda substituir por uma dimensão.
 */
class CanalDeVendaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, User, PedidoSituacao, Produto, Cliente} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $user, $situacao, $produto, $cliente];
    }

    /** O painel é atendimento interno — balcão ou telefone. */
    public function test_pedido_do_painel_e_interno(): void
    {
        [$empresa, $user, $situacao, $produto, $cliente] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/pedidos', [
                'cliente_id' => $cliente->id,
                'pedidosituacao_id' => $situacao->id,
                'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
            ])
            ->assertCreated();

        $this->assertSame(CanalVenda::INTERNO, Pedido::query()->latest('id')->first()->canal);
    }

    /**
     * Pedido antigo fica DESCONHECIDO, e isso é deliberado.
     *
     * Adivinhar a origem retroativa (por exemplo: "tem entregador e não tem
     * atendente, então veio do campo") produziria um gráfico de faturamento por
     * canal bonito e errado. "Não sei" é melhor que "provavelmente" num dado que
     * embasa decisão de investimento.
     */
    public function test_pedido_sem_canal_declarado_fica_desconhecido(): void
    {
        [$empresa, , $situacao, , $cliente] = $this->cenario();

        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $situacao->id,
        ], []);

        $this->assertSame(CanalVenda::DESCONHECIDO, $pedido->fresh()->canal);
    }

    /** Cada canal é declarado por quem cria — e chega ao banco. */
    public function test_canal_declarado_e_persistido(): void
    {
        [$empresa, , $situacao, , $cliente] = $this->cenario();

        foreach ([CanalVenda::APP_CLIENTE, CanalVenda::CAMPO, CanalVenda::CENTRAL] as $canal) {
            $pedido = app(PedidoService::class)->criar([
                'empresa_id' => $empresa->id,
                'grupo_id' => $empresa->grupo_id,
                'cliente_id' => $cliente->id,
                'pedidosituacao_id' => $situacao->id,
                'canal' => $canal->value,
            ], []);

            $this->assertSame($canal, $pedido->fresh()->canal);
        }
    }

    /**
     * A pergunta que a dimensão torna respondível — e que booleanos paralelos
     * respondiam mal.
     */
    public function test_faturamento_por_canal_e_consultavel(): void
    {
        [$empresa, , $situacao, , $cliente] = $this->cenario();

        $criar = fn (CanalVenda $canal, float $valor) => app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $situacao->id,
            'canal' => $canal->value,
            'valor_venda' => $valor,
        ], []);

        $criar(CanalVenda::APP_CLIENTE, 100);
        $criar(CanalVenda::APP_CLIENTE, 150);
        $criar(CanalVenda::INTERNO, 200);

        $porCanal = Pedido::query()
            ->selectRaw('canal, count(*) as total')
            ->groupBy('canal')
            ->pluck('total', 'canal');

        $this->assertSame(2, (int) $porCanal[CanalVenda::APP_CLIENTE->value]);
        $this->assertSame(1, (int) $porCanal[CanalVenda::INTERNO->value]);
    }

    /**
     * Guarda contra o canal ser esquecido numa porta nova.
     *
     * Declarar o canal em quatro lugares e depender de alguém lembrar do quinto
     * é como o `DESCONHECIDO` volta a crescer — silenciosamente, e só se
     * percebe quando o relatório de faturamento por canal deixa de fechar.
     *
     * Este teste varre quem chama `PedidoService::criar` e exige que cada um
     * declare um canal. O que ele NÃO faz é adivinhar qual: essa é a decisão de
     * quem escreve a porta.
     */
    public function test_toda_porta_que_cria_pedido_declara_o_canal(): void
    {
        $semCanal = [];

        foreach ($this->arquivosQueCriamPedido() as $arquivo) {
            $conteudo = (string) file_get_contents($arquivo);

            if (! str_contains($conteudo, 'CanalVenda::')) {
                $semCanal[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $arquivo);
            }
        }

        $this->assertNotEmpty($arquivos = $this->arquivosQueCriamPedido(), 'a varredura nao achou porta nenhuma');
        unset($arquivos);

        $this->assertSame([], $semCanal, implode('
', array_merge(
            ['Porta que cria pedido sem declarar o canal (F3-05):'],
            $semCanal,
            ['', 'Acrescente `CanalVenda::X->value` ao array passado a `criar`.'],
        )));
    }

    /**
     * Arquivos que chamam `PedidoService::criar`.
     *
     * @return list<string>
     */
    private function arquivosQueCriamPedido(): array
    {
        $achados = [];

        foreach (['app/Http/Controllers', 'app/Domain'] as $pasta) {
            $iterador = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($pasta)),
            );

            foreach ($iterador as $arquivo) {
                if (! $arquivo->isFile() || $arquivo->getExtension() !== 'php') {
                    continue;
                }

                $conteudo = (string) file_get_contents($arquivo->getPathname());

                // O proprio PedidoService define `criar`, nao o chama.
                if (str_contains($conteudo, 'class PedidoService')) {
                    continue;
                }

                // Exige o TIPO injetado (`PedidoService $algo`) e uma chamada
                // `criar` que nao seja `$this->criar` — sem as duas condicoes,
                // um servico que so CITA `PedidoService` num comentario, e tem
                // o proprio metodo `criar`, entraria na lista.
                $injeta = preg_match('/PedidoService\s+\$/', $conteudo) === 1;
                $chama = preg_match('/\$(?!this)[a-zA-Z_]\w*->criar\(/', $conteudo) === 1
                    || preg_match('/\$this->(pedidos|pedidoService|service)->criar\(/', $conteudo) === 1;

                if ($injeta && $chama) {
                    $achados[] = $arquivo->getPathname();
                }
            }
        }

        return $achados;
    }

    /**
     * Um canal só: `eAutoatendimento` distingue o pedido que o cliente fez
     * sozinho — que é o que muda a conversa sobre custo de atendimento.
     */
    public function test_autoatendimento_e_identificavel(): void
    {
        $this->assertTrue(CanalVenda::APP_CLIENTE->eAutoatendimento());
        $this->assertFalse(CanalVenda::INTERNO->eAutoatendimento());
        $this->assertFalse(CanalVenda::CENTRAL->eAutoatendimento());
    }
}
