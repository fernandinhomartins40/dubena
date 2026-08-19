<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Cidade;
use App\Models\Monitora\Cerca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cerca organizada por MUNICÍPIO.
 *
 * A lista plana misturava dois níveis: das 19 cercas migradas, "Turvo",
 * "Goioxim" e "Boa Ventura do São Roque" são municípios inteiros, enquanto
 * "Setor 01" a "Setor 08" são zonas dentro de Guarapuava. Quem opera em várias
 * cidades não conseguia enxergar o que é de onde.
 */
class CercaMunicipioTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa, Cidade} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true,
        ]);
        Estado::firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná', 'cod_ibge' => 41]);
        $cidade = Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Guarapuava', 'uf' => 'PR',
        ]);

        return [$user, $empresa, $cidade];
    }

    /** @return list<array{latitude: float, longitude: float}> */
    private function quadrado(): array
    {
        return [
            ['latitude' => -25.35, 'longitude' => -51.50],
            ['latitude' => -25.35, 'longitude' => -51.41],
            ['latitude' => -25.43, 'longitude' => -51.41],
            ['latitude' => -25.43, 'longitude' => -51.50],
        ];
    }

    public function test_cerca_guarda_e_devolve_o_municipio(): void
    {
        [$user, , $cidade] = $this->cenario();

        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/monitora/cercas', [
                'descricao' => 'Setor 01',
                'cidade_id' => $cidade->id,
                'pontos' => $this->quadrado(),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('monitora_cercas', ['id' => $id, 'cidade_id' => $cidade->id]);

        // A tela agrupa pelo NOME do município: um id solto não diz nada.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/monitora/cercas')
            ->assertOk()
            ->assertJsonPath('data.0.cidade', 'Guarapuava')
            ->assertJsonPath('data.0.uf', 'PR');
    }

    /**
     * Cerca sem município continua VÁLIDA.
     *
     * As 19 migradas do rastreador não têm `cidade_id` — exigir a cidade
     * invalidaria o dado que já existe. Elas aparecem em "Sem município" para
     * serem classificadas, e não escondidas.
     */
    public function test_cerca_sem_municipio_continua_valida(): void
    {
        [$user] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/monitora/cercas', [
                'descricao' => 'Área herdada', 'pontos' => $this->quadrado(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.cidade_id', null);
    }

    /**
     * A classificação sai de onde estão os CLIENTES.
     *
     * O sistema não tem a malha do IBGE; o município vem da cidade que
     * predomina entre os clientes de dentro da cerca — dado que a operação já
     * conferiu, cliente a cliente. Só pelo nome, "Setor 01" nunca seria
     * reconhecido como Guarapuava.
     */
    public function test_comando_deduz_o_municipio_pelos_clientes_de_dentro(): void
    {
        [, $empresa, $cidade] = $this->cenario();

        $cerca = Cerca::withoutTenant()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Setor 01 - CARLOS', 'ativo' => true,
        ]);
        foreach ($this->quadrado() as $i => $p) {
            DB::table('monitora_cerca_pontos')->insert([
                'cerca_id' => $cerca->id, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
                'latitude' => $p['latitude'], 'longitude' => $p['longitude'], 'ordem' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Clientes dentro do polígono, com a cidade já conferida no cadastro.
        Cliente::factory()->count(3)->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cidade_id' => $cidade->id, 'latitude' => -25.39, 'longitude' => -51.45,
        ]);

        Artisan::call('cerca:classificar-municipio', ['--aplicar' => true]);

        $this->assertSame(
            $cidade->id,
            (int) DB::table('monitora_cercas')->where('id', $cerca->id)->value('cidade_id'),
            'o nome "Setor 01" não identifica o município — a geografia identifica',
        );
    }

    /** Sem `--aplicar` o comando só simula: é rodado antes de decidir. */
    public function test_comando_e_read_only_sem_a_flag(): void
    {
        [, $empresa, $cidade] = $this->cenario();

        $cerca = Cerca::withoutTenant()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Guarapuava Centro', 'ativo' => true,
        ]);

        Artisan::call('cerca:classificar-municipio');

        $this->assertNull(
            DB::table('monitora_cercas')->where('id', $cerca->id)->value('cidade_id'),
            'a simulação gravou',
        );
        $this->assertNotSame($cidade->id, null); // usa a variável: o cadastro existe
    }
}
