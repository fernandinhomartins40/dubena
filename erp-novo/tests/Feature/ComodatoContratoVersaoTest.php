<?php

namespace Tests\Feature;

use App\Domain\Satelite\ComodatoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoContrato;
use App\Models\Satelite\ComodatoMovimento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O contrato pelo lado da API: versões, recibo e o guarda do encerrado.
 */
class ComodatoContratoVersaoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa,2:Cliente,3:Produto} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Mercado Central',
            'cnpj' => '11222333000181',
            'cliente' => true,
        ]);
        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Vasilhame P13',
            'vasilhame_retornavel' => true,
            'ativo' => true,
        ]);

        return [$user, $empresa, $cliente, $produto];
    }

    private function emprestar(User $u, Empresa $e, Cliente $c, Produto $p, float $qtd = 5): Comodato
    {
        return app(ComodatoService::class)->emprestar([
            'empresa_id' => $e->id,
            'grupo_id' => $e->grupo_id,
            'cliente_id' => $c->id,
            'produto_id' => $p->id,
            'quantidade' => $qtd,
        ], $u->id);
    }

    public function test_devolucao_parcial_pela_api_e_o_detalhe_traz_extrato(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->emprestar($user, $e, $c, $p);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/comodatos/{$comodato->id}/devolver", [
                'quantidade' => 2,
                'observacao' => 'Recebido no balcão',
            ])
            ->assertOk()
            ->assertJsonPath('data.situacao', 'PARCIAL');

        $detalhe = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/comodatos/{$comodato->id}")
            ->assertOk();

        $detalhe->assertJsonPath('data.em_posse', 3);
        // Empréstimo + devolução.
        $this->assertCount(2, $detalhe->json('data.movimentos'));
        // v1 (emissão) + v2 (pós-devolução).
        $this->assertCount(2, $detalhe->json('data.contratos'));
    }

    public function test_segunda_via_da_versao_assinada_continua_disponivel(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->emprestar($user, $e, $c, $p);

        app(ComodatoService::class)->devolver($comodato, 2, $user->id);

        // A versão 1 é o papel que o cliente assinou dizendo 5 unidades. Ela
        // precisa continuar imprimível mesmo depois de a posse mudar.
        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/comodatos/{$comodato->id}/contrato?versao=1")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/comodatos/{$comodato->id}/contrato?versao=2")
            ->assertOk();
    }

    public function test_versao_inexistente_da_404(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->emprestar($user, $e, $c, $p);

        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/comodatos/{$comodato->id}/contrato?versao=9")
            ->assertNotFound();
    }

    public function test_recibo_da_devolucao_sai_para_o_cliente(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->emprestar($user, $e, $c, $p);

        app(ComodatoService::class)->devolver($comodato, 2, $user->id);
        $mov = ComodatoMovimento::where('comodato_id', $comodato->id)
            ->where('tipo', ComodatoMovimento::DEVOLUCAO)->sole();

        $r = $this->actingAs($user, 'sanctum')
            ->get("/api/admin/comodatos/{$comodato->id}/movimentos/{$mov->id}/recibo")
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    public function test_recibo_de_movimento_de_outro_comodato_da_404(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $a = $this->emprestar($user, $e, $c, $p);
        $b = $this->emprestar($user, $e, $c, $p);

        app(ComodatoService::class)->devolver($a, 1, $user->id);
        $mov = ComodatoMovimento::where('comodato_id', $a->id)
            ->where('tipo', ComodatoMovimento::DEVOLUCAO)->sole();

        // O id do movimento não pode virar chave de acesso a comodato alheio.
        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/comodatos/{$b->id}/movimentos/{$mov->id}/recibo")
            ->assertNotFound();
    }

    /**
     * O ETL trouxe 745 comodatos do legado com situação `ENCERRADO`, que o
     * código não conhecia — o guarda barrava só `DEVOLVIDO`, então todos os 745
     * imprimiam contrato afirmando uma posse encerrada.
     */
    public function test_comodato_encerrado_do_legado_nao_gera_contrato(): void
    {
        [$user, $e, $c, $p] = $this->cenario();

        $comodato = Comodato::create([
            'empresa_id' => $e->id,
            'grupo_id' => $e->grupo_id,
            'cliente_id' => $c->id,
            'produto_id' => $p->id,
            'quantidade' => 4,
            'quantidade_devolvida' => 0,
            'situacao' => 'ENCERRADO',
            'data_emprestimo' => now()->subYear()->toDateString(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/comodatos/{$comodato->id}/contrato")
            ->assertStatus(422);
    }

    public function test_estorno_exige_permissao_propria(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->emprestar($user, $e, $c, $p);
        app(ComodatoService::class)->devolver($comodato, 2, $user->id);
        $mov = ComodatoMovimento::where('comodato_id', $comodato->id)
            ->where('tipo', ComodatoMovimento::DEVOLUCAO)->sole();

        // Usuário com edit mas sem `comodato.estornar` não desfaz entrega.
        $comum = User::factory()->semPapel()->create([
            'empresa_id' => $e->id,
            'grupo_id' => $e->grupo_id,
        ]);

        $this->actingAs($comum, 'sanctum')
            ->postJson("/api/admin/comodatos/{$comodato->id}/movimentos/{$mov->id}/estornar")
            ->assertForbidden();
    }

    public function test_reemitir_sem_saldo_bloqueia(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->emprestar($user, $e, $c, $p);
        app(ComodatoService::class)->devolver($comodato, 5, $user->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/comodatos/{$comodato->id}/reemitir")
            ->assertStatus(422);
    }

    public function test_marcar_contrato_assinado(): void
    {
        [$user, $e, $c, $p] = $this->cenario();
        $comodato = $this->emprestar($user, $e, $c, $p);
        $versao = $comodato->id;

        $contrato = ComodatoContrato::where('comodato_id', $comodato->id)->sole();
        $this->assertNull($contrato->assinado_em);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/comodatos/{$versao}/contratos/{$contrato->id}/assinado")
            ->assertOk();

        $this->assertNotNull($contrato->refresh()->assinado_em);
    }
}
