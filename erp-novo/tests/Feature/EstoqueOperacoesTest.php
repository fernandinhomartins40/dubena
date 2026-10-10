<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Estoque\TipoLocalEstoque;
use App\Domain\Tenant\TenantContext;
use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FASE C11 — requisições + inventário/estoque físico. Efetivação passa pelo
 * EstoqueService (acerto/transferência) → saldo auditável.
 */
class EstoqueOperacoesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Produto $produto;

    private Setor $deposito;

    private Setor $loja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->produto = Produto::create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'P13', 'preco_venda' => 110, 'custo_medio' => 90, 'ativo' => true]);
        $this->deposito = Setor::create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Depósito', 'ativo' => true]);
        $this->loja = Setor::create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Loja', 'ativo' => true]);
        app(EstoqueService::class)->entrada($this->deposito->id, $this->produto->id, 100, 90.0);
    }

    public function test_requisicao_com_atendimento_transfere_estoque(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/requisicoes', [
                'setor_origem_id' => $this->deposito->id,
                'setor_destino_id' => $this->loja->id,
                'produto_id' => $this->produto->id,
                'quantidade' => 30,
                'atender' => true,
            ])->assertStatus(201)->assertJsonPath('data.situacao', 'atendida');

        $dep = EstoqueSaldo::withoutGlobalScopes()->where('setor_id', $this->deposito->id)->where('produto_id', $this->produto->id)->value('quantidade');
        $loja = EstoqueSaldo::withoutGlobalScopes()->where('setor_id', $this->loja->id)->where('produto_id', $this->produto->id)->value('quantidade');
        $this->assertEqualsWithDelta(70.0, (float) $dep, 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $loja, 0.001);
    }

    public function test_inventario_efetivar_ajusta_saldo_para_o_contado(): void
    {
        // Conta 80 (sistema tem 100) → após efetivar, saldo = 80.
        $resp = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/inventarios', [
                'setor_id' => $this->deposito->id,
                'itens' => [['produto_id' => $this->produto->id, 'quantidade_contada' => 80]],
            ])->assertStatus(201);

        $invId = $resp->json('data.id');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/estoque/fisico/{$invId}/efetivar")
            ->assertOk()->assertJsonPath('data.situacao', 'efetivado');

        $saldo = EstoqueSaldo::withoutGlobalScopes()->where('setor_id', $this->deposito->id)->where('produto_id', $this->produto->id)->value('quantidade');
        $this->assertEqualsWithDelta(80.0, (float) $saldo, 0.001);
        // Invariante preservada: Σ histórico = saldo.
        $this->assertEqualsWithDelta(80.0, app(EstoqueService::class)->saldoDerivado($this->deposito->id, $this->produto->id), 0.001);
    }

    public function test_abrir_fechamento_registra(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/fechamentos/abrir', [
                'setor_id' => $this->deposito->id,
                'produto_id' => $this->produto->id,
            ])->assertStatus(201);

        $this->assertEqualsWithDelta(100.0, (float) $resp->json('data.saldo_final'), 0.001);
    }

    public function test_entrada_recusa_setor_e_produto_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create();
        $setorAlheio = Setor::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);
        $produtoAlheio = Produto::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/entrada', [
                'setor_id' => $setorAlheio->id,
                'produto_id' => $produtoAlheio->id,
                'quantidade' => 10,
            ])->assertUnprocessable();

        $this->assertSame(0, EstoqueSaldo::withoutGlobalScopes()->where('empresa_id', $outra->id)->count());
    }

    private function saldoDeposito(): float
    {
        return (float) EstoqueSaldo::withoutGlobalScopes()
            ->where('setor_id', $this->deposito->id)->where('produto_id', $this->produto->id)->value('quantidade');
    }

    /**
     * O que a tela de Acerto faz: lançamento manual com motivo e chave. Reenviar
     * (rede caiu depois que o servidor gravou, duplo clique) não pode lançar de
     * novo — entrada manual duplicada não dá erro, dá saldo que não bate.
     */
    public function test_lancamento_manual_reenviado_com_a_mesma_chave_nao_duplica(): void
    {
        $envio = fn () => $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'acerto-0001-abcdef'])
            ->postJson('/api/admin/estoque/entrada', [
                'setor_id' => $this->deposito->id, 'produto_id' => $this->produto->id,
                'quantidade' => 7, 'motivo' => 'Sobra encontrada na conferência',
            ])->assertStatus(201);

        $primeiro = $envio()->json('data.id');
        $segundo = $envio()->json('data.id');

        $this->assertSame($primeiro, $segundo, 'o reenvio devolve o movimento já gravado');
        $this->assertEqualsWithDelta(107.0, $this->saldoDeposito(), 0.001);
        // Uma ação humana = uma linha na trilha, mesmo com o reenvio.
        $this->assertSame(1, AuditLog::query()->where('acao', 'lancamento_manual')->count());
    }

    /** Sem a chave o comportamento antigo se mantém: cada envio é um lançamento. */
    public function test_lancamento_manual_sem_chave_continua_lancando_a_cada_envio(): void
    {
        foreach ([1, 2] as $_) {
            $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/admin/estoque/saida', [
                    'setor_id' => $this->deposito->id, 'produto_id' => $this->produto->id, 'quantidade' => 5,
                ])->assertStatus(201);
        }

        $this->assertEqualsWithDelta(90.0, $this->saldoDeposito(), 0.001);
    }

    /** O motivo digitado era descartado; agora fica consultável na trilha. */
    public function test_motivo_do_lancamento_manual_fica_na_trilha(): void
    {
        $id = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/saida', [
                'setor_id' => $this->deposito->id, 'produto_id' => $this->produto->id,
                'quantidade' => 2, 'motivo' => 'Botijão avariado',
            ])->assertStatus(201)->json('data.id');

        $linha = AuditLog::query()->where('acao', 'lancamento_manual')->firstOrFail();
        $this->assertSame('Botijão avariado', $linha->motivo);
        $this->assertSame($id, (int) $linha->entidade_id);
    }

    public function test_chave_de_idempotencia_malformada_e_recusada(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'x y'])
            ->postJson('/api/admin/estoque/entrada', [
                'setor_id' => $this->deposito->id, 'produto_id' => $this->produto->id, 'quantidade' => 1,
            ])->assertUnprocessable();

        $this->assertEqualsWithDelta(100.0, $this->saldoDeposito(), 0.001);
    }

    /**
     * Requisição criada sem `atender` ficava pendente para sempre: não havia
     * porta para atendê-la depois.
     */
    public function test_requisicao_pendente_pode_ser_atendida_depois(): void
    {
        $id = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/requisicoes', [
                'setor_destino_id' => $this->loja->id, 'produto_id' => $this->produto->id, 'quantidade' => 12,
            ])->assertStatus(201)->assertJsonPath('data.situacao', 'pendente')->json('data.id');

        // Sem origem não há de onde tirar: recusa em vez de atender pela metade.
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/estoque/requisicoes/{$id}/atender")
            ->assertUnprocessable();
        $this->assertEqualsWithDelta(100.0, $this->saldoDeposito(), 0.001);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/estoque/requisicoes/{$id}/atender", ['setor_origem_id' => $this->deposito->id])
            ->assertOk()->assertJsonPath('data.situacao', 'atendida');
        $this->assertEqualsWithDelta(88.0, $this->saldoDeposito(), 0.001);

        // Atender de novo não transfere outra vez.
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/estoque/requisicoes/{$id}/atender")
            ->assertUnprocessable();
        $this->assertEqualsWithDelta(88.0, $this->saldoDeposito(), 0.001);
    }

    public function test_requisicao_de_outra_empresa_nao_e_atendida(): void
    {
        $outra = Empresa::factory()->create();
        $id = DB::table('estoque_requisicoes')->insertGetId([
            'empresa_id' => $outra->id, 'setor_destino_id' => $this->loja->id, 'produto_id' => $this->produto->id,
            'quantidade' => 1, 'situacao' => 'pendente', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/estoque/requisicoes/{$id}/atender", ['setor_origem_id' => $this->deposito->id])
            ->assertNotFound();
    }

    /** As listas levam os nomes: a tela mostrava só ids (ou nada). */
    public function test_listas_trazem_setor_e_produto(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/estoque/requisicoes', [
            'setor_origem_id' => $this->deposito->id, 'setor_destino_id' => $this->loja->id,
            'produto_id' => $this->produto->id, 'quantidade' => 1,
        ])->assertStatus(201);
        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/estoque/fisico', [
            'setor_id' => $this->deposito->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade_contada' => 99]],
        ])->assertStatus(201);
        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/estoque/fechamentos', [
            'setor_id' => $this->deposito->id, 'produto_id' => $this->produto->id, 'data_fechamento' => now()->toDateString(),
        ])->assertStatus(201);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/estoque/requisicoes')
            ->assertOk()
            ->assertJsonPath('data.0.produto.descricao', 'P13')
            ->assertJsonPath('data.0.setor_origem.descricao', 'Depósito')
            ->assertJsonPath('data.0.setor_destino.descricao', 'Loja');
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/estoque/fisico')
            ->assertOk()
            ->assertJsonPath('data.0.setor.descricao', 'Depósito')
            ->assertJsonPath('data.0.itens.0.produto.descricao', 'P13');
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/estoque/fechamentos')
            ->assertOk()
            ->assertJsonPath('data.0.setor.descricao', 'Depósito')
            ->assertJsonPath('data.0.produto.descricao', 'P13');
    }

    /** F3-06: o seletor de entrada só oferece onde se pode lançar entrada. */
    public function test_lookup_de_setores_filtra_armazens(): void
    {
        Setor::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Em poder de João', 'tipo' => TipoLocalEstoque::CUSTODIA_PESSOA, 'ativo' => true,
        ]);

        $todos = $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/lookups/setores')
            ->assertOk()->json('data');
        $armazens = $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/lookups/setores?armazens=1')
            ->assertOk()->json('data');

        // A transferência precisa da lista inteira: custódia é destino válido.
        $this->assertContains('Em poder de João', array_column($todos, 'label'));
        $this->assertNotContains('Em poder de João', array_column($armazens, 'label'));
        $this->assertEqualsCanonicalizing(['Depósito', 'Loja'], array_column($armazens, 'label'));
    }
}
