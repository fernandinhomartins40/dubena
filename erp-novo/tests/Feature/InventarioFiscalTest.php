<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Fiscal\InventarioFiscalService;
use App\Domain\Fiscal\SpedFiscalService;
use App\Domain\Tenant\TenantContext;
use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Fiscal\InventarioFiscal;
use App\Models\Permission;
use App\Models\Produto\Produto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inventário fiscal — o estoque declarado ao fisco (SPED Fiscal, Bloco H).
 *
 * No legado era a tela "Inventário" do menu SPED. No sistema novo não existia:
 * a aba da SPA postava no endpoint da contagem física e tomava 422, e o SPED
 * montava o Bloco H com o saldo do instante da geração.
 */
class InventarioFiscalTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Produto $p13;

    private Produto $p45;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $base = ['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id];
        $this->p13 = Produto::factory()->create($base + ['descricao' => 'Botijão P13']);
        $this->p45 = Produto::factory()->create($base + ['descricao' => 'Cilindro P45']);
    }

    /** @return array<string, mixed> */
    private function corpo(array $extra = []): array
    {
        return $extra + [
            'mes_entrega' => '2026-02-01',
            'data_inventario' => '2025-12-31',
            'itens' => [
                ['produto_id' => $this->p13->id, 'quantidade' => 120, 'valor_unitario' => 92.5],
                ['produto_id' => $this->p45->id, 'quantidade' => 7.5, 'valor_unitario' => 310.3333],
            ],
        ];
    }

    /** @param list<string> $chaves */
    private function usuarioCom(array $chaves): User
    {
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $role = Role::create(['grupo_id' => $this->empresa->grupo_id, 'nome' => 'Papel '.uniqid()]);
        $role->permissions()->sync(collect($chaves)->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id));
        $user->roles()->attach($role->id, ['empresa_id' => $this->empresa->id]);

        return $user;
    }

    public function test_grava_o_que_foi_declarado_e_congela_a_descricao(): void
    {
        $id = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo())
            ->assertStatus(201)->json('data.id');

        $inv = InventarioFiscal::query()->with('itens')->findOrFail($id);
        $this->assertSame('2026-02-01', $inv->mes_entrega->toDateString());
        $this->assertSame('2025-12-31', $inv->data_inventario->toDateString());
        $this->assertSame('01', $inv->motivo);
        // 120 × 92,50 = 11.100,00 ; 7,5 × 310,3333 = 2.327,49975 → 2.327,50
        $this->assertEqualsWithDelta(13427.50, (float) $inv->valor_total, 0.001);
        $this->assertSame($this->user->id, (int) $inv->user_id);

        // Renomear o produto depois não muda o documento declarado.
        $this->p13->update(['descricao' => 'GLP 13kg']);
        $this->assertSame('Botijão P13', $inv->itens->firstWhere('produto_id', $this->p13->id)->descricao_snapshot);
    }

    /** É um documento: gravar não pode mexer em saldo de estoque. */
    public function test_gravar_inventario_fiscal_nao_move_estoque(): void
    {
        $setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        app(EstoqueService::class)->entrada($setor->id, $this->p13->id, 40, 90.0);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo())->assertStatus(201);

        $saldo = EstoqueSaldo::withoutGlobalScopes()->where('setor_id', $setor->id)->where('produto_id', $this->p13->id)->value('quantidade');
        $this->assertEqualsWithDelta(40.0, (float) $saldo, 0.001);
    }

    public function test_mes_de_entrega_e_normalizado_para_o_primeiro_dia(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo(['mes_entrega' => '2026-02-19']))
            ->assertStatus(201);

        $this->assertSame('2026-02-01', InventarioFiscal::query()->firstOrFail()->mes_entrega->toDateString());
    }

    /** Dois H005 com o mesmo motivo na mesma entrega: o fisco não saberia qual vale. */
    public function test_recusa_segundo_inventario_do_mesmo_motivo_na_mesma_entrega(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/fiscal/inventarios', $this->corpo())->assertStatus(201);
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo(['mes_entrega' => '2026-02-28']))
            ->assertUnprocessable()->assertJsonValidationErrors('mes_entrega');

        // Outro motivo na mesma entrega é legítimo (ex.: mudança de tributação).
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo(['motivo' => '02']))->assertStatus(201);

        $this->assertSame(2, InventarioFiscal::query()->count());
    }

    public function test_validacoes_da_porta(): void
    {
        $recusa = fn (array $corpo, string $campo) => $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $corpo)
            ->assertUnprocessable()->assertJsonValidationErrors($campo);

        // Inventário de data posterior ao mês em que é entregue: campos trocados.
        $recusa($this->corpo(['mes_entrega' => '2025-11-01']), 'data_inventario');
        $recusa($this->corpo(['motivo' => '09']), 'motivo');
        $recusa($this->corpo(['itens' => []]), 'itens');
        $recusa($this->corpo(['itens' => [['produto_id' => $this->p13->id, 'quantidade' => -1, 'valor_unitario' => 1]]]), 'itens.0.quantidade');
        $recusa($this->corpo(['itens' => [
            ['produto_id' => $this->p13->id, 'quantidade' => 1, 'valor_unitario' => 1],
            ['produto_id' => $this->p13->id, 'quantidade' => 2, 'valor_unitario' => 1],
        ]]), 'itens');

        $this->assertSame(0, InventarioFiscal::query()->count());
    }

    public function test_produto_de_outra_empresa_e_recusado(): void
    {
        $outra = Empresa::factory()->create();
        $alheio = Produto::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo(['itens' => [
                ['produto_id' => $alheio->id, 'quantidade' => 1, 'valor_unitario' => 1],
            ]]))->assertUnprocessable();

        $this->assertSame(0, InventarioFiscal::withoutGlobalScopes()->count());
    }

    /** O valor unitário é CUSTO: sem a permissão de campo, nem lê nem grava. */
    public function test_exige_permissao_de_custo_alem_da_fiscal(): void
    {
        $semCusto = $this->usuarioCom(['fiscal.view', 'fiscal.edit']);
        $this->actingAs($semCusto, 'sanctum')->getJson('/api/admin/fiscal/inventarios')->assertForbidden();
        $this->actingAs($semCusto, 'sanctum')->getJson('/api/admin/fiscal/inventarios/sugestao')->assertForbidden();
        $this->actingAs($semCusto, 'sanctum')->postJson('/api/admin/fiscal/inventarios', $this->corpo())->assertForbidden();

        $soLeitura = $this->usuarioCom(['fiscal.view', 'produto.campo.custo.view']);
        $this->actingAs($soLeitura, 'sanctum')->getJson('/api/admin/fiscal/inventarios')->assertOk();
        $this->actingAs($soLeitura, 'sanctum')->postJson('/api/admin/fiscal/inventarios', $this->corpo())->assertForbidden();

        $this->assertSame(0, InventarioFiscal::query()->count());
    }

    public function test_excluir_exige_motivo_e_deixa_rastro(): void
    {
        $id = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo())->json('data.id');

        $this->actingAs($this->user, 'sanctum')->deleteJson("/api/admin/fiscal/inventarios/{$id}")->assertUnprocessable();
        $this->assertSame(1, InventarioFiscal::query()->count());

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/admin/fiscal/inventarios/{$id}", ['motivo' => 'Quantidade do P45 digitada errada'])
            ->assertOk();

        $this->assertSame(0, InventarioFiscal::query()->count());
        $linha = AuditLog::query()->where('entidade', 'inventarios_fiscais')->where('acao', 'excluido')->firstOrFail();
        $this->assertSame('Quantidade do P45 digitada errada', $linha->motivo);
        $this->assertSame($id, (int) $linha->entidade_id);
    }

    public function test_inventario_de_outra_empresa_nao_aparece_nem_e_excluido(): void
    {
        $outra = Empresa::factory()->create();
        $produtoAlheio = Produto::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);
        $alheio = app(InventarioFiscalService::class)->criar(
            $outra->id,
            ['mes_entrega' => '2026-02-01', 'data_inventario' => '2025-12-31'],
            [['produto_id' => $produtoAlheio->id, 'quantidade' => 1, 'valor_unitario' => 1]],
        );

        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/fiscal/inventarios')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/admin/fiscal/inventarios/{$alheio->id}", ['motivo' => 'Tentativa'])
            ->assertNotFound();

        $this->assertSame(1, InventarioFiscal::withoutGlobalScopes()->whereKey($alheio->id)->count());
    }

    /** A sugestão soma os setores e pondera o custo pela quantidade de cada um. */
    public function test_sugestao_soma_setores_e_pondera_o_custo(): void
    {
        $base = ['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id];
        $a = Setor::factory()->create($base);
        $b = Setor::factory()->create($base);
        $estoque = app(EstoqueService::class);
        $estoque->entrada($a->id, $this->p13->id, 90, 100.0);
        $estoque->entrada($b->id, $this->p13->id, 10, 200.0);

        $data = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/fiscal/inventarios/sugestao')->assertOk()->json('data');

        // Só o que tem saldo: o P45, sem estoque, fica de fora.
        $this->assertSame([$this->p13->id], array_column($data, 'produto_id'));
        $this->assertEqualsWithDelta(100.0, $data[0]['quantidade'], 0.001);
        // (90×100 + 10×200) / 100 = 110 — a média simples daria 150.
        $this->assertEqualsWithDelta(110.0, $data[0]['valor_unitario'], 0.001);
    }

    /**
     * O ponto fiscal: com inventário declarado, o Bloco H leva a data, as
     * quantidades e os valores DECLARADOS — não o saldo do instante da geração.
     */
    public function test_sped_usa_o_inventario_declarado_na_entrega(): void
    {
        // Saldo de AGORA bem diferente do declarado, para a diferença aparecer.
        $setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        app(EstoqueService::class)->entrada($setor->id, $this->p13->id, 999, 1.0);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo())->assertStatus(201);

        $sped = app(SpedFiscalService::class)->gerar($this->empresa, '2026-02-01', '2026-02-28');
        $linhas = explode("\r\n", $sped);

        $this->assertContains('|H005|31122025|13427,50|01|', $linhas);
        $this->assertContains("|H010|{$this->p13->id}|UN|120,000|92,500000|11100,00|0||||", $linhas);
        $this->assertContains("|H010|{$this->p45->id}|UN|7,500|310,333300|2327,50|0||||", $linhas);
        $this->assertSame(1, count(array_filter($linhas, fn ($l) => str_starts_with($l, '|H005|'))));

        // Todo COD_ITEM do inventário está no 0200, mesmo sem nota no período.
        foreach ([$this->p13, $this->p45] as $p) {
            $this->assertNotEmpty(
                array_filter($linhas, fn ($l) => str_starts_with($l, "|0200|{$p->id}|")),
                "produto {$p->id} citado no H010 e ausente do 0200",
            );
        }

        // O total declarado bate com a soma dos itens do arquivo.
        $soma = 0.0;
        foreach ($linhas as $l) {
            if (str_starts_with($l, '|H010|')) {
                $soma += (float) str_replace(',', '.', explode('|', $l)[6]);
            }
        }
        $this->assertEqualsWithDelta(13427.50, $soma, 0.001);
    }

    /** O inventário vai na escrituração do MÊS DE ENTREGA, e só nela. */
    public function test_sped_de_outro_mes_nao_leva_o_inventario_declarado(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/fiscal/inventarios', $this->corpo())->assertStatus(201);

        // Dezembro é o mês do inventário, não o da entrega.
        $dezembro = app(SpedFiscalService::class)->gerar($this->empresa, '2025-12-01', '2025-12-31');
        $marco = app(SpedFiscalService::class)->gerar($this->empresa, '2026-03-01', '2026-03-31');

        // Asserta o VALOR e os itens declarados, não a data: o Bloco H derivado
        // de dezembro também sai datado de 31/12 (fim do período), e não é o
        // inventário declarado.
        foreach ([$dezembro, $marco] as $sped) {
            $this->assertStringNotContainsString('13427,50', $sped);
            $this->assertStringNotContainsString('|H010|', $sped);
        }
    }

    /** Sem inventário declarado nada muda no arquivo que já era gerado. */
    public function test_sem_inventario_declarado_o_sped_segue_como_era(): void
    {
        $sped = app(SpedFiscalService::class)->gerar($this->empresa, '2026-02-01', '2026-02-28');

        $this->assertStringContainsString('|H001|0|', $sped);
        $this->assertStringContainsString('|H005|28022026|0,00|01|', $sped);
    }
}
