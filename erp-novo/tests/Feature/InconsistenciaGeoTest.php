<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Cidade;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GATE da T4.1 — a fila de inconsistências geográficas precisa ESVAZIAR.
 *
 * O detector de duplicatas foi portado do legado, mas a ação que resolve o par
 * (`ignorarRua`/`ignorarBairro`) não. O efeito é a lacuna mais insidiosa da
 * auditoria: a tela existe, parece pronta, e não substitui a do legado — o
 * operador abre, confere, conclui "essas duas ruas são mesmo diferentes" e não
 * tem como registrar isso. Os mesmos falsos positivos voltam para sempre.
 *
 * O teste central aqui é `test_ciclo_fechado_*`: listar N → ignorar 1 → listar
 * N-1. É ele que prova que a fila é uma fila, e não um relatório.
 */
class InconsistenciaGeoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa,2:Cidade} */
    private function cenario(): array
    {
        Estado::firstOrCreate(['uf' => 'SP'], ['descricao' => 'São Paulo', 'cod_ibge' => 35]);

        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        $cidade = Cidade::create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Guarapuava',
            'uf' => 'SP',
            'cod_ibge' => 4109401,
        ]);

        return [$user, $empresa, $cidade];
    }

    /** Cria um par de ruas quase idêntico (dispara o detector). */
    private function parDeRuas(int $grupoId, int $cidadeId, string $a, string $b): array
    {
        $ids = [];
        foreach ([$a, $b] as $descricao) {
            $ids[] = (int) DB::table('ruas')->insertGetId([
                'grupo_id' => $grupoId,
                'cidade_id' => $cidadeId,
                'descricao' => $descricao,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    public function test_detector_encontra_o_par_quase_identico(): void
    {
        [$user, , $cidade] = $this->cenario();
        $this->parDeRuas($user->grupo_id, $cidade->id, 'Rua das Flores', 'Rua das Flore');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/inconsistencias?tipo=ruas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'rua');
    }

    public function test_ciclo_fechado_ignorar_tira_o_par_da_fila(): void
    {
        [$user, , $cidade] = $this->cenario();
        [$idA, $idB] = $this->parDeRuas($user->grupo_id, $cidade->id, 'Rua das Flores', 'Rua das Flore');

        // 1) A fila tem 1 par.
        $antes = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/inconsistencias?tipo=ruas')
            ->assertOk()->json('data');
        $this->assertCount(1, $antes);

        // 2) O operador resolve: são ruas distintas mesmo.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/inconsistencias/ignorar', [
                'tipo' => 'rua',
                'item_id' => $idA,
                'item_ignorado_id' => $idB,
                'motivo' => 'Conferido: são ruas diferentes, em quadras distintas.',
            ])
            ->assertOk()
            ->assertJsonPath('data.novo', true);

        // 3) A fila ESVAZIA — o ponto inteiro da tarefa.
        $depois = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/inconsistencias?tipo=ruas')
            ->assertOk()->json('data');
        $this->assertCount(0, $depois, 'o par ignorado não pode voltar à fila');
    }

    public function test_ordem_do_par_nao_importa(): void
    {
        [$user, , $cidade] = $this->cenario();
        [$idA, $idB] = $this->parDeRuas($user->grupo_id, $cidade->id, 'Av Brasil', 'Av Brasill');

        // Ignora na ordem (B, A) — invertida em relação à que o detector devolve.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/inconsistencias/ignorar', [
                'tipo' => 'rua', 'item_id' => $idB, 'item_ignorado_id' => $idA,
            ])->assertOk();

        // Sem a normalização do par, (A,B) e (B,A) seriam registros distintos e
        // o par voltaria à fila pela outra ordem.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/inconsistencias?tipo=ruas')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_ignorar_e_idempotente(): void
    {
        [$user, , $cidade] = $this->cenario();
        [$idA, $idB] = $this->parDeRuas($user->grupo_id, $cidade->id, 'Rua Marechal Deodoro', 'Rua Marechal Deodor');

        $payload = ['tipo' => 'rua', 'item_id' => $idA, 'item_ignorado_id' => $idB];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/inconsistencias/ignorar', $payload)
            ->assertOk()->assertJsonPath('data.novo', true);

        // Repetir não pode duplicar nem estourar o UNIQUE com erro 500.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/inconsistencias/ignorar', $payload)
            ->assertOk()->assertJsonPath('data.novo', false);

        $this->assertSame(1, DB::table('geo_pares_ignorados')->count());
    }

    public function test_reconsiderar_devolve_o_par_a_fila(): void
    {
        [$user, , $cidade] = $this->cenario();
        // Descrições longas o bastante para a similaridade passar do limiar
        // (0,85): "Rua B"/"Rua BB" ficaria abaixo e o detector nem as pareia.
        [$idA, $idB] = $this->parDeRuas($user->grupo_id, $cidade->id, 'Rua Sete de Setembro', 'Rua Sete de Setembr');
        $payload = ['tipo' => 'rua', 'item_id' => $idA, 'item_ignorado_id' => $idB];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/inconsistencias/ignorar', $payload)->assertOk();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/inconsistencias?tipo=ruas')->assertJsonCount(0, 'data');

        // Errar ao ignorar precisa ser reversível.
        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/admin/cadastros/inconsistencias/ignorar', $payload)
            ->assertOk()->assertJsonPath('data.reconsiderado', true);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/inconsistencias?tipo=ruas')->assertJsonCount(1, 'data');
    }

    public function test_nao_ignora_par_de_outro_grupo(): void
    {
        [$user, , $cidade] = $this->cenario();
        [$idA] = $this->parDeRuas($user->grupo_id, $cidade->id, 'Rua Presidente Vargas', 'Rua Presidente Varga');

        // Rua de OUTRO grupo: o id vem do cliente, então o service confere.
        $outra = Empresa::factory()->create();
        $cidadeOutra = Cidade::create([
            'grupo_id' => $outra->grupo_id, 'descricao' => 'Outra', 'uf' => 'SP', 'cod_ibge' => 3550308,
        ]);
        $idExterno = (int) DB::table('ruas')->insertGetId([
            'grupo_id' => $outra->grupo_id, 'cidade_id' => $cidadeOutra->id,
            'descricao' => 'Rua Externa', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/inconsistencias/ignorar', [
                'tipo' => 'rua', 'item_id' => $idA, 'item_ignorado_id' => $idExterno,
            ])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('geo_pares_ignorados')->count());
    }

    public function test_escrita_exige_permissao_de_edicao(): void
    {
        [$user, $empresa, $cidade] = $this->cenario();
        [$idA, $idB] = $this->parDeRuas($user->grupo_id, $cidade->id, 'Rua Duque de Caxias', 'Rua Duque de Caxia');

        // Usuário com apenas `cliente.view` (o que basta para o GET) não pode
        // gravar: a escrita exige `cliente.edit`.
        $leitor = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);
        $papel = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Leitor']);
        $papel->permissions()->sync([Permission::firstOrCreate(['chave' => 'cliente.view'])->id]);
        $leitor->roles()->attach($papel->id, ['empresa_id' => $empresa->id]);

        $this->actingAs($leitor->fresh(), 'sanctum')
            ->getJson('/api/admin/cadastros/inconsistencias?tipo=ruas')
            ->assertOk();

        $this->actingAs($leitor->fresh(), 'sanctum')
            ->postJson('/api/admin/cadastros/inconsistencias/ignorar', [
                'tipo' => 'rua', 'item_id' => $idA, 'item_ignorado_id' => $idB,
            ])
            ->assertStatus(403);
    }

    public function test_rejeita_par_com_o_mesmo_id(): void
    {
        [$user, , $cidade] = $this->cenario();
        [$idA] = $this->parDeRuas($user->grupo_id, $cidade->id, 'Rua Barao do Rio Branco', 'Rua Barao do Rio Branc');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/inconsistencias/ignorar', [
                'tipo' => 'rua', 'item_id' => $idA, 'item_ignorado_id' => $idA,
            ])
            ->assertStatus(422);
    }
}
