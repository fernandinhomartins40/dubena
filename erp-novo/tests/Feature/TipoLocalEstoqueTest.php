<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Estoque\TipoLocalEstoque;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3-06 — o local de estoque diz que espécie de lugar é.
 *
 * `setores` tinha descrição e `ativo`, e mais nada. Três coisas de naturezas
 * diferentes conviviam na mesma lista:
 *
 *  - o depósito da revenda, que é um endereço físico;
 *  - o estoque em poder de um franqueado, criado automaticamente como
 *    "Em poder de Fulano" na primeira carga;
 *  - o que estiver a bordo de um veículo, que se move.
 *
 * A consequência aparece no seletor de "onde lançar a entrada": o operador vê
 * "Em poder de João" ao lado de "Depósito central" e pode escolher qualquer um.
 * O lançamento errado **não dá erro** — dá um saldo que não bate, descoberto no
 * inventário, quando ninguém mais liga uma coisa à outra.
 */
class TipoLocalEstoqueTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, User} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $user];
    }

    private function setor(Empresa $empresa, string $descricao, ?TipoLocalEstoque $tipo = null): Setor
    {
        return Setor::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'descricao' => $descricao,
            'tipo' => ($tipo ?? TipoLocalEstoque::DEPOSITO)->value,
        ]);
    }

    /** Sem tipo declarado, o local é um depósito — o caso comum e o mais conservador. */
    public function test_setor_nasce_deposito(): void
    {
        [$empresa] = $this->cenario();

        $novo = Setor::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->assertSame(TipoLocalEstoque::DEPOSITO, $novo->fresh()->tipo);
    }

    /**
     * O ponto da tarefa: entrada manual não entra em local de custódia.
     *
     * O que está com uma pessoa chegou lá por um movimento. Lançar entrada
     * direto ali cria mercadoria do nada num lugar que deveria ter RECEBIDO de
     * algum outro.
     */
    public function test_entrada_manual_e_recusada_em_local_de_custodia(): void
    {
        [$empresa, $user] = $this->cenario();
        $custodia = $this->setor($empresa, 'Em poder de João', TipoLocalEstoque::CUSTODIA_PESSOA);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/estoque/entrada', [
                'setor_id' => $custodia->id, 'produto_id' => $produto->id, 'quantidade' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('setor_id');
    }

    /** Veículo idem: a carga chega por transferência, não por lançamento. */
    public function test_entrada_manual_e_recusada_em_veiculo(): void
    {
        [$empresa, $user] = $this->cenario();
        $veiculo = $this->setor($empresa, 'Caminhão 1', TipoLocalEstoque::VEICULO);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/estoque/entrada', [
                'setor_id' => $veiculo->id, 'produto_id' => $produto->id, 'quantidade' => 5,
            ])
            ->assertStatus(422);
    }

    /** O caminho normal continua funcionando — senão a restrição teria travado o produto. */
    public function test_entrada_em_deposito_continua_funcionando(): void
    {
        [$empresa, $user] = $this->cenario();
        $deposito = $this->setor($empresa, 'Depósito central');
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/estoque/entrada', [
                'setor_id' => $deposito->id, 'produto_id' => $produto->id, 'quantidade' => 10,
            ])
            ->assertCreated();
    }

    /** Loja também aceita: é ponto de venda com estoque próprio, não custódia. */
    public function test_loja_aceita_entrada_direta(): void
    {
        [$empresa, $user] = $this->cenario();
        $loja = $this->setor($empresa, 'Loja centro', TipoLocalEstoque::LOJA);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/estoque/entrada', [
                'setor_id' => $loja->id, 'produto_id' => $produto->id, 'quantidade' => 3,
            ])
            ->assertCreated();
    }

    /**
     * A transferência PODE colocar mercadoria em custódia — é o caminho
     * legítimo, e é por isso que a restrição fica na porta HTTP e não no
     * `EstoqueService`.
     */
    public function test_transferencia_para_custodia_continua_permitida(): void
    {
        [$empresa] = $this->cenario();
        $deposito = $this->setor($empresa, 'Depósito');
        $custodia = $this->setor($empresa, 'Em poder de Maria', TipoLocalEstoque::CUSTODIA_PESSOA);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $servico = app(EstoqueService::class);
        $servico->entrada($deposito->id, $produto->id, 10, 5);
        $servico->transferir($deposito->id, $custodia->id, $produto->id, 4);

        $this->assertSame(
            4.0,
            (float) EstoqueSaldo::query()
                ->where('setor_id', $custodia->id)->where('produto_id', $produto->id)
                ->value('quantidade'),
        );
    }

    /** `armazens()` é o que a tela deve oferecer num seletor de lançamento. */
    public function test_escopo_de_armazens_exclui_custodia(): void
    {
        [$empresa] = $this->cenario();
        $deposito = $this->setor($empresa, 'Depósito');
        $loja = $this->setor($empresa, 'Loja', TipoLocalEstoque::LOJA);
        $this->setor($empresa, 'Em poder de João', TipoLocalEstoque::CUSTODIA_PESSOA);
        $this->setor($empresa, 'Caminhão', TipoLocalEstoque::VEICULO);

        $ids = Setor::query()->armazens()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$deposito->id, $loja->id], $ids);
    }

    /** A pergunta que a tipagem torna expressável: quanto está fora do depósito? */
    public function test_custodia_e_identificavel(): void
    {
        $this->assertTrue(TipoLocalEstoque::CUSTODIA_PESSOA->eCustodia());
        $this->assertTrue(TipoLocalEstoque::VEICULO->eCustodia());
        $this->assertFalse(TipoLocalEstoque::DEPOSITO->eCustodia());
        $this->assertFalse(TipoLocalEstoque::LOJA->eCustodia());
    }
}
