<?php

namespace Tests\Feature;

use App\Domain\Produto\TipoProduto;
use App\Domain\Satelite\VinculoVasilhame;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3-02 — o produto declara o que é; a regex em português vira sugestão.
 *
 * `VinculoVasilhame` decidia "isto é um casco" lendo a descrição: `VASILHA`,
 * `CASCO`, `BOTIJAO`, `BOTIJÃO`; e "isto é gás" com `GLP`/`RECARGA`, excluindo
 * `GRANEL`.
 *
 * Isso acerta o cadastro de uma revenda — a que escreveu essas palavras. Uma
 * que cadastre "Cilindro 13kg", "P13 cheio", ou opere em espanhol, some da
 * vigilância de comodato inteira. E o modo de falhar é o pior possível: a tela
 * não fica vazia, fica com MENOS linhas, e ninguém sabe quantas faltam.
 *
 * A regex não foi jogada fora — mudou de lugar. Ela agora é uma sugestão
 * oferecida na tela de conferência, com a evidência que a produziu, em vez de
 * uma resposta usada como verdade.
 */
class TipoProdutoDeclaradoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, User} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $user];
    }

    private function produto(Empresa $empresa, string $descricao, array $extra = []): Produto
    {
        return Produto::factory()->create(array_merge([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'descricao' => $descricao,
        ], $extra));
    }

    /**
     * O cenário que a heurística não sobrevive: catálogo em espanhol, sem
     * nenhuma das palavras procuradas.
     */
    public function test_catalogo_em_outro_idioma_e_reconhecido_pelo_tipo(): void
    {
        [$empresa] = $this->cenario();

        $casco = $this->produto($empresa, 'Cilindro 13kg vacío', ['tipo' => TipoProduto::RECIPIENTE->value]);
        $gas = $this->produto($empresa, 'Gas licuado 13kg', ['tipo' => TipoProduto::CONTEUDO->value]);

        $vinculo = app(VinculoVasilhame::class);

        $this->assertTrue($vinculo->ehVasilhame($casco));
        $this->assertTrue($vinculo->ehConteudo($gas));
        $this->assertFalse($vinculo->ehVasilhame($gas));
    }

    /** O nome deixou de decidir: "Botijão" sem tipo declarado não é recipiente. */
    public function test_descricao_sozinha_nao_classifica_mais(): void
    {
        [$empresa] = $this->cenario();

        $ambiguo = $this->produto($empresa, 'Botijão P13 promocional');

        $this->assertSame(TipoProduto::INDEFINIDO, $ambiguo->tipo);
        $this->assertFalse(app(VinculoVasilhame::class)->ehVasilhame($ambiguo));
    }

    /** A regex sobrevive como SUGESTÃO — com a evidência, senão não é conferível. */
    public function test_sugestao_traz_o_tipo_e_a_evidencia(): void
    {
        [$empresa] = $this->cenario();
        $vinculo = app(VinculoVasilhame::class);

        $sugestao = $vinculo->sugerirTipo($this->produto($empresa, 'Vasilha P13'));

        $this->assertSame(TipoProduto::RECIPIENTE, $sugestao['tipo']);
        $this->assertStringContainsString('VASILHA', $sugestao['evidencia']);
    }

    /** Produto já classificado não recebe sugestão: não há o que sugerir. */
    public function test_produto_classificado_nao_recebe_sugestao(): void
    {
        [$empresa] = $this->cenario();

        $classificado = $this->produto($empresa, 'Vasilha P13', ['tipo' => TipoProduto::RECIPIENTE->value]);

        $this->assertNull(app(VinculoVasilhame::class)->sugerirTipo($classificado));
    }

    /**
     * "Botijão P13 - RECARGA" é venda de conteúdo, não casco emprestado.
     * Sem essa exclusão ele entraria dos dois lados, errando as duas contas.
     */
    public function test_recarga_sugere_conteudo_e_nao_recipiente(): void
    {
        [$empresa] = $this->cenario();

        $sugestao = app(VinculoVasilhame::class)->sugerirTipo(
            $this->produto($empresa, 'Botijão P13 - RECARGA'),
        );

        $this->assertSame(TipoProduto::CONTEUDO, $sugestao['tipo']);
    }

    /**
     * GLP a granel vai para tanque estacionário, não enche botijão. Contá-lo
     * como reabastecimento inflaria o giro do cliente que tem os dois.
     */
    public function test_granel_nao_e_sugerido_como_conteudo(): void
    {
        [$empresa] = $this->cenario();

        $this->assertNull(app(VinculoVasilhame::class)->sugerirTipo(
            $this->produto($empresa, 'GLP a GRANEL'),
        ));
    }

    /** `tipo_glp` é evidência mais forte que qualquer palavra: é campo estruturado. */
    public function test_tipo_glp_preenchido_sugere_conteudo(): void
    {
        [$empresa] = $this->cenario();

        $sugestao = app(VinculoVasilhame::class)->sugerirTipo(
            $this->produto($empresa, 'P13 comum', ['tipo_glp' => 3]),
        );

        $this->assertSame(TipoProduto::CONTEUDO, $sugestao['tipo']);
        $this->assertStringContainsString('tipo_glp', $sugestao['evidencia']);
    }

    /** Produto nasce INDEFINIDO: classificação é afirmação, não default. */
    public function test_produto_nasce_indefinido(): void
    {
        [$empresa] = $this->cenario();

        $this->assertSame(TipoProduto::INDEFINIDO, $this->produto($empresa, 'Qualquer coisa')->tipo);
    }

    /** O tipo é declarável pela API — senão a classificação não teria como sair do INDEFINIDO. */
    public function test_tipo_e_declaravel_pela_api(): void
    {
        [$empresa, $user] = $this->cenario();
        $produto = $this->produto($empresa, 'Cilindro 13kg');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/produtos/{$produto->id}", [
                'descricao' => 'Cilindro 13kg',
                'tipo' => TipoProduto::RECIPIENTE->value,
            ])
            ->assertOk();

        $this->assertSame(TipoProduto::RECIPIENTE, $produto->fresh()->tipo);
    }

    /**
     * A capacidade tem o mesmo defeito um nível abaixo: a grade brasileira de
     * GLP (`P13`, `13KG`) estava escrita na regex. Outra grade não pareava.
     */
    public function test_capacidade_declarada_vence_a_descricao(): void
    {
        [$empresa] = $this->cenario();

        $p = $this->produto($empresa, 'Botellón 15 kg', [
            'tipo' => TipoProduto::RECIPIENTE->value,
            'capacidade' => 'B15',
        ]);

        $this->assertSame('B15', app(VinculoVasilhame::class)->capacidadeDe($p));
    }

    /** Sem coluna, a regex continua atendendo — como fallback, não como verdade. */
    public function test_sem_capacidade_declarada_a_descricao_e_o_fallback(): void
    {
        [$empresa] = $this->cenario();

        $p = $this->produto($empresa, 'Vasilha P13 Kg', ['tipo' => TipoProduto::RECIPIENTE->value]);

        $this->assertSame('P13', app(VinculoVasilhame::class)->capacidadeDe($p));
    }

    /**
     * O que a mudança destrava: casco e gás de uma grade que a regex não
     * conhece passam a parear.
     */
    public function test_pareamento_funciona_em_grade_fora_da_regex(): void
    {
        [$empresa] = $this->cenario();

        $casco = $this->produto($empresa, 'Botellón vacío', [
            'tipo' => TipoProduto::RECIPIENTE->value, 'capacidade' => 'B15',
        ]);
        $gas = $this->produto($empresa, 'Gas licuado', [
            'tipo' => TipoProduto::CONTEUDO->value, 'capacidade' => 'B15',
        ]);
        // Mesma grade, capacidade diferente: não pode entrar no par.
        $outro = $this->produto($empresa, 'Gas licuado grande', [
            'tipo' => TipoProduto::CONTEUDO->value, 'capacidade' => 'B45',
        ]);

        $conteudos = app(VinculoVasilhame::class)->conteudosDe($casco);

        $this->assertContains($gas->id, $conteudos);
        $this->assertNotContains($outro->id, $conteudos);
    }

    /**
     * A lista que impede a falha silenciosa: os não classificados aparecem na
     * tela de conferência, com a sugestão ao lado.
     */
    public function test_tela_de_vinculos_lista_os_nao_classificados(): void
    {
        [$empresa, $user] = $this->cenario();
        $this->produto($empresa, 'Cilindro 13kg vacío');

        $resposta = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/comodatos/vinculos')
            ->assertOk();

        $descricoes = collect($resposta->json('nao_classificados'))->pluck('descricao');

        $this->assertContains('Cilindro 13kg vacío', $descricoes->all());
    }
}
