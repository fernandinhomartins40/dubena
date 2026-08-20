<?php

namespace Tests\Feature;

use App\Domain\Monitora\CercasInteligentesService;
use App\Domain\Monitora\Contracts\MalhaViaria;
use App\Domain\Monitora\Drivers\FakeMalhaViaria;
use App\Domain\Monitora\GrafoViario;
use App\Models\Empresa;
use App\Models\Monitora\Cerca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ferramentas assistidas da aba Cercas.
 *
 * A geometria aqui é o tipo de código que passa a impressão de funcionar e
 * entrega contorno errado: a primeira versão da caminhada de face devolvia zero
 * quadras num grafo de 243 cruzamentos por causa de um sinal invertido. Por
 * isso os testes afirmam MEDIDA (tamanho da quadra, fração sobreposta), e não
 * só "veio alguma coisa".
 */
class CercasInteligentesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
    }

    private function servico(): CercasInteligentesService
    {
        return $this->app->make(CercasInteligentesService::class);
    }

    public function test_fecha_a_quadra_que_contem_o_ponto(): void
    {
        // A malha sintética é um xadrez de 0,001° (~110 m). Um ponto no meio de
        // uma célula deve devolver aquela célula — quatro cantos, não a cidade.
        $quadra = $this->servico()->quadra(-25.39350, -51.45650);

        $this->assertNotNull($quadra, 'deveria fechar a quadra no xadrez sintético');
        $this->assertGreaterThanOrEqual(4, count($quadra));

        // O ponto clicado precisa estar DENTRO do que voltou — é o mínimo que
        // "a quadra daquele clique" significa.
        $this->assertTrue(
            GrafoViario::contem($quadra, -25.39350, -51.45650),
            'a quadra devolvida não contém o ponto clicado',
        );

        // E precisa ser a MENOR face, não o retângulo inteiro da consulta: o
        // lado da célula é 0,001°, então nenhum vértice pode fugir muito disso.
        $lats = array_column($quadra, 'lat');
        $lngs = array_column($quadra, 'lng');
        $this->assertLessThan(0.0025, max($lats) - min($lats), 'quadra alta demais — pegou face externa');
        $this->assertLessThan(0.0025, max($lngs) - min($lngs), 'quadra larga demais — pegou face externa');
    }

    public function test_sem_malha_a_quadra_nao_e_inventada(): void
    {
        // Overpass fora do ar: a ferramenta fica indisponível, e é o certo —
        // devolver um retângulo qualquer viraria cerca gravada torta.
        $this->app->bind(MalhaViaria::class, fn () => new class implements MalhaViaria
        {
            public function vias(float $sul, float $oeste, float $norte, float $leste): array
            {
                return [];
            }
        });

        $this->assertNull($this->servico()->quadra(-25.3935, -51.4562));
    }

    public function test_contencao_nao_e_acusada_como_conflito(): void
    {
        // Reproduz o caso real: a "Área de entrega — Guarapuava" engloba os
        // setores de propósito. Acusar isso afogaria a lista com avisos sobre
        // os quais ninguém deve agir.
        $mae = $this->cercaQuadrada('Área de entrega', -25.45, -51.50, -25.35, -51.40);
        $filha = $this->cercaQuadrada('Setor 01', -25.42, -51.47, -25.40, -51.45);

        $conflitos = $this->servico()->conflitos([$mae, $filha]);

        $this->assertSame([], $conflitos, 'cerca-mãe englobando setor não é conflito');
    }

    public function test_area_mae_que_corta_a_beirada_tambem_e_ignorada(): void
    {
        // O caso que quebrou o critério anterior nos dados reais. A cerca-mãe
        // de produção é um quadrilátero folgado de 4 pontos: ela NÃO contém os
        // setores por inteiro, corta a beirada de vários. Com a regra de "90%
        // da área dentro da outra", a contenção ficava em 46% a 83% e escapava
        // — os 5 avisos emitidos eram todos dela, nenhum acionável.
        // Proporção da base real: a mãe é ~26× a área do setor.
        $mae = $this->cercaQuadrada('Área de entrega', -25.50, -51.60, -25.30, -51.35);
        // Transborda a mãe pelo sul e pelo oeste, como acontece de verdade.
        $setor = $this->cercaQuadrada('Setor 07', -25.52, -51.62, -25.47, -51.56);

        $this->assertSame(
            [],
            $this->servico()->conflitos([$mae, $setor]),
            'envelope de cidade cortando a beirada do setor não é disputa de território',
        );
    }

    public function test_setores_que_dividem_divisa_longa_nao_sao_conflito(): void
    {
        // Os setores 06 e 07 reais compartilham 69 de 138 VÉRTICES e mesmo
        // assim dividem só 0,01% de área: é uma divisa longa e bem desenhada.
        // Foi o que mostrou que contar vértice mede a fronteira, não a disputa.
        $a = $this->cercaQuadrada('Setor 06', -25.41, -51.47, -25.38, -51.43);
        $b = $this->cercaQuadrada('Setor 07', -25.38, -51.47, -25.35, -51.43);

        $this->assertSame([], $this->servico()->conflitos([$a, $b]));
    }

    public function test_encosto_de_borda_nao_e_acusado(): void
    {
        // Vizinhas dividindo a divisa: é exatamente o que o snap do editor
        // produz de propósito, para não sobrar buraco entre setores.
        //
        // Este caso pegou um defeito real: contando VÉRTICES dentro da outra,
        // estas duas — que não dividem um metro quadrado de área — acusavam 25%
        // de sobreposição, porque os 2 vértices da divisa contam como "dentro"
        // pela regra do raio. Ou seja, todo par bem desenhado viraria alarme
        // falso. A medida certa é área comum, e aqui ela é zero.
        $a = $this->cercaQuadrada('Setor A', -25.42, -51.47, -25.40, -51.45);
        $b = $this->cercaQuadrada('Setor B', -25.42, -51.45, -25.40, -51.43);

        $this->assertSame([], $this->servico()->conflitos([$a, $b]));
    }

    public function test_sobreposicao_real_e_acusada_com_a_fracao(): void
    {
        // Duas cercas do MESMO tamanho cobrindo um quarto de território em
        // comum: é a disputa que custa entrega errada, porque o endereço no
        // meio pertence às duas e a rota que o pega é sorteio.
        $a = $this->cercaQuadrada('Setor A', -25.42, -51.47, -25.40, -51.45);
        $b = $this->cercaQuadrada('Setor B', -25.41, -51.46, -25.39, -51.44);

        $conflitos = $this->servico()->conflitos([$a, $b]);

        $this->assertCount(1, $conflitos);
        $this->assertSame($a->id, $conflitos[0]['a']);
        $this->assertSame($b->id, $conflitos[0]['b']);
        $this->assertGreaterThan(0.1, $conflitos[0]['fracao']);
    }

    public function test_ajuste_preserva_o_contorno_quando_nao_ha_encaixe(): void
    {
        // Com o ajustador Fake (sem Roads API) o contorno não pode sumir nem
        // encolher: entregar meio contorno seria pior que não ajustar.
        $contorno = [
            ['lat' => -25.42, 'lng' => -51.47],
            ['lat' => -25.42, 'lng' => -51.45],
            ['lat' => -25.40, 'lng' => -51.45],
            ['lat' => -25.40, 'lng' => -51.47],
        ];

        $ajustado = $this->servico()->ajustar($contorno);

        $this->assertNotNull($ajustado);
        $this->assertGreaterThanOrEqual(3, count($ajustado));
    }

    public function test_contorno_com_menos_de_tres_pontos_nao_ajusta(): void
    {
        $this->assertNull($this->servico()->ajustar([
            ['lat' => -25.42, 'lng' => -51.47],
            ['lat' => -25.40, 'lng' => -51.45],
        ]));
    }

    public function test_malha_fake_gera_cruzamentos_de_verdade(): void
    {
        // Se o xadrez não se cruzar, o grafo não tem face e os testes acima
        // passariam por vacuidade.
        $vias = (new FakeMalhaViaria)->vias(-25.396, -51.459, -25.391, -51.454);

        $this->assertNotEmpty($vias);
        $this->assertNotEmpty((new GrafoViario($vias))->faces(), 'a malha sintética não fechou nenhuma face');
    }

    /** Cerca retangular persistida, para os testes de conflito. */
    private function cercaQuadrada(string $nome, float $sul, float $oeste, float $norte, float $leste): Cerca
    {
        $cerca = Cerca::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => $nome,
            'cor' => '#FF6200',
            'ativo' => true,
        ]);

        $cantos = [[$sul, $oeste], [$sul, $leste], [$norte, $leste], [$norte, $oeste]];
        foreach ($cantos as $i => [$lat, $lng]) {
            $cerca->pontos()->create([
                'empresa_id' => $this->empresa->id,
                'grupo_id' => $this->empresa->grupo_id,
                'latitude' => $lat,
                'longitude' => $lng,
                'ordem' => $i,
            ]);
        }

        return $cerca->load('pontos');
    }
}
