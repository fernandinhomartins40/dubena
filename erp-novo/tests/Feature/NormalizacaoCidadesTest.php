<?php

namespace Tests\Feature;

use App\Domain\Geografico\NormalizarCidades;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Normalização das cidades cadastradas à mão contra o catálogo do IBGE.
 *
 * Todos os casos vieram da base REAL. O ponto difícil é separar dois casos que
 * parecem iguais — o nome difere do município vinculado nos dois:
 *   "Palmeirinha (Guarapuava)" → distrito, está CERTO;
 *   "CAMPO LARGO"/SC com o código de Fraiburgo → vínculo ERRADO.
 */
class NormalizacaoCidadesTest extends TestCase
{
    use RefreshDatabase;

    private function ambiente(): Empresa
    {
        foreach ([['PR', 'Paraná', 41], ['SC', 'Santa Catarina', 42], ['RS', 'Rio Grande do Sul', 43]] as [$uf, $nome, $cod]) {
            Estado::firstOrCreate(['uf' => $uf], ['descricao' => $nome, 'cod_ibge' => $cod]);
        }

        MunicipioIbge::insert([
            ['cod_ibge' => 4109401, 'nome' => 'Guarapuava', 'uf' => 'PR', 'nome_busca' => 'guarapuava', 'cod_uf' => 41],
            ['cod_ibge' => 4104204, 'nome' => 'Campo Largo', 'uf' => 'PR', 'nome_busca' => 'campo largo', 'cod_uf' => 41],
            ['cod_ibge' => 4205506, 'nome' => 'Fraiburgo', 'uf' => 'SC', 'nome_busca' => 'fraiburgo', 'cod_uf' => 42],
            ['cod_ibge' => 4115606, 'nome' => 'Matelândia', 'uf' => 'PR', 'nome_busca' => 'matelandia', 'cod_uf' => 41],
            ['cod_ibge' => 4106506, 'nome' => 'Coronel Vivida', 'uf' => 'PR', 'nome_busca' => 'coronel vivida', 'cod_uf' => 41],
            ['cod_ibge' => 4309209, 'nome' => 'Gravataí', 'uf' => 'RS', 'nome_busca' => 'gravatai', 'cod_uf' => 43],
        ]);

        return Empresa::factory()->create();
    }

    private function cidade(Empresa $e, string $nome, string $uf, ?int $codIbge, ?int $vinculo = null): Cidade
    {
        $c = Cidade::factory()->create([
            'grupo_id' => $e->grupo_id,
            'descricao' => $nome,
            'uf' => $uf,
            'cod_ibge' => $codIbge,
        ]);

        if ($vinculo !== null) {
            $c->forceFill(['municipio_ibge' => $vinculo])->save();
        }

        return $c->refresh();
    }

    public function test_nome_sem_acento_e_apenas_grafia_a_corrigir(): void
    {
        $e = $this->ambiente();
        // Caso real: "MATELANDIA" na base, "Matelândia" no IBGE.
        $cidade = $this->cidade($e, 'MATELANDIA', 'PR', 4115606, 4115606);

        $r = app(NormalizarCidades::class)->avaliar($cidade);

        $this->assertSame('nome_divergente', $r['situacao']);
        $this->assertSame('Matelândia', $r['oficial']->nome);
    }

    public function test_erro_de_digitacao_no_nome_e_grafia_a_corrigir(): void
    {
        $e = $this->ambiente();
        // Caso real: "CORENEL VIVIDA".
        $cidade = $this->cidade($e, 'CORENEL VIVIDA', 'PR', 4106506, 4106506);

        $this->assertSame('nome_divergente', app(NormalizarCidades::class)->avaliar($cidade)['situacao']);
    }

    public function test_lixo_no_nome_e_grafia_nao_distrito(): void
    {
        $e = $this->ambiente();
        MunicipioIbge::insert([
            ['cod_ibge' => 4211900, 'nome' => 'Palhoça', 'uf' => 'SC', 'nome_busca' => 'palhoca', 'cod_uf' => 42],
        ]);

        // Caso real: o "Rua" entrou no nome da cidade. É Palhoça com lixo em
        // volta, não um distrito de nome próprio.
        $cidade = $this->cidade($e, 'Rua Palhoça', 'SC', 4211900, 4211900);

        $r = app(NormalizarCidades::class)->avaliar($cidade);

        $this->assertSame('nome_divergente', $r['situacao']);
        $this->assertSame('Palhoça', $r['oficial']->nome);
    }

    public function test_distrito_com_o_codigo_da_sede_esta_correto(): void
    {
        $e = $this->ambiente();
        // "Palmeirinha (Guarapuava)" tem 231 clientes próprios: é praça de
        // entrega distinta e o código da sede está certo. Não é erro.
        $cidade = $this->cidade($e, 'Palmeirinha (Guarapuava)', 'PR', 4109401, 4109401);

        $this->assertSame('distrito', app(NormalizarCidades::class)->avaliar($cidade)['situacao']);
    }

    public function test_vinculo_errado_e_denunciado_pelo_nome(): void
    {
        $e = $this->ambiente();
        // Caso real: "CAMPO LARGO" com UF=SC e o código de FRAIBURGO. O código
        // existe e a UF bate com ele — só o NOME denuncia o erro.
        $cidade = $this->cidade($e, 'CAMPO LARGO', 'SC', 4205506, 4205506);

        $r = app(NormalizarCidades::class)->avaliar($cidade);

        $this->assertSame('vinculo_suspeito', $r['situacao']);
        $this->assertSame('Fraiburgo', $r['oficial']->nome);
        $this->assertSame(4104204, $r['sugerido']->cod_ibge, 'Deveria sugerir o Campo Largo do PR.');
    }

    public function test_cidade_sem_vinculo_recebe_sugestao_ignorando_a_uf_cadastrada(): void
    {
        $e = $this->ambiente();
        // Caso real: "Gravatai" cadastrada como PR; a cidade é do RS. Filtrar
        // pela UF do cadastro nunca a encontraria — a UF é o que está errado.
        $cidade = $this->cidade($e, 'Gravatai', 'PR', 4309209);

        $r = app(NormalizarCidades::class)->avaliar($cidade);

        $this->assertSame('sugestao_uf', $r['situacao']);
        $this->assertSame('RS', $r['sugerido']->uf);
    }

    public function test_cidade_inexistente_nao_recebe_palpite(): void
    {
        $e = $this->ambiente();
        // "DESCONHECIDO" existe na base real. Inventar um vínculo seria pior.
        $cidade = $this->cidade($e, 'DESCONHECIDO', 'PR', 1212);

        $this->assertSame('sem_correspondencia', app(NormalizarCidades::class)->avaliar($cidade)['situacao']);
    }

    public function test_corrigir_nome_preserva_o_id(): void
    {
        $e = $this->ambiente();
        $cidade = $this->cidade($e, 'MATELANDIA', 'PR', 4115606, 4115606);
        $idOriginal = $cidade->id;

        $normalizador = app(NormalizarCidades::class);
        $normalizador->corrigirNome($cidade, $normalizador->avaliar($cidade)['oficial']);

        $cidade->refresh();
        // 44 mil clientes apontam para cidades.id — recriar apagaria o endereço.
        $this->assertSame($idOriginal, $cidade->id);
        $this->assertSame('Matelândia', $cidade->descricao);
    }

    public function test_revincular_acerta_codigo_uf_e_nome_juntos(): void
    {
        $e = $this->ambiente();
        $cidade = $this->cidade($e, 'CAMPO LARGO', 'SC', 4205506, 4205506);

        $normalizador = app(NormalizarCidades::class);
        $normalizador->revincular($cidade, $normalizador->avaliar($cidade)['sugerido']);

        $cidade->refresh();
        $this->assertSame('Campo Largo', $cidade->descricao);
        // A UF também estava errada: manter SC com o código do PR recriaria
        // a mesma inconsistência.
        $this->assertSame('PR', $cidade->uf);
        $this->assertSame(4104204, $cidade->cod_ibge);
        $this->assertSame(4104204, $cidade->municipio_ibge);
    }

    public function test_duplicatas_excluem_distritos(): void
    {
        $e = $this->ambiente();
        // Duplicata real: a mesma cidade escrita de dois jeitos.
        $this->cidade($e, 'Coronel Vivida', 'PR', 4106506, 4106506);
        $this->cidade($e, 'CORENEL VIVIDA', 'PR', 4106506, 4106506);
        // Distrito compartilha o código da sede DE PROPÓSITO: não é duplicata.
        $this->cidade($e, 'Guarapuava', 'PR', 4109401, 4109401);
        $this->cidade($e, 'Palmeirinha (Guarapuava)', 'PR', 4109401, 4109401);

        $duplicatas = app(NormalizarCidades::class)->duplicatas();

        $this->assertCount(1, $duplicatas);
        $this->assertSame('Coronel Vivida', $duplicatas[0]['oficial']->nome);
    }

    public function test_cidade_correta_nao_vira_proposta(): void
    {
        $e = $this->ambiente();
        $cidade = $this->cidade($e, 'Guarapuava', 'PR', 4109401, 4109401);

        $this->assertSame('ok', app(NormalizarCidades::class)->avaliar($cidade)['situacao']);
    }

    public function test_nome_ambiguo_no_catalogo_nao_recebe_sugestao(): void
    {
        $e = $this->ambiente();
        MunicipioIbge::insert([
            ['cod_ibge' => 4302105, 'nome' => 'Bom Jesus', 'uf' => 'RS', 'nome_busca' => 'bom jesus', 'cod_uf' => 43],
            ['cod_ibge' => 4202503, 'nome' => 'Bom Jesus', 'uf' => 'SC', 'nome_busca' => 'bom jesus', 'cod_uf' => 42],
        ]);

        $cidade = $this->cidade($e, 'Bom Jesus', 'PR', null);

        // Com mais de um município homônimo, escolher seria adivinhar.
        $r = app(NormalizarCidades::class)->avaliar($cidade);
        $this->assertSame('sem_correspondencia', $r['situacao']);
        $this->assertNull($r['sugerido']);
    }
}
