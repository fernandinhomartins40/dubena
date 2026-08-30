<?php

namespace Tests\Feature;

use App\Domain\Frota\VeiculoService;
use App\Models\Empresa;
use App\Models\Frota\Veiculo;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da T4.7 — sub-operações que viraram somente-leitura na migração.
 *
 * Quatro casos com o mesmo padrão: o legado tem `Route::resource` completo, o
 * novo expõe só GET. Sem POST o operador não consegue lançar um recesso, uma
 * comissão ou a troca de óleo que acabou de fazer — precisa voltar ao legado,
 * o que inviabiliza aposentá-lo.
 *
 * O teste mais importante aqui é `test_registrar_troca_de_oleo_zera_o_alerta`:
 * gravar a linha não basta. O alerta lê `veiculos.km_ultima_troca_oleo`, não a
 * última linha da tabela — registrar sem atualizar esse campo deixaria o aviso
 * de "troca vencida" aceso para sempre, e um alerta que nunca apaga é um alerta
 * que o operador aprende a ignorar.
 */
class EscritaRestauradaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    private function colaborador(Empresa $empresa): Colaborador
    {
        return Colaborador::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Maria da Silva',
            'ativo' => true,
        ]);
    }

    private function veiculo(Empresa $empresa, array $extra = []): Veiculo
    {
        return Veiculo::create(array_merge([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'placa' => 'ABC1D23',
            'descricao' => 'Caminhão de entrega',
            'km_atual' => 10000,
            'ativo' => true,
        ], $extra));
    }

    // ───────────────────────────── RH: recessos ─────────────────────────────

    public function test_ciclo_completo_de_recesso(): void
    {
        [$user, $empresa] = $this->suporte();
        $c = $this->colaborador($empresa);

        $criado = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$c->id}/recessos", [
                'tipo' => 'ferias',
                'inicio' => '2026-09-01',
                'fim' => '2026-09-30',
                'observacao' => 'Férias regulamentares',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/colaboradores/{$c->id}/recessos")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/colaboradores/{$c->id}/recessos/{$criado['id']}", [
                'tipo' => 'ferias',
                'inicio' => '2026-09-01',
                'fim' => '2026-09-15',
            ])
            ->assertOk()
            ->assertJsonPath('data.fim', '2026-09-15T00:00:00.000000Z');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/colaboradores/{$c->id}/recessos/{$criado['id']}")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/colaboradores/{$c->id}/recessos")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_recesso_com_fim_antes_do_inicio_e_recusado(): void
    {
        [$user, $empresa] = $this->suporte();
        $c = $this->colaborador($empresa);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$c->id}/recessos", [
                'inicio' => '2026-09-30',
                'fim' => '2026-09-01',
            ])
            ->assertStatus(422);
    }

    public function test_recesso_de_um_dia_e_valido(): void
    {
        [$user, $empresa] = $this->suporte();
        $c = $this->colaborador($empresa);

        // `after_or_equal` e não `after`: folga de um dia só é caso real.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$c->id}/recessos", [
                'inicio' => '2026-09-01', 'fim' => '2026-09-01',
            ])
            ->assertCreated();
    }

    public function test_nao_edita_recesso_de_outro_colaborador(): void
    {
        [$user, $empresa] = $this->suporte();
        $a = $this->colaborador($empresa);
        $b = Colaborador::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'João', 'ativo' => true,
        ]);

        $recesso = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$a->id}/recessos", [
                'inicio' => '2026-09-01', 'fim' => '2026-09-05',
            ])->json('data');

        // O id do recesso vem do cliente: o findOrFail é sobre a RELAÇÃO.
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/colaboradores/{$b->id}/recessos/{$recesso['id']}", [
                'inicio' => '2026-10-01', 'fim' => '2026-10-05',
            ])
            ->assertStatus(404);
    }

    // ──────────────────────────── RH: comissões ─────────────────────────────

    public function test_ciclo_completo_de_comissao(): void
    {
        [$user, $empresa] = $this->suporte();
        $c = $this->colaborador($empresa);

        $criada = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$c->id}/comissoes", [
                'percentual' => 5.5,
                'data_inicio' => '2026-01-01',
                'ativo' => true,
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/colaboradores/{$c->id}/comissoes/{$criada['id']}", [
                'percentual' => 7.0, 'ativo' => true,
            ])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/colaboradores/{$c->id}/comissoes/{$criada['id']}")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/colaboradores/{$c->id}/comissoes")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_percentual_acima_de_100_e_recusado(): void
    {
        [$user, $empresa] = $this->suporte();
        $c = $this->colaborador($empresa);

        // 5000 em vez de 50,00 é erro de digitação plausível — e sai caro na folha.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$c->id}/comissoes", ['percentual' => 5000])
            ->assertStatus(422);
    }

    // ───────────────────────── Frota: troca de óleo ─────────────────────────

    public function test_registrar_troca_de_oleo_zera_o_alerta(): void
    {
        [$user, $empresa] = $this->suporte();
        // Intervalo de 10.000 km e última troca em 0 → com km_atual 10.000, o
        // alerta está ACESO.
        $v = $this->veiculo($empresa, ['km_troca_oleo' => 10000, 'km_ultima_troca_oleo' => 0]);

        $antes = app(VeiculoService::class)->alertaTrocaOleo($v);
        $this->assertTrue($antes['precisa_trocar'], 'o cenário precisa começar com o alerta aceso');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/veiculos/{$v->id}/trocas-oleo", [
                'km' => 10500, 'valor' => 320.50, 'observacao' => 'Óleo 15W40 + filtro',
            ])
            ->assertCreated();

        // O ponto da tarefa: o alerta APAGA. Gravar a linha sem atualizar
        // `km_ultima_troca_oleo` deixaria o aviso aceso para sempre.
        $depois = app(VeiculoService::class)->alertaTrocaOleo($v->fresh());
        $this->assertFalse($depois['precisa_trocar'], 'a troca registrada tem de zerar o alerta');
        $this->assertSame(10500, (int) $v->fresh()->km_ultima_troca_oleo);
        $this->assertSame(10500, (int) $v->fresh()->km_atual);
    }

    public function test_troca_com_km_menor_que_o_atual_e_recusada(): void
    {
        [$user, $empresa] = $this->suporte();
        $v = $this->veiculo($empresa);   // km_atual = 10000

        // Hodômetro não anda para trás — mesma regra do abastecimento.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/veiculos/{$v->id}/trocas-oleo", ['km' => 9000])
            ->assertStatus(422);
    }

    // ───────────────────────────── Frota: pneus ─────────────────────────────

    public function test_ciclo_completo_de_pneu(): void
    {
        [$user, $empresa] = $this->suporte();
        $v = $this->veiculo($empresa);

        $pneu = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/veiculos/{$v->id}/pneus", [
                'posicao' => 'dianteiro-esquerdo',
                'marca' => 'Michelin',
                'data_instalacao' => '2026-08-01',
                'km_instalacao' => 10000,
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/veiculos/{$v->id}/pneus/{$pneu['id']}", [
                'posicao' => 'dianteiro-esquerdo', 'marca' => 'Pirelli',
            ])
            ->assertOk()
            ->assertJsonPath('data.marca', 'Pirelli');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/veiculos/{$v->id}/pneus/{$pneu['id']}")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/veiculos/{$v->id}/pneus")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_escrita_de_frota_exige_permissao(): void
    {
        [, $empresa] = $this->suporte();
        $v = $this->veiculo($empresa);

        $semPermissao = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($semPermissao, 'sanctum')
            ->postJson("/api/admin/veiculos/{$v->id}/trocas-oleo", ['km' => 20000])
            ->assertStatus(403);
    }
}
