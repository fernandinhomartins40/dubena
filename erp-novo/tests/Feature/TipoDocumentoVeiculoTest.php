<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Frota\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GATE da T4.5 — tipos de documento de VEÍCULO (CRLV, seguro, ANTT).
 *
 * O legado tem o cadastro; o novo gravava `veiculos/{id}/documentos` sem domínio
 * de valores por trás — campo livre, cada operador digitando de um jeito, e
 * nenhum relatório confiável de "quais veículos estão com o CRLV vencido".
 *
 * ⚠️ Não confundir com o tipo da GESTÃO DOCUMENTAL (`Documentotipo` no legado):
 * são módulos distintos, e a auditoria destaca essa armadilha nominal.
 */
class TipoDocumentoVeiculoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    public function test_cadastro_esta_registrado_e_responde(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/tipos-documento-veiculo')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_crud_completo_pelo_registry(): void
    {
        [$user] = $this->suporte();

        $criado = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/tipos-documento-veiculo', [
                'descricao' => 'Licença ambiental',
                'exige_validade' => true,
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/cadastros/tipos-documento-veiculo/{$criado['id']}", [
                'descricao' => 'Licença ambiental (IAP)',
                'exige_validade' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.descricao', 'Licença ambiental (IAP)');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/cadastros/tipos-documento-veiculo/{$criado['id']}")
            ->assertOk();
    }

    public function test_documento_de_veiculo_aceita_o_tipo_cadastrado(): void
    {
        [$user, $empresa] = $this->suporte();

        $tipoId = (int) DB::table('tipos_documento_veiculo')->insertGetId([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'CRLV',
            'exige_validade' => true,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'placa' => 'XYZ9K88',
            'descricao' => 'Caminhão',
            'km_atual' => 0,
            'ativo' => true,
        ]);

        // É o vínculo que faltava: antes o documento só tinha texto livre.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/veiculos/{$veiculo->id}/documentos", [
                'tipo' => 'CRLV',
                'tipo_documento_id' => $tipoId,
                'numero' => '2026-0001',
                'vencimento' => '2027-03-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo_documento_id', $tipoId);
    }

    public function test_rejeita_tipo_inexistente(): void
    {
        [$user, $empresa] = $this->suporte();
        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'placa' => 'AAA1B22', 'descricao' => 'Van', 'km_atual' => 0, 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/veiculos/{$veiculo->id}/documentos", [
                'tipo' => 'CRLV',
                'tipo_documento_id' => 999999,
            ])
            ->assertStatus(422);
    }

    public function test_cadastro_e_escopado_por_grupo(): void
    {
        [$user, $empresa] = $this->suporte();

        DB::table('tipos_documento_veiculo')->insert([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Do meu grupo',
            'exige_validade' => false, 'ativo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Tipo de OUTRO grupo não pode aparecer na listagem.
        $outra = Empresa::factory()->create();
        DB::table('tipos_documento_veiculo')->insert([
            'grupo_id' => $outra->grupo_id, 'descricao' => 'De outro grupo',
            'exige_validade' => false, 'ativo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $descricoes = array_column(
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/admin/cadastros/tipos-documento-veiculo')
                ->assertOk()->json('data'),
            'descricao'
        );

        $this->assertContains('Do meu grupo', $descricoes);
        $this->assertNotContains('De outro grupo', $descricoes);
    }
}
