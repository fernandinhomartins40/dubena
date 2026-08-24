<?php

namespace Tests\Feature;

use App\Domain\Geografico\NormalizarLogradouros;
use App\Domain\Identidade\NormalizadorTexto;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\LogradouroOficial;
use App\Models\Geografico\Rua;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Normalização do cadastro manual de ruas contra o oficial do CNEFE.
 *
 * Os casos aqui saíram da base REAL de Guarapuava: "Rua 10 de Setembro" e "Rua
 * Dez de Setembro" convivendo como registros distintos da mesma via, e "Rua
 * Sete de Seetembro" com o erro de digitação preservado pela importação.
 */
class NormalizacaoLogradourosTest extends TestCase
{
    use RefreshDatabase;

    private const COD_IBGE = 4109401;

    private function cidade(): Cidade
    {
        Estado::firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná', 'cod_ibge' => 41]);
        $empresa = Empresa::factory()->create();

        return Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Guarapuava',
            'uf' => 'PR',
            'cod_ibge' => self::COD_IBGE,
        ]);
    }

    private function oficial(string $tipo, string $nome, ?string $bairro = null, ?string $cep = null): LogradouroOficial
    {
        return LogradouroOficial::create([
            'cod_ibge' => self::COD_IBGE,
            'tipo' => $tipo,
            'nome' => $nome,
            'bairro' => $bairro,
            'cep' => $cep,
            'nome_busca' => NormalizadorTexto::logradouro($tipo.' '.$nome),
            'numero_min' => 1,
            'numero_max' => 2000,
            'enderecos' => 100,
        ]);
    }

    private function rua(Cidade $c, string $descricao): Rua
    {
        return Rua::create([
            'grupo_id' => $c->grupo_id,
            'cidade_id' => $c->id,
            'descricao' => $descricao,
            'ativo' => true,
        ]);
    }

    /**
     * A chave tem de ser IDÊNTICA à que o scripts/cnefe_importar.py gera — as
     * duas pontas alimentam a mesma coluna, e divergir faria a busca não achar
     * nada. Os valores esperados aqui foram conferidos contra a saída do Python.
     */
    public function test_chave_de_busca_bate_com_a_do_script_python(): void
    {
        $esperado = [
            'Rua Sete de Setembro' => '7 de setembro',
            'RUA 7 DE SETEMBRO' => '7 de setembro',
            'R. Sete de Setembro' => '7 de setembro',
            'Avenida XV de Novembro' => '15 de novembro',
            'Rua Quinze de Novembro' => '15 de novembro',
            'RUA DEZ DE SETEMBRO' => '10 de setembro',
            'Rua 10 de Setembro' => '10 de setembro',
            'Travessa Doutor Joao' => 'doutor joao',
            'RUA CLAUDIO COUTINHO' => 'claudio coutinho',
        ];

        foreach ($esperado as $entrada => $chave) {
            $this->assertSame($chave, NormalizadorTexto::logradouro($entrada), "Falhou para: {$entrada}");
        }
    }

    public function test_numeral_por_extenso_e_digito_sao_a_mesma_via(): void
    {
        // O caso real: as duas grafias convivem na base e a busca por
        // "setembro" devolve ambas como se fossem ruas diferentes.
        $this->assertSame(
            NormalizadorTexto::logradouro('Rua Dez de Setembro'),
            NormalizadorTexto::logradouro('Rua 10 de Setembro'),
        );
    }

    /**
     * Casos colhidos da PRIMEIRA execução em produção, que reprovou o critério
     * inicial: `similaridadeNome` mede sobreposição sobre o MENOR conjunto
     * (correto para pessoa, onde informar menos é legítimo) e por isso deu 100%
     * para vias claramente distintas.
     */
    public function test_nao_confunde_vias_distintas_de_nome_parecido(): void
    {
        $falsos = [
            ['Rua Santo Andre', 'RUA JOSE DOS SANTOS ANDRADE'],
            ['Rua Sonia Maria Sampaio Szeremeta', 'RUA MARIO ZENI'],
            // "Mato Grosso do Sul" NÃO é "Mato Grosso".
            ['Travessa Mato Grosso do Sul', 'RUA MATO GROSSO'],
            ['Rua Maria Eleni da Silva Virmond', 'RUA MARIO VIRMOND'],
            ['Rua Sao Jose', 'COMUNIDADE VILAREJO SAO JOSE'],
            ['Rua Santo Inacio', 'RUA SANTA INES'],
        ];

        foreach ($falsos as [$cadastrado, $oficial]) {
            $this->assertLessThan(
                NormalizarLogradouros::LIMIAR_PROVAVEL,
                NormalizadorTexto::similaridadeLogradouro($cadastrado, $oficial),
                "Não deveria propor '{$oficial}' para '{$cadastrado}'.",
            );
        }
    }

    /** Os pares que SÃO a mesma via e precisam continuar sendo propostos. */
    public function test_reconhece_variacoes_da_mesma_via(): void
    {
        $verdadeiros = [
            ['Rua Sete de Seetembro', 'RUA SETE DE SETEMBRO'],   // digitação
            ['Rua Alcides Rossoni', 'RUA ALCIDES ROSSINI'],      // digitação
            ['Rua Dario Borges de Lis', 'RUA DARIO BORGES DE LIZ'], // grafia S/Z
            ['Rua Carla Selhorst de Souza', 'RUA CARLA SELHORST SOUZA'], // partícula
            ['Avenida Brasil', 'RUA BRASIL'],                    // só o tipo muda
            ['Rua Dez de Setembro', 'RUA 10 DE SETEMBRO'],       // extenso/dígito
        ];

        foreach ($verdadeiros as [$cadastrado, $oficial]) {
            $this->assertGreaterThanOrEqual(
                NormalizarLogradouros::LIMIAR_PROVAVEL,
                NormalizadorTexto::similaridadeLogradouro($cadastrado, $oficial),
                "Deveria propor '{$oficial}' para '{$cadastrado}'.",
            );
        }
    }

    public function test_reconhece_rua_que_bate_exatamente(): void
    {
        $cidade = $this->cidade();
        $this->oficial('RUA', 'CLAUDIO COUTINHO');
        $this->rua($cidade, 'Rua Claudio Coutinho');

        $analise = app(NormalizarLogradouros::class)->analisar($cidade);

        $this->assertCount(1, $analise);
        $this->assertSame('exato', $analise[0]['situacao']);
    }

    public function test_detecta_erro_de_digitacao_como_provavel(): void
    {
        $cidade = $this->cidade();
        $this->oficial('RUA', 'SETE DE SETEMBRO');
        // Caso real preservado pela importação: a letra a mais.
        $this->rua($cidade, 'Rua Sete de Seetembro');

        $analise = app(NormalizarLogradouros::class)->analisar($cidade);

        $this->assertSame('provavel', $analise[0]['situacao']);
        $this->assertNotNull($analise[0]['oficial']);
    }

    public function test_rua_sem_correspondencia_nao_recebe_palpite(): void
    {
        $cidade = $this->cidade();
        $this->oficial('RUA', 'CLAUDIO COUTINHO');
        // Nada a ver com a oficial: propor uma correção aqui seria pior que
        // não propor nada — renomearia uma rua legítima para outra coisa.
        $this->rua($cidade, 'Estrada da Colonia Vitoria');

        $analise = app(NormalizarLogradouros::class)->analisar($cidade);

        $this->assertSame('ausente', $analise[0]['situacao']);
        $this->assertNull($analise[0]['oficial']);
    }

    public function test_aplicar_renomeia_preservando_o_id(): void
    {
        $cidade = $this->cidade();
        $oficial = $this->oficial('RUA', 'SETE DE SETEMBRO', 'CENTRO', '85065640');
        $rua = $this->rua($cidade, 'Rua Sete de Seetembro');
        $idOriginal = $rua->id;

        app(NormalizarLogradouros::class)->aplicar($rua, $oficial);

        $rua->refresh();
        // O id é o ponto: 44.338 clientes apontam para ruas.id e recriar a rua
        // apagaria o endereço deles.
        $this->assertSame($idOriginal, $rua->id);
        $this->assertSame('RUA SETE DE SETEMBRO', $rua->descricao);
        $this->assertSame('85065640', $rua->cep);
        $this->assertNotNull($rua->bairro_id);
        $this->assertSame('CENTRO', Bairro::withoutGrupo()->find($rua->bairro_id)->descricao);
    }

    public function test_aplicar_nao_sobrescreve_cep_ja_preenchido(): void
    {
        $cidade = $this->cidade();
        $oficial = $this->oficial('RUA', 'SETE DE SETEMBRO', 'CENTRO', '85065640');
        $rua = $this->rua($cidade, 'Rua Sete de Seetembro');
        $rua->forceFill(['cep' => '85000000'])->save();

        app(NormalizarLogradouros::class)->aplicar($rua, $oficial);

        // O dado que alguém conferiu à mão vale mais que o da importação em massa.
        $this->assertSame('85000000', $rua->refresh()->cep);
    }

    public function test_encontra_duplicatas_da_mesma_via_oficial(): void
    {
        $cidade = $this->cidade();
        $this->oficial('RUA', 'DEZ DE SETEMBRO');
        // As duas existem de verdade na base, como registros separados.
        $this->rua($cidade, 'Rua Dez de Setembro');
        $this->rua($cidade, 'Rua 10 de Setembro');

        $duplicatas = app(NormalizarLogradouros::class)->duplicatas($cidade);

        $this->assertCount(1, $duplicatas);
        $this->assertCount(2, $duplicatas[0]['ruas']);
    }

    public function test_sugere_oficial_para_texto_digitado(): void
    {
        $this->cidade();
        $this->oficial('RUA', 'PRESIDENTE GETULIO VARGAS');

        $sugestoes = app(NormalizarLogradouros::class)
            ->sugerir(self::COD_IBGE, 'Rua Presidente Getulio Vargaz');

        $this->assertNotEmpty($sugestoes);
        $this->assertSame('RUA PRESIDENTE GETULIO VARGAS', $sugestoes[0]['oficial']->nome_completo);
    }

    public function test_sugestao_ignora_texto_curto_demais(): void
    {
        $this->cidade();
        $this->oficial('RUA', 'CLAUDIO COUTINHO');

        // Com 1-2 letras qualquer coisa casa: sugerir aqui seria ruído puro.
        $this->assertSame([], app(NormalizarLogradouros::class)->sugerir(self::COD_IBGE, 'ru'));
    }

    public function test_cidade_sem_catalogo_importado_nao_propoe_nada(): void
    {
        $cidade = $this->cidade();
        $this->rua($cidade, 'Rua Qualquer');

        // Sem oficiais, todo palpite seria invenção.
        $this->assertSame([], app(NormalizarLogradouros::class)->analisar($cidade));
    }

    public function test_faixa_de_numeracao_alerta_sobre_numero_improvavel(): void
    {
        $this->cidade();
        $oficial = $this->oficial('RUA', 'CLAUDIO COUTINHO');

        $this->assertTrue($oficial->numeroPlausivel(500));
        $this->assertFalse($oficial->numeroPlausivel(99999));
        // Sem número informado não há o que julgar.
        $this->assertNull($oficial->numeroPlausivel(null));
    }
}
