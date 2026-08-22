<?php

namespace Tests\Feature;

use App\Domain\Identidade\IdentificarOuCriarCliente;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fila de revisão de cadastros duplicados — a retaguarda do motor de identidade.
 *
 * O que se garante: o par chega na fila sem travar a venda, a decisão humana
 * consolida ou separa, e a fila não cruza empresa.
 */
class ClienteRevisaoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true,
        ]);

        return [$user, $empresa];
    }

    /** Cria o par suspeito pelo caminho real (telefone igual, nomes distintos). */
    private function parSuspeito(Empresa $empresa): void
    {
        $svc = app(IdentificarOuCriarCliente::class);
        $svc->executar((int) $empresa->id, (int) $empresa->grupo_id, [
            'nome' => 'Jeann Ricardo de Goes', 'telefone' => '42991045566',
        ], 'entregador');
        $svc->executar((int) $empresa->id, (int) $empresa->grupo_id, [
            'nome' => 'Karem Francieli Calixto', 'telefone' => '42991045566',
        ], 'entregador');
    }

    public function test_fila_lista_os_pares_pendentes_com_os_dois_lados(): void
    {
        [$user, $empresa] = $this->suporte();
        $this->parSuspeito($empresa);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes/revisoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.situacao', 'pendente')
            ->assertJsonPath('data.0.escore', 60)
            ->assertJsonPath('meta.pendentes', 1)
            // Os dois lados vêm juntos: é a comparação que permite decidir.
            ->assertJsonStructure(['data' => [['cliente' => ['id', 'nome', 'telefones'], 'candidato' => ['id', 'nome']]]]);
    }

    public function test_consolidar_pela_fila_funde_e_fecha_o_par(): void
    {
        [$user, $empresa] = $this->suporte();
        $this->parSuspeito($empresa);

        $revisao = \App\Models\Cliente\ClienteRevisao::query()->firstOrFail();
        $manterId = min((int) $revisao->cliente_id, (int) $revisao->candidato_id);
        $absorvidoId = max((int) $revisao->cliente_id, (int) $revisao->candidato_id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/clientes/revisoes/{$revisao->id}/consolidar", ['principal_id' => $manterId])
            ->assertOk();

        $this->assertDatabaseHas('cliente_revisoes', ['id' => $revisao->id, 'situacao' => 'consolidado']);
        $this->assertDatabaseHas('cliente_vinculos', [
            'cliente_id' => $absorvidoId, 'principal_id' => $manterId,
        ]);
        // O absorvido continua existindo, desativado.
        $this->assertDatabaseHas('clientes', ['id' => $absorvidoId, 'ativo' => false]);
    }

    /** Família dividindo telefone: fecha o par sem tocar nos cadastros. */
    public function test_descartar_marca_pessoas_diferentes_sem_alterar_cadastros(): void
    {
        [$user, $empresa] = $this->suporte();
        $this->parSuspeito($empresa);

        $revisao = \App\Models\Cliente\ClienteRevisao::query()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/clientes/revisoes/{$revisao->id}/descartar", [
                'observacao' => 'Mae e filha, mesma casa',
            ])->assertOk();

        $this->assertDatabaseHas('cliente_revisoes', [
            'id' => $revisao->id, 'situacao' => 'descartado', 'observacao' => 'Mae e filha, mesma casa',
        ]);
        // Nenhum cadastro foi desativado nem fundido.
        $this->assertSame(2, Cliente::query()->where('ativo', true)->count());
        $this->assertDatabaseCount('cliente_vinculos', 0);
    }

    public function test_fila_nao_cruza_empresa(): void
    {
        [, $empresaA] = $this->suporte();
        [$userB] = $this->suporte();

        $this->parSuspeito($empresaA);

        $this->actingAs($userB, 'sanctum')->getJson('/api/admin/clientes/revisoes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/clientes/revisoes')->assertStatus(403);
    }

    /** A tela de cadastro consulta candidatos ANTES de criar mais um. */
    public function test_sugestoes_apontam_cadastro_parecido(): void
    {
        [$user, $empresa] = $this->suporte();

        app(IdentificarOuCriarCliente::class)->executar((int) $empresa->id, (int) $empresa->grupo_id, [
            'nome' => 'Vicente Baroni', 'telefone' => '42988096620',
        ], 'admin');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/clientes/sugestoes?nome=Vicente+Barone&telefone=42988096620')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Vicente Baroni')
            ->assertJsonPath('data.0.confianca', 'alta');
    }
}
