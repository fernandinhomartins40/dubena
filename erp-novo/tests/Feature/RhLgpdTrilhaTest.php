<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIE P2-E-11 (RH): CPF na listagem, ponto de dia fechado reescrevível e folha
 * sem trilha. A parte de permissões novas (saúde/remuneração) depende do
 * catálogo de permissões e está proposta no relatório da Rodada 3 (F).
 */
class RhLgpdTrilhaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Colaborador} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $colab = Colaborador::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'cpf' => '12345678901',
        ]);

        return [$user, $colab];
    }

    public function test_listagem_mascara_o_cpf_e_o_detalhe_mantem(): void
    {
        [$user, $colab] = $this->cenario();

        $lista = $this->actingAs($user, 'sanctum')->getJson('/api/admin/colaboradores')->assertOk();
        $this->assertSame('***.456.789-**', $lista->json('data.0.cpf'));
        $this->assertStringNotContainsString('12345678901', $lista->getContent());

        // O formulário de edição precisa do documento inteiro.
        $this->actingAs($user, 'sanctum')->getJson("/api/admin/colaboradores/{$colab->id}")
            ->assertOk()->assertJsonPath('data.cpf', '12345678901');
    }

    public function test_ponto_de_dia_fechado_exige_motivo_e_grava_o_motivo(): void
    {
        [$user, $colab] = $this->cenario();
        $ontem = now()->subDays(3)->toDateString();
        $url = "/api/admin/colaboradores/{$colab->id}/pontos";

        $this->actingAs($user, 'sanctum')->postJson($url, ['data' => $ontem, 'entrada' => '08:00'])
            ->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->assertSame(0, $colab->pontos()->count());

        $this->actingAs($user, 'sanctum')
            ->postJson($url, ['data' => $ontem, 'entrada' => '08:00', 'motivo' => 'Esqueceu de bater o ponto'])
            ->assertStatus(201);
        $ponto = $colab->pontos()->sole();
        $this->assertSame('Esqueceu de bater o ponto', $ponto->motivo_ajuste);

        // Reescrever o mesmo dia fechado sem motivo continua barrado.
        $this->actingAs($user, 'sanctum')->postJson($url, ['data' => $ontem, 'entrada' => '06:00'])
            ->assertStatus(422);
        $this->assertSame('08:00', substr((string) $ponto->fresh()->entrada, 0, 5));
    }

    public function test_ponto_do_dia_e_livre_e_futuro_e_recusado(): void
    {
        [$user, $colab] = $this->cenario();
        $url = "/api/admin/colaboradores/{$colab->id}/pontos";

        $this->actingAs($user, 'sanctum')->postJson($url, ['entrada' => '08:00'])->assertStatus(201);
        $this->actingAs($user, 'sanctum')->postJson($url, ['saida' => '17:00'])->assertStatus(201);
        $this->assertSame(1, $colab->pontos()->count());

        $this->actingAs($user, 'sanctum')
            ->postJson($url, ['data' => now()->addDay()->toDateString(), 'entrada' => '08:00'])
            ->assertStatus(422)->assertJsonValidationErrors('data');
    }

    public function test_janela_de_edicao_livre_e_configuracao_da_empresa(): void
    {
        [$user, $colab] = $this->cenario();
        EmpresaConfig::query()->create([
            'empresa_id' => $colab->empresa_id, 'dados' => ['rh' => ['ponto_dias_edicao_livre' => 7]],
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/pontos", ['data' => now()->subDays(5)->toDateString(), 'entrada' => '08:00'])
            ->assertStatus(201);
        $this->assertNull($colab->pontos()->sole()->motivo_ajuste);
    }

    public function test_comissao_e_ponto_deixam_trilha(): void
    {
        [$user, $colab] = $this->cenario();

        $comissao = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/comissoes", ['tipo_comissao' => 1, 'percentual' => 5])
            ->assertStatus(201)->json('data.id');
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/colaboradores/{$colab->id}/comissoes/{$comissao}", ['tipo_comissao' => 1, 'percentual' => 9])
            ->assertOk();

        $alteracao = AuditLog::query()->where('entidade', 'colaborador_comissoes')
            ->where('entidade_id', $comissao)->where('acao', 'atualizado')->sole();
        $this->assertSame($user->id, (int) $alteracao->user_id);
        $this->assertEquals(9, (float) $alteracao->depois['percentual']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/pontos", ['entrada' => '08:00'])->assertStatus(201);
        $this->assertTrue(AuditLog::query()->where('entidade', 'colaborador_pontos')->where('acao', 'criado')->exists());

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/exames", ['realizado_em' => now()->toDateString(), 'resultado' => 'apto'])
            ->assertStatus(201);
        $this->assertTrue(AuditLog::query()->where('entidade', 'colaborador_exames')->exists());
    }

    public function test_remover_familiar_deixa_trilha_e_nao_remove_de_outro_colaborador(): void
    {
        [$user, $colab] = $this->cenario();
        $outro = Colaborador::factory()->create(['empresa_id' => $colab->empresa_id, 'grupo_id' => $colab->grupo_id]);
        $fam = $outro->familias()->create(['nome' => 'Filho do outro']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/colaboradores/{$colab->id}/familia/{$fam->id}")->assertNotFound();
        $this->assertNotNull($fam->fresh());

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/colaboradores/{$outro->id}/familia/{$fam->id}")->assertOk();
        $this->assertTrue(AuditLog::query()->where('entidade', 'colaborador_familias')->where('acao', 'excluido')->exists());
    }
}
