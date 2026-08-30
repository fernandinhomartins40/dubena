<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endereço normalizado de empresa e colaborador.
 *
 * Os dois formulários já enviavam `cidade_id`/`bairro_id` (em Colaborador o
 * campo era até obrigatório na tela), mas o destino não tinha as colunas: o
 * usuário preenchia e o backend descartava em silêncio — sem erro, sem aviso.
 *
 * Na empresa isso tinha consequência fiscal: as 7 empresas migradas ficaram sem
 * cidade/bairro/endereço, e a DANFE imprime o endereço do emitente.
 */
class EnderecoEmpresaColaboradorTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa, Cidade, Bairro} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        // `cidades.uf` é FK para `estados`: sem o PR cadastrado a criação falha.
        Estado::firstOrCreate(
            ['uf' => 'PR'],
            ['descricao' => 'Paraná', 'cod_ibge' => 41],
        );
        $cidade = Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Guarapuava', 'uf' => 'PR',
        ]);
        $bairro = Bairro::factory()->create([
            'grupo_id' => $empresa->grupo_id, 'cidade_id' => $cidade->id, 'descricao' => 'Batel',
        ]);

        return [$user, $empresa, $cidade, $bairro];
    }

    public function test_empresa_grava_o_endereco_por_fk(): void
    {
        [$user, $empresa, $cidade, $bairro] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/empresas/{$empresa->id}", [
                'razao_social' => $empresa->razao_social,
                'cidade_id' => $cidade->id,
                'bairro_id' => $bairro->id,
                'numero' => '1500',
            ])
            ->assertOk()
            ->assertJsonPath('data.cidade_id', $cidade->id)
            ->assertJsonPath('data.bairro_id', $bairro->id);

        $this->assertDatabaseHas('empresas', [
            'id' => $empresa->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'numero' => '1500',
        ]);
    }

    /**
     * O texto tem de acompanhar a FK: `DanfePdfService`, `ComodatoPdfService` e
     * `ValeGasPdfService` imprimem `$empresa->cidade` como string. Se as duas
     * representações divergirem, a nota fiscal sai com o endereço antigo.
     */
    public function test_texto_do_endereco_e_derivado_da_fk(): void
    {
        [$user, $empresa, $cidade, $bairro] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/empresas/{$empresa->id}", [
                'razao_social' => $empresa->razao_social,
                'cidade_id' => $cidade->id,
                'bairro_id' => $bairro->id,
            ])
            ->assertOk();

        $empresa->refresh();
        $this->assertSame('Guarapuava', $empresa->cidade, 'a DANFE imprime este campo');
        $this->assertSame('Batel', $empresa->bairro);
        $this->assertSame('PR', $empresa->uf, 'a UF vem da cidade escolhida');
    }

    public function test_empresa_devolve_os_rotulos_das_fks(): void
    {
        [$user, $empresa, $cidade, $bairro] = $this->cenario();
        $empresa->update(['cidade_id' => $cidade->id, 'bairro_id' => $bairro->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/empresas/{$empresa->id}")
            ->assertOk()
            ->assertJsonPath('data.cidade_label', 'Guarapuava')
            ->assertJsonPath('data.bairro_label', 'Batel');
    }

    public function test_colaborador_grava_o_endereco(): void
    {
        [$user, $empresa, $cidade, $bairro] = $this->cenario();

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/colaboradores', [
                'nome' => 'João Entregador',
                'cidade_id' => $cidade->id,
                'bairro_id' => $bairro->id,
                'numero' => '250',
                'cep' => '85010000',
                'uf' => 'PR',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('colaboradores', [
            'id' => $resp->json('data.id'),
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'numero' => '250',
            'cep' => '85010000',
        ]);
    }

    /**
     * A SPA envia `datanascimento`/`dataadmissao` (grafia do legado), mas o
     * `validate()` só conhecia `data_nascimento`/`data_admissao` — as datas eram
     * descartadas silenciosamente na criação e na edição.
     */
    public function test_colaborador_aceita_as_datas_na_grafia_da_spa(): void
    {
        [$user] = $this->cenario();

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/colaboradores', [
                'nome' => 'Maria Atendente',
                'datanascimento' => '1990-05-01',
                'dataadmissao' => '2020-01-10',
            ])
            ->assertCreated();

        $colaborador = Colaborador::withoutTenant()->find($resp->json('data.id'));
        $this->assertSame('1990-05-01', $colaborador->data_nascimento?->toDateString());
        $this->assertSame('2020-01-10', $colaborador->data_admissao?->toDateString());
    }

    public function test_colaborador_devolve_endereco_e_rotulos_na_edicao(): void
    {
        [$user, $empresa, $cidade, $bairro] = $this->cenario();

        $colaborador = Colaborador::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'numero' => '250',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/colaboradores/{$colaborador->id}")
            ->assertOk()
            ->assertJsonPath('data.cidade_id', $cidade->id)
            ->assertJsonPath('data.cidade_label', 'Guarapuava')
            ->assertJsonPath('data.bairro_label', 'Batel')
            ->assertJsonPath('data.numero', '250');
    }
}
