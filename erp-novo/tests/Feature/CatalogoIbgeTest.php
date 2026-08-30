<?php

namespace Tests\Feature;

use App\Domain\Geografico\CatalogoIbge;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Catálogo oficial de municípios do IBGE.
 *
 * Existe por causa de um risco FISCAL medido na base de produção: `cod_ibge`
 * vira `cMun`/`cMunFG` no XML da NF-e, e 5 das 105 cidades tinham código
 * inventado (999999999), zerado, um CEP (89874000) ou o código de outra cidade.
 */
class CatalogoIbgeTest extends TestCase
{
    use RefreshDatabase;

    private function ambiente(): Empresa
    {
        Estado::firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná', 'cod_ibge' => 41]);
        Estado::firstOrCreate(['uf' => 'SC'], ['descricao' => 'Santa Catarina', 'cod_ibge' => 42]);

        MunicipioIbge::insert([
            ['cod_ibge' => 4109401, 'nome' => 'Guarapuava', 'uf' => 'PR', 'nome_busca' => 'guarapuava', 'cod_uf' => 41],
            ['cod_ibge' => 4104808, 'nome' => 'Cascavel', 'uf' => 'PR', 'nome_busca' => 'cascavel', 'cod_uf' => 41],
            ['cod_ibge' => 4105805, 'nome' => 'Campo Largo', 'uf' => 'PR', 'nome_busca' => 'campo largo', 'cod_uf' => 41],
            ['cod_ibge' => 4205506, 'nome' => 'Fraiburgo', 'uf' => 'SC', 'nome_busca' => 'fraiburgo', 'cod_uf' => 42],
        ]);

        return Empresa::factory()->create();
    }

    public function test_corrige_codigo_ibge_inventado(): void
    {
        $empresa = $this->ambiente();

        // Caso real da base: Guaratuba com 999999999.
        $cidade = Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Guarapuava',
            'uf' => 'PR',
            'cod_ibge' => 999999999,
        ]);

        $catalogo = app(CatalogoIbge::class);
        $catalogo->aplicar($catalogo->conciliar());

        $cidade->refresh();
        $this->assertSame(4109401, $cidade->cod_ibge);
        $this->assertSame(4109401, $cidade->municipio_ibge);
    }

    public function test_rejeita_codigo_valido_que_e_de_outra_uf(): void
    {
        $empresa = $this->ambiente();

        // Caso real: "CAMPO LARGO"/PR carregando 4205506, que é Fraiburgo/SC.
        // O código existe no catálogo — aceitar só porque existe perpetuaria o erro.
        $cidade = Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Campo Largo',
            'uf' => 'PR',
            'cod_ibge' => 4205506,
        ]);

        $catalogo = app(CatalogoIbge::class);
        $catalogo->aplicar($catalogo->conciliar());

        $cidade->refresh();
        $this->assertSame(4105805, $cidade->cod_ibge, 'Deveria ter caído para o Campo Largo do PR.');
    }

    public function test_resolve_distrito_pelo_municipio_entre_parenteses(): void
    {
        $empresa = $this->ambiente();

        // "Palmeirinha (Guarapuava)" é distrito, não município: o nome que o
        // catálogo conhece está entre parênteses.
        $cidade = Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Palmeirinha (Guarapuava)',
            'uf' => 'PR',
            'cod_ibge' => null,
        ]);

        $catalogo = app(CatalogoIbge::class);
        $catalogo->aplicar($catalogo->conciliar());

        $this->assertSame(4109401, $cidade->refresh()->cod_ibge);
    }

    public function test_cidade_sem_correspondencia_fica_sem_vinculo(): void
    {
        $empresa = $this->ambiente();

        // "DESCONHECIDO" existe na base real. Inventar um vínculo para ela
        // seria pior que deixá-la órfã: gravaria um código errado na NF-e.
        $cidade = Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'DESCONHECIDO',
            'uf' => 'PR',
            'cod_ibge' => 1212,
        ]);

        $catalogo = app(CatalogoIbge::class);
        $r = $catalogo->aplicar($catalogo->conciliar());

        $this->assertSame(1, $r['orfas']);
        $this->assertNull($cidade->refresh()->municipio_ibge);
    }

    public function test_busca_ignora_acento_e_caixa(): void
    {
        $empresa = $this->ambiente();
        $user = User::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'empresa_id' => $empresa->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/municipios-ibge?q=GUARAPUAVA&uf=PR')
            ->assertOk()
            ->assertJsonPath('data.0.cod_ibge', 4109401);
    }

    public function test_adotar_cria_cidade_com_o_codigo_oficial(): void
    {
        $empresa = $this->ambiente();
        $user = User::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'empresa_id' => $empresa->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/municipios-ibge/adotar', ['cod_ibge' => 4104808])
            ->assertCreated()
            ->assertJsonPath('data.descricao', 'Cascavel')
            ->assertJsonPath('data.cod_ibge', 4104808);
    }

    public function test_adotar_nao_duplica_cidade_ja_cadastrada(): void
    {
        $empresa = $this->ambiente();
        Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Cascavel',
            'uf' => 'PR',
            'cod_ibge' => 4104808,
        ]);

        $user = User::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'empresa_id' => $empresa->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/municipios-ibge/adotar', ['cod_ibge' => 4104808])
            ->assertOk();

        $this->assertSame(1, Cidade::withoutGrupo()->where('descricao', 'Cascavel')->count());
    }

    public function test_sincronismo_recusa_payload_truncado(): void
    {
        // Gravar um payload curto apagaria metade do catálogo em silêncio.
        Http::fake([
            'servicodados.ibge.gov.br/*' => Http::response([
                ['id' => 4109401, 'nome' => 'Guarapuava', 'microrregiao' => ['mesorregiao' => ['UF' => ['id' => 41, 'sigla' => 'PR']]]],
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        app(CatalogoIbge::class)->sincronizar();
    }
}
