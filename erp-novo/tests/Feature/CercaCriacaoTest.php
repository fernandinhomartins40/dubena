<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Criar cerca do zero — o caso do revendedor NOVO.
 *
 * O relato do dono foi que "novos revendedores que não têm dados não conseguem
 * criar as cercas". Esta classe fixa que o caminho funciona sem nenhum dado
 * herdado: empresa recém-criada, nenhuma cerca, nenhum setor.
 *
 * A parte visual (mapa centralizado na empresa, busca de endereço, marcadores
 * numerados nos vértices) não dá para testar aqui — mas o contrato do backend,
 * que é o que impediria de salvar, dá.
 */
class CercaCriacaoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa} */
    private function revendaNova(): array
    {
        $empresa = Empresa::factory()->create(['razao_social' => 'Revenda Nova Ltda']);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    /** @return list<array{latitude: float, longitude: float}> */
    private function quadrado(): array
    {
        return [
            ['latitude' => -23.55, 'longitude' => -46.63],
            ['latitude' => -23.55, 'longitude' => -46.60],
            ['latitude' => -23.58, 'longitude' => -46.60],
            ['latitude' => -23.58, 'longitude' => -46.63],
        ];
    }

    public function test_revenda_sem_nenhum_dado_cria_a_primeira_cerca(): void
    {
        [$user, $empresa] = $this->revendaNova();

        $this->assertSame(0, DB::table('monitora_cercas')->count(), 'o cenário exige base zerada');

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/monitora/cercas', [
                'descricao' => 'Zona Centro',
                'cor' => '#FF6200',
                'pontos' => $this->quadrado(),
            ])
            ->assertCreated();

        $id = $resp->json('data.id');
        $this->assertDatabaseHas('monitora_cercas', [
            'id' => $id,
            'empresa_id' => $empresa->id,
            'descricao' => 'Zona Centro',
            'cor' => '#FF6200',
        ]);
        $this->assertSame(4, DB::table('monitora_cerca_pontos')->where('cerca_id', $id)->count());
    }

    /**
     * A ORDEM dos vértices é o desenho: trocar dois pontos vira um polígono
     * cruzado, e o geofencing passa a testar uma área que não existe.
     */
    public function test_ordem_dos_vertices_e_preservada(): void
    {
        [$user] = $this->revendaNova();

        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/monitora/cercas', [
                'descricao' => 'Zona Sul', 'pontos' => $this->quadrado(),
            ])
            ->assertCreated()
            ->json('data.id');

        $lngs = DB::table('monitora_cerca_pontos')
            ->where('cerca_id', $id)->orderBy('ordem')->pluck('longitude')
            ->map(fn ($v) => (float) $v)->all();

        $this->assertSame([-46.63, -46.6, -46.6, -46.63], $lngs);
    }

    /** Polígono precisa de área: dois pontos são uma reta, não uma cerca. */
    public function test_recusa_poligono_com_menos_de_tres_pontos(): void
    {
        [$user] = $this->revendaNova();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/monitora/cercas', [
                'descricao' => 'Reta',
                'pontos' => array_slice($this->quadrado(), 0, 2),
            ])
            ->assertStatus(422);
    }

    /** Editar substitui o traçado por inteiro — não mistura pontos velhos com novos. */
    public function test_editar_substitui_o_poligono(): void
    {
        [$user] = $this->revendaNova();

        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/monitora/cercas', [
                'descricao' => 'Zona Norte', 'pontos' => $this->quadrado(),
            ])
            ->assertCreated()
            ->json('data.id');

        // Redesenhada com um triângulo.
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/monitora/cercas/{$id}", [
                'descricao' => 'Zona Norte (ajustada)',
                'pontos' => array_slice($this->quadrado(), 0, 3),
            ])
            ->assertOk();

        $this->assertSame(
            3,
            DB::table('monitora_cerca_pontos')->where('cerca_id', $id)->count(),
            'sobraram vértices do traçado anterior — o polígono ficaria cruzado',
        );
    }

    public function test_cerca_de_outra_empresa_nao_e_acessivel(): void
    {
        [$user] = $this->revendaNova();
        [$outro] = $this->revendaNova();

        $id = $this->actingAs($outro, 'sanctum')
            ->postJson('/api/admin/monitora/cercas', [
                'descricao' => 'Da outra revenda', 'pontos' => $this->quadrado(),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/monitora/cercas/{$id}", [
                'descricao' => 'Invasão', 'pontos' => $this->quadrado(),
            ])
            ->assertNotFound();
    }
}
