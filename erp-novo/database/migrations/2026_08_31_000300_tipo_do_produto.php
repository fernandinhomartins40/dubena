<?php

use App\Domain\Produto\TipoProduto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3-02 — o produto passa a DECLARAR o que é.
 *
 * `VinculoVasilhame` decidia lendo a descrição: `VASILHA`, `CASCO`, `BOTIJAO`,
 * `BOTIJÃO` para recipiente; `GLP`, `RECARGA` para conteúdo; `GRANEL` excluía.
 * Isso acerta o cadastro da revenda que escreveu essas palavras. Uma que
 * cadastre "Cilindro 13kg" ou opere em espanhol some da vigilância inteira — e
 * a tela não fica vazia, fica com MENOS linhas, que é o modo mais difícil de
 * perceber.
 *
 * ## A conversão
 *
 * A heurística roda uma vez, aqui, e o resultado é gravado com a evidência que
 * o produziu. Três colunas, e cada uma responde a uma pergunta diferente:
 *
 *   `tipo`             o que o produto é (a resposta que o código passa a usar)
 *   `tipo_origem`      quem decidiu: `heuristica` ou `humano`
 *   `tipo_evidencia`   por que se decidiu isso (o termo que casou)
 *
 * A origem é o que impede a conversão de virar verdade absoluta: uma linha
 * marcada `heuristica` é um palpite gravado, e a tela pode listá-la para
 * conferência. Sem essa coluna, palpite e decisão humana ficariam
 * indistinguíveis no dia seguinte — e a dívida sumiria de vista sem ser paga.
 *
 * `tipo_glp` preenchido é evidência mais forte que qualquer palavra: é um campo
 * estruturado que o cadastro já tinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $t) {
            $t->string('tipo', 20)->default(TipoProduto::INDEFINIDO->value)->index();
            $t->string('tipo_origem', 20)->nullable();
            $t->string('tipo_evidencia', 120)->nullable();
        });

        $this->classificar();
    }

    /**
     * Aplica a heurística legada uma única vez, registrando a evidência.
     *
     * A ordem importa e reproduz a do código antigo: `RECARGA` exclui de
     * recipiente antes de qualquer coisa (é venda de conteúdo, não casco
     * emprestado), e `GRANEL` exclui de conteúdo (vai para tanque estacionário,
     * não enche botijão).
     */
    private function classificar(): void
    {
        // UPPER + LIKE em vez de ILIKE: o sqlite dos testes não tem ILIKE, e a
        // conversão precisa dar o mesmo resultado nos dois bancos.
        $d = 'UPPER(descricao)';

        // CONTEÚDO por campo estruturado — a evidência mais forte que existe.
        DB::table('produtos')
            ->whereNotNull('tipo_glp')
            ->whereRaw("({$d} NOT LIKE '%GRANEL%')")
            ->update([
                'tipo' => TipoProduto::CONTEUDO->value,
                'tipo_origem' => 'heuristica',
                'tipo_evidencia' => 'tipo_glp preenchido',
            ]);

        // RECIPIENTE: casco emprestado. `RECARGA` no nome exclui — é venda de
        // conteúdo, e sem essa exclusão o produto entraria dos dois lados,
        // errando as duas contas.
        foreach (['VASILHA', 'CASCO', 'BOTIJAO', 'BOTIJÃO', 'CILINDRO'] as $termo) {
            DB::table('produtos')
                ->where('tipo', TipoProduto::INDEFINIDO->value)
                ->whereRaw("({$d} LIKE ?)", ['%'.$termo.'%'])
                ->whereRaw("({$d} NOT LIKE '%RECARGA%')")
                ->update([
                    'tipo' => TipoProduto::RECIPIENTE->value,
                    'tipo_origem' => 'heuristica',
                    'tipo_evidencia' => 'descrição contém '.$termo,
                ]);
        }

        // CONTEÚDO por descrição, para o que não tem `tipo_glp`.
        foreach (['GLP', 'RECARGA'] as $termo) {
            DB::table('produtos')
                ->where('tipo', TipoProduto::INDEFINIDO->value)
                ->whereRaw("({$d} LIKE ?)", ['%'.$termo.'%'])
                ->whereRaw("({$d} NOT LIKE '%GRANEL%')")
                ->update([
                    'tipo' => TipoProduto::CONTEUDO->value,
                    'tipo_origem' => 'heuristica',
                    'tipo_evidencia' => 'descrição contém '.$termo,
                ]);
        }

        // O resto NÃO vira MERCADORIA automaticamente.
        //
        // "Não casou com nenhuma palavra" e "é mercadoria comum" são afirmações
        // diferentes, e só a primeira é verdade aqui. Marcar tudo como
        // mercadoria esconderia justamente os cascos que a heurística não
        // reconhece — que são a razão desta migration existir.
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $t) {
            $t->dropColumn(['tipo', 'tipo_origem', 'tipo_evidencia']);
        });
    }
};
