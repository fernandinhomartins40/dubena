<?php

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PapelSituacao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3-04A — o papel operacional da situação vira dado, não texto adivinhado.
 *
 * `EntregaService::iniciarRota()` procurava a situação de deslocamento assim:
 *
 *     LIKE '%saiu%' OR LIKE '%rota%' OR LIKE '%caminho%'
 *
 * ...e, não achando, CRIAVA "Saiu para entrega" para conseguir continuar.
 *
 * Numa revenda só isso funciona: ela escreveu essas palavras. Na segunda — "Em
 * trânsito", "Despachado", ou qualquer coisa em espanhol — a busca falha e o
 * sistema passa a cadastrar uma situação concorrente, invisível na configuração
 * que o cliente montou. Dois nomes para o mesmo momento, e o relatório dele
 * passa a somar errado.
 *
 * ## A conversão dos dados existentes
 *
 * A heurística antiga é usada UMA vez, aqui, para converter o que já existe —
 * e só onde ela é inequívoca: um único candidato no grupo. Grupo com dois
 * candidatos fica sem papel de propósito.
 *
 * Isso é deliberado e é a diferença entre converter e adivinhar. Escolher "a de
 * menor id" resolveria a migration e deixaria uma decisão errada gravada num
 * banco que ninguém mais vai revisar. Sem papel, a ação avisa que precisa de
 * configuração — que é uma pergunta respondível por quem sabe a resposta.
 */
return new class extends Migration
{
    /** Termos da heurística legada — usados só nesta conversão, nunca em runtime. */
    private const TERMOS_LEGADOS = ['%saiu%', '%rota%', '%caminho%', '%trânsito%', '%transito%'];

    public function up(): void
    {
        Schema::table('pedidosituacoes', function (Blueprint $t) {
            // Default NENHUM: papel é afirmação deliberada de quem configura.
            // Um default que infira algo reintroduziria o problema por outro
            // caminho.
            $t->string('papel', 20)->default(PapelSituacao::NENHUM->value)->after('efeito');
        });

        $this->converterDeslocamento();
    }

    /**
     * Marca EM_ROTA onde a heurística legada é inequívoca.
     */
    private function converterDeslocamento(): void
    {
        $candidatos = DB::table('pedidosituacoes')
            ->where('efeito', EfeitoPedido::PENDENTE->value)
            ->where('ativo', true)
            ->where(function ($q) {
                foreach (self::TERMOS_LEGADOS as $termo) {
                    $q->orWhereRaw('LOWER(descricao) LIKE ?', [$termo]);
                }
            })
            ->get(['id', 'grupo_id', 'descricao']);

        foreach ($candidatos->groupBy('grupo_id') as $doGrupo) {
            // Ambíguo: mais de um candidato no mesmo grupo. Deixar sem papel é
            // a resposta honesta — a ação vai pedir configuração em vez de
            // gravar um palpite que ninguém revisaria.
            if ($doGrupo->count() !== 1) {
                continue;
            }

            DB::table('pedidosituacoes')
                ->where('id', $doGrupo->first()->id)
                ->update(['papel' => PapelSituacao::EM_ROTA->value]);
        }
    }

    public function down(): void
    {
        Schema::table('pedidosituacoes', function (Blueprint $t) {
            $t->dropColumn('papel');
        });
    }
};
