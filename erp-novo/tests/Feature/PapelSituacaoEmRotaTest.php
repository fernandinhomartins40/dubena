<?php

namespace Tests\Feature;

use App\Domain\Mobile\EntregaService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PapelSituacao;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F3-04A — "saiu para entrega" vira papel declarado, não palavra procurada.
 *
 * `EntregaService::iniciarRota()` procurava a situação de deslocamento por
 * `LIKE '%saiu%' OR '%rota%' OR '%caminho%'` e, não achando, CRIAVA
 * "Saiu para entrega" para conseguir continuar.
 *
 * Isso funciona para uma revenda: a que escreveu essas palavras. Para a segunda
 * — "Em trânsito", "Despachado", ou qualquer coisa em espanhol — a busca falha,
 * e o sistema cadastra uma situação concorrente que não aparece na configuração
 * que o cliente montou. O relatório dele passa a somar dois nomes para o mesmo
 * momento, e ninguém liga uma coisa à outra.
 *
 * Estes testes usam nomenclatura NÃO portuguesa de propósito: é o cenário que o
 * gate da F3 exige e o que a heurística antiga não sobrevive.
 */
class PapelSituacaoEmRotaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, User} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $entregador = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $entregador];
    }

    private function situacao(Empresa $empresa, string $descricao, ?PapelSituacao $papel = null): PedidoSituacao
    {
        return PedidoSituacao::query()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => $descricao,
            'efeito' => EfeitoPedido::PENDENTE->value,
            'papel' => ($papel ?? PapelSituacao::NENHUM)->value,
            'ativo' => true,
        ]);
    }

    private function pedidoPendente(Empresa $empresa, User $entregador, PedidoSituacao $situacao): Pedido
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return Pedido::query()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $situacao->id,
            'entregador_user_id' => $entregador->id,
            'data' => now(),
            'total' => 100,
        ]);
    }

    /**
     * O cenário que a heurística antiga não sobrevivia: a situação de rota tem
     * nome em espanhol e nenhuma das três palavras procuradas.
     */
    public function test_situacao_em_outro_idioma_funciona_pelo_papel(): void
    {
        [$empresa, $entregador] = $this->cenario();

        $aguardando = $this->situacao($empresa, 'Pendiente de despacho');
        $emRota = $this->situacao($empresa, 'En reparto', PapelSituacao::EM_ROTA);
        $pedido = $this->pedidoPendente($empresa, $entregador, $aguardando);

        $resultado = app(EntregaService::class)->iniciarRota($empresa->id, $entregador->id);

        $this->assertSame(1, $resultado['iniciados']);
        $this->assertSame($emRota->id, (int) $pedido->fresh()->pedidosituacao_id);
    }

    /**
     * O comportamento que se removeu: NÃO cadastrar situação para continuar.
     *
     * Um erro que diz o que configurar custa um minuto. Uma situação duplicada
     * criada em silêncio contamina o relatório do cliente para sempre.
     */
    public function test_sem_papel_declarado_falha_em_vez_de_criar_situacao(): void
    {
        [$empresa, $entregador] = $this->cenario();

        $aguardando = $this->situacao($empresa, 'Aguardando separação');
        $this->pedidoPendente($empresa, $entregador, $aguardando);

        $antes = PedidoSituacao::query()->count();

        try {
            app(EntregaService::class)->iniciarRota($empresa->id, $entregador->id);
            $this->fail('deveria ter recusado por falta de configuração');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Saiu para entrega', $e->getMessage());
        }

        $this->assertSame($antes, PedidoSituacao::query()->count(), 'nenhuma situação foi inventada');
    }

    /**
     * A armadilha inversa: uma situação chamada "saiu do estoque" casaria com
     * `LIKE '%saiu%'` e viraria destino de entrega. Com papel declarado, o nome
     * deixa de decidir.
     */
    public function test_descricao_parecida_sem_papel_nao_e_escolhida(): void
    {
        [$empresa, $entregador] = $this->cenario();

        $this->situacao($empresa, 'Saiu do estoque para conferência');
        $aguardando = $this->situacao($empresa, 'Aguardando');
        $this->pedidoPendente($empresa, $entregador, $aguardando);

        $this->expectException(ValidationException::class);
        app(EntregaService::class)->iniciarRota($empresa->id, $entregador->id);
    }

    /** Idempotente: pedido já em rota não é movido de novo. */
    public function test_iniciar_rota_duas_vezes_nao_duplica(): void
    {
        [$empresa, $entregador] = $this->cenario();

        $aguardando = $this->situacao($empresa, 'Aguardando');
        $this->situacao($empresa, 'Despachado', PapelSituacao::EM_ROTA);
        $this->pedidoPendente($empresa, $entregador, $aguardando);

        $servico = app(EntregaService::class);

        $this->assertSame(1, $servico->iniciarRota($empresa->id, $entregador->id)['iniciados']);
        $this->assertSame(0, $servico->iniciarRota($empresa->id, $entregador->id)['iniciados']);
    }

    /**
     * Papel exclusivo: duas situações EM_ROTA deixariam a ação com dois alvos,
     * e a escolha voltaria a ser um desempate arbitrário por id.
     */
    public function test_papel_em_rota_e_exclusivo_no_grupo(): void
    {
        [$empresa, $user] = $this->cenario();
        $this->situacao($empresa, 'Despachado', PapelSituacao::EM_ROTA);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/pedidos/situacoes', [
                'descricao' => 'A caminho',
                'efeito' => EfeitoPedido::PENDENTE->value,
                'papel' => PapelSituacao::EM_ROTA->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('papel');
    }

    /** Situação sem papel especial continua livre para existir em qualquer número. */
    public function test_papel_nenhum_nao_e_exclusivo(): void
    {
        [$empresa, $user] = $this->cenario();
        $this->situacao($empresa, 'Aguardando');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/pedidos/situacoes', [
                'descricao' => 'Em conferência',
                'efeito' => EfeitoPedido::PENDENTE->value,
            ])
            ->assertCreated();
    }

    /** Editar a própria situação mantendo o papel não pode colidir consigo mesma. */
    public function test_editar_mantendo_o_proprio_papel_e_permitido(): void
    {
        [$empresa, $user] = $this->cenario();
        $emRota = $this->situacao($empresa, 'Despachado', PapelSituacao::EM_ROTA);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/pedidos/situacoes/{$emRota->id}", [
                'descricao' => 'Despachado ao cliente',
                'efeito' => EfeitoPedido::PENDENTE->value,
                'papel' => PapelSituacao::EM_ROTA->value,
            ])
            ->assertOk();
    }

    /** Toda situação nasce sem papel: é afirmação de quem configura, não default. */
    public function test_situacao_nasce_sem_papel(): void
    {
        [$empresa] = $this->cenario();

        $nova = PedidoSituacao::query()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Nova',
            'efeito' => EfeitoPedido::PENDENTE->value,
            'ativo' => true,
        ]);

        $this->assertSame(PapelSituacao::NENHUM, $nova->fresh()->papel);
    }
}
