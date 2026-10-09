<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\Setor;
use App\Models\Permission;
use App\Models\Produto\Produto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transferência de estoque pela API, no formato que a TELA envia.
 *
 * A tela mandava `origemsetor_id`/`destinosetor_id`/`itens[]`, nomes que o
 * endpoint nunca aceitou: toda transferência pela interface tomava 422, e
 * nenhum teste exercitava o formato da tela — só o de um produto por chamada.
 */
class TransferenciaEstoqueApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Setor $origem;

    private Setor $destino;

    private Produto $p1;

    private Produto $p2;

    protected function setUp(): void
    {
        parent::setUp();
        $empresa = Empresa::factory()->create();
        $this->user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Estoquista']);
        $role->permissions()->sync(collect(['estoque.view', 'estoque.edit'])
            ->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id));
        $this->user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        $this->origem = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $this->destino = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $this->p1 = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $this->p2 = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $estoque = app(EstoqueService::class);
        $estoque->entrada($this->origem->id, $this->p1->id, 50, 10, empresaEsperada: $empresa->id);
        $estoque->entrada($this->origem->id, $this->p2->id, 30, 10, empresaEsperada: $empresa->id);
    }

    private function saldo(Setor $setor, Produto $produto): float
    {
        return app(EstoqueService::class)->saldoDerivado($setor->id, $produto->id);
    }

    private function carga(): array
    {
        return [
            'setor_origem_id' => $this->origem->id,
            'setor_destino_id' => $this->destino->id,
            'itens' => [
                ['produto_id' => $this->p1->id, 'quantidade' => 5],
                ['produto_id' => $this->p2->id, 'quantidade' => 3],
            ],
        ];
    }

    public function test_carga_com_varios_itens_no_formato_da_tela(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/transferencias', $this->carga())
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsWithDelta(45, $this->saldo($this->origem, $this->p1), 0.001);
        $this->assertEqualsWithDelta(5, $this->saldo($this->destino, $this->p1), 0.001);
        $this->assertEqualsWithDelta(27, $this->saldo($this->origem, $this->p2), 0.001);
        $this->assertEqualsWithDelta(3, $this->saldo($this->destino, $this->p2), 0.001);
    }

    public function test_reenvio_com_a_mesma_chave_nao_move_de_novo(): void
    {
        $chave = '0f8fad5b-d9cb-469f-a165-70867728950e';
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->user, 'sanctum')
                ->withHeader('Idempotency-Key', $chave)
                ->postJson('/api/admin/estoque/transferencias', $this->carga())
                ->assertCreated();
        }

        $this->assertEqualsWithDelta(45, $this->saldo($this->origem, $this->p1), 0.001, 'o reenvio moveu a mercadoria de novo');
        $this->assertEqualsWithDelta(5, $this->saldo($this->destino, $this->p1), 0.001);
        $this->assertSame(4, EstoqueHistorico::query()->where('origem', 'transferencia')->count());
    }

    public function test_chaves_diferentes_sao_transferencias_diferentes(): void
    {
        // A chave identifica o ENVIO, não o conteúdo: duas cargas iguais de
        // propósito são duas transferências.
        foreach (['aaaaaaaa-0001', 'aaaaaaaa-0002'] as $chave) {
            $this->actingAs($this->user, 'sanctum')
                ->withHeader('Idempotency-Key', $chave)
                ->postJson('/api/admin/estoque/transferencias', $this->carga())
                ->assertCreated();
        }

        $this->assertEqualsWithDelta(40, $this->saldo($this->origem, $this->p1), 0.001);
    }

    public function test_carga_e_atomica_um_item_sem_saldo_desfaz_todos(): void
    {
        $carga = $this->carga();
        $carga['itens'][1]['quantidade'] = 999; // p2 só tem 30

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/transferencias', $carga)
            ->assertStatus(422);

        $this->assertEqualsWithDelta(50, $this->saldo($this->origem, $this->p1), 0.001, 'o primeiro item foi transferido sozinho');
        $this->assertSame(0, EstoqueHistorico::query()->where('origem', 'transferencia')->count());
    }

    public function test_chave_com_formato_invalido_e_recusada(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', "x'; drop")
            ->postJson('/api/admin/estoque/transferencias', $this->carga())
            ->assertStatus(422);

        $this->assertEqualsWithDelta(50, $this->saldo($this->origem, $this->p1), 0.001);
    }
}
