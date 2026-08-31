<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3-08 — o município IBGE é o catálogo autoritativo.
 *
 * `cidades` é por GRUPO: cada tenant tem a sua cópia de "Guarapuava/PR", que é
 * um fato nacional. Isso sozinho não é o problema — o problema é o `cod_ibge`
 * ser um inteiro livre, digitado à mão.
 *
 * Um código errado **não dá erro no cadastro**. Dá rejeição da SEFAZ na primeira
 * nota emitida para aquela cidade, quando ninguém lembra de onde veio o número —
 * e a nota é o momento mais caro possível para descobrir.
 *
 * O vínculo `cidades.municipio_ibge → municipios_ibge` já existia. O que faltava
 * era a porta de escrita usá-lo: aceitar `cod_ibge` solto, sem conferir, deixava
 * a garantia valendo só para quem se lembrasse de preencher o vínculo.
 */
class CidadeMunicipioIbgeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, User} */
    private function cenario(): array
    {
        // `cidades.uf` tem FK para `estados` — sem o estado, o insert falha
        // antes de chegar na regra que este arquivo exercita.
        Estado::query()->firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná', 'cod_ibge' => 41]);
        Estado::query()->firstOrCreate(['uf' => 'SC'], ['descricao' => 'Santa Catarina', 'cod_ibge' => 42]);

        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $user];
    }

    private function municipio(int $codigo, string $nome, string $uf): MunicipioIbge
    {
        return MunicipioIbge::query()->create([
            'cod_ibge' => $codigo,
            'nome' => $nome,
            'uf' => $uf,
            'nome_busca' => mb_strtolower($nome),
            'cod_uf' => (int) substr((string) $codigo, 0, 2),
        ]);
    }

    /** Com o vínculo, código e UF são DERIVADOS — não se confia em campos que podem discordar. */
    public function test_vinculo_com_o_catalogo_deriva_codigo_e_uf(): void
    {
        [, $user] = $this->cenario();
        $this->municipio(4109401, 'Guarapuava', 'PR');

        $resposta = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/geo/cidades', [
                'descricao' => 'Guarapuava',
                'uf' => 'PR',
                'municipio_ibge' => 4109401,
            ])
            ->assertCreated();

        $this->assertSame(4109401, $resposta->json('data.cod_ibge'));
        $this->assertSame('PR', $resposta->json('data.uf'));
    }

    /**
     * O que a mudança fecha: um código inventado é recusado no cadastro, onde
     * custa um minuto — e não na SEFAZ, onde custa uma nota.
     */
    public function test_codigo_ibge_inexistente_e_recusado(): void
    {
        [, $user] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/geo/cidades', [
                'descricao' => 'Cidade Inventada',
                'uf' => 'PR',
                'cod_ibge' => 9999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cod_ibge');
    }

    /**
     * UF divergente do próprio código IBGE é rejeitada pela SEFAZ, e o cadastro
     * não tem como saber qual das duas o operador quis.
     */
    public function test_uf_divergente_do_municipio_e_recusada(): void
    {
        [, $user] = $this->cenario();
        $this->municipio(4109401, 'Guarapuava', 'PR');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/geo/cidades', [
                'descricao' => 'Guarapuava',
                'uf' => 'SC',
                'cod_ibge' => 4109401,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uf');
    }

    /** Só o `cod_ibge` também serve: ele vira o vínculo, depois de conferido. */
    public function test_codigo_valido_sozinho_vira_vinculo(): void
    {
        [, $user] = $this->cenario();
        $this->municipio(4106902, 'Curitiba', 'PR');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/geo/cidades', [
                'descricao' => 'Curitiba', 'uf' => 'PR', 'cod_ibge' => 4106902,
            ])
            ->assertCreated();

        $this->assertSame(
            4106902,
            (int) Cidade::query()->where('descricao', 'Curitiba')->value('municipio_ibge'),
        );
    }

    /**
     * Cidade sem código continua podendo ser criada.
     *
     * Exigir o código aqui travaria o cadastro de quem ainda não migrou — e a
     * emissão fiscal já barra o que falta, com erro claro. A garantia é sobre
     * código ERRADO, não sobre código ausente.
     */
    public function test_cidade_sem_codigo_continua_permitida(): void
    {
        [, $user] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/geo/cidades', ['descricao' => 'Distrito novo', 'uf' => 'PR'])
            ->assertCreated();
    }

    /** A conferência vale também na edição — senão bastaria criar e editar. */
    public function test_edicao_tambem_confere_o_codigo(): void
    {
        [, $user] = $this->cenario();
        $this->municipio(4109401, 'Guarapuava', 'PR');

        $cidade = Cidade::query()->create([
            'grupo_id' => $user->grupo_id, 'descricao' => 'Guarapuava', 'uf' => 'PR', 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/geo/cidades/{$cidade->id}", [
                'descricao' => 'Guarapuava', 'uf' => 'PR', 'cod_ibge' => 8888888,
            ])
            ->assertStatus(422);
    }
}
