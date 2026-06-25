<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F04 — cadastros de apoio do RH antes ausentes (cargos, parentescos, tipos-exame)
 * agora resolvem via /cadastros/{tipo}, escopados por grupo. Centraliza a cauda de
 * CRUDs no mecanismo de apoio genérico.
 */
class CadastroApoioRhTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    public function test_cargos_crud_com_extra_salario(): void
    {
        [$user] = $this->suporte();

        $criado = $this->actingAs($user, 'sanctum')->postJson('/api/admin/cadastros/cargos', [
            'descricao' => 'Entregador', 'salario_base' => 1800.50,
        ])->assertCreated()->json('data');

        $this->assertSame('Entregador', $criado['descricao']);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/cadastros/cargos')
            ->assertOk()->assertJsonFragment(['descricao' => 'Entregador']);
    }

    public function test_parentescos_crud(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/cadastros/parentescos', ['descricao' => 'Cônjuge'])
            ->assertCreated();
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/cadastros/parentescos')
            ->assertOk()->assertJsonFragment(['descricao' => 'Cônjuge']);
    }

    public function test_tipos_exame_aceita_alias_e_extra_admissional(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/cadastros/tipos-exame', [
            'descricao' => 'ASO Admissional', 'admissional' => true,
        ])->assertCreated()->assertJsonPath('data.admissional', true);

        // alias legado (tipoexame) resolve para o mesmo cadastro.
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/cadastros/tipoexame')
            ->assertOk()->assertJsonFragment(['descricao' => 'ASO Admissional']);
    }

    public function test_cadastro_apoio_isola_por_grupo(): void
    {
        [$userA] = $this->suporte();
        [$userB] = $this->suporte();

        $this->actingAs($userA, 'sanctum')->postJson('/api/admin/cadastros/parentescos', ['descricao' => 'Filho(a) A'])->assertCreated();

        $dataB = $this->actingAs($userB, 'sanctum')->getJson('/api/admin/cadastros/parentescos')->assertOk()->json('data');
        $this->assertEmpty($dataB, 'Cadastro de apoio vazou entre grupos.');
    }
}
