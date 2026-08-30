<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GATE da T4.8 — motivos de atraso e de não-venda.
 *
 * As colunas `pedidos.pedidomotivoatraso_id` e `pedidos.motivonaovenda_id` já
 * existiam no schema novo, mas apontando para o VAZIO: as tabelas de domínio
 * nunca foram criadas (grep por `motivonaovenda` retornava zero). O atendimento
 * não conseguia justificar um atraso, e o gestor não tinha como saber por que
 * os pedidos atrasam nem por que as vendas se perdem.
 */
class MotivosPedidoTest extends TestCase
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

    private function pedido(Empresa $empresa): Pedido
    {
        $situacao = PedidoSituacao::create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Em rota',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Cliente Teste',
            'cliente' => true,
            'ativo' => true,
        ]);

        return Pedido::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $situacao->id,
            'datahora' => now(),
            'valor_total' => 100.00,
        ]);
    }

    private function motivo(string $tabela, Empresa $empresa, string $descricao): int
    {
        return (int) DB::table($tabela)->insertGetId([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => $descricao,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cadastros_estao_registrados_e_semeados(): void
    {
        [$user] = $this->suporte();

        // Um cadastro vazio deixaria o campo obrigatório impossível de preencher
        // no primeiro dia — por isso a migration semeia os valores do legado.
        foreach (['motivos-atraso', 'motivos-nao-venda'] as $tipo) {
            $this->actingAs($user, 'sanctum')
                ->getJson("/api/admin/cadastros/{$tipo}")
                ->assertOk()
                ->assertJsonStructure(['data']);
        }
    }

    public function test_justificar_atraso_grava_o_motivo(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa);
        $motivoId = $this->motivo('pedido_motivos_atraso', $empresa, 'Acidente na pista');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/justificar-atraso", [
                'pedidomotivoatraso_id' => $motivoId,
                'justificativa' => 'BR-277 interditada por 2h',
            ])
            ->assertOk()
            ->assertJsonPath('data.pedidomotivoatraso_id', $motivoId);

        $this->assertSame($motivoId, (int) $pedido->fresh()->pedidomotivoatraso_id);
    }

    public function test_atraso_sem_motivo_e_recusado(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa);

        // O motivo é obrigatório: um atraso sem causa registrada não vira
        // informação, e é a informação que o relatório precisa.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/justificar-atraso", [])
            ->assertStatus(422);
    }

    public function test_motivo_inexistente_e_recusado(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/justificar-atraso", [
                'pedidomotivoatraso_id' => 999999,
            ])
            ->assertStatus(422);
    }

    public function test_registrar_nao_venda(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa);
        $motivoId = $this->motivo('motivos_nao_venda', $empresa, 'Cliente pesquisando preço');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/nao-venda", [
                'motivonaovenda_id' => $motivoId,
            ])
            ->assertOk()
            ->assertJsonPath('data.motivonaovenda_id', $motivoId);

        $this->assertSame($motivoId, (int) $pedido->fresh()->motivonaovenda_id);
    }

    public function test_escrita_exige_permissao(): void
    {
        [, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa);
        $motivoId = $this->motivo('pedido_motivos_atraso', $empresa, 'Trânsito');

        $semPermissao = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($semPermissao, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/justificar-atraso", [
                'pedidomotivoatraso_id' => $motivoId,
            ])
            ->assertStatus(403);
    }
}
