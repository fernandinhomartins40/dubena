<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3-02 (segunda peça) — a capacidade do recipiente vira dado declarado.
 *
 * `VinculoVasilhame::capacidade()` extraía a capacidade da descrição:
 *
 *     /\bP\s?(13|20|45|90)\b/      "P13", "P 13"
 *     /\b(13|20|45|90)\s?KG\b/     "13KG", "45 KG"
 *
 * É o mesmo problema do `tipo`, um nível abaixo: a grade brasileira de GLP está
 * escrita no código. Uma revenda que use outra grade — ou "botellón 15 kg", ou
 * qualquer nomenclatura fora dessas duas formas — não pareia casco com gás, e o
 * pareamento é o que sustenta a vigilância inteira: sem ele não há como
 * perguntar "o cliente com 13 vasilhames P13 comprou quanto de P13?".
 *
 * ## Por que texto e não número
 *
 * `capacidade` é `varchar` e não decimal porque o valor é um RÓTULO de grade
 * comercial ("P13"), não uma medida. Dois recipientes de 13 kg de grades
 * diferentes não são intercambiáveis, e um número faria parecer que são.
 *
 * O pareamento é por igualdade exata do rótulo, que é a semântica correta: o
 * gás "P13" enche o casco "P13".
 *
 * ## A conversão
 *
 * A regex roda uma vez, aqui. `tipo_glp` (campo fiscal, preenchido para valer)
 * tem precedência sobre o texto — é a mesma ordem que o código antigo usava ao
 * comparar, e ela está certa.
 */
return new class extends Migration
{
    /** tipo_glp → rótulo, do legado. */
    private const TIPO_GLP = [3 => 'P13', 4 => 'P20', 5 => 'P45'];

    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $t) {
            $t->string('capacidade', 20)->nullable()->index();
            $t->string('capacidade_origem', 20)->nullable();
        });

        $this->converter();
    }

    private function converter(): void
    {
        // `tipo_glp` primeiro: campo estruturado vence texto.
        foreach (self::TIPO_GLP as $tipo => $rotulo) {
            DB::table('produtos')
                ->where('tipo_glp', $tipo)
                ->whereNull('capacidade')
                ->update(['capacidade' => $rotulo, 'capacidade_origem' => 'tipo_glp']);
        }

        // Depois o texto, só para quem não tem o campo fiscal. Percorrido em
        // PHP porque as duas formas ("P13" e "13KG") não cabem num LIKE sem
        // falso positivo — "P130" casaria com `%P13%`.
        DB::table('produtos')
            ->whereNull('capacidade')
            ->orderBy('id')
            ->chunkById(500, function ($produtos) {
                foreach ($produtos as $p) {
                    $rotulo = self::daDescricao($p->descricao);

                    if ($rotulo !== null) {
                        DB::table('produtos')->where('id', $p->id)->update([
                            'capacidade' => $rotulo,
                            'capacidade_origem' => 'descricao',
                        ]);
                    }
                }
            });
    }

    /** A regex legada, aplicada uma única vez. */
    private static function daDescricao(?string $descricao): ?string
    {
        $texto = mb_strtoupper((string) $descricao);

        if (preg_match('/\bP\s?(13|20|45|90)\b/', $texto, $m) === 1) {
            return 'P'.$m[1];
        }

        if (preg_match('/\b(13|20|45|90)\s?KG\b/', $texto, $m) === 1) {
            return 'P'.$m[1];
        }

        return null;
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $t) {
            $t->dropColumn(['capacidade', 'capacidade_origem']);
        });
    }
};
