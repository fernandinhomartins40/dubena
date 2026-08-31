<?php

use App\Domain\Estoque\TipoLocalEstoque;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3-06 — o setor de estoque passa a dizer que espécie de lugar é.
 *
 * `setores` tinha descrição e `ativo`. Depósito da revenda, estoque em poder de
 * um franqueado e carga de veículo conviviam na mesma lista, indistinguíveis —
 * e o seletor de "onde lançar a entrada" oferecia os três.
 *
 * ## A conversão, e por que ela é segura aqui
 *
 * `CargaFranqueadoService` cria os setores de custódia com um padrão que ele
 * mesmo escreve: `'Em poder de '.$colaborador->nome`. Não é heurística sobre
 * texto que um humano digitou — é reconhecer a assinatura do próprio código.
 *
 * A diferença importa: nas outras conversões desta fase (F3-02, F3-04A) a
 * heurística lia texto de origem desconhecida e por isso deixava o ambíguo sem
 * classificar. Aqui o prefixo é gerado pelo sistema, e o vínculo é confirmado
 * por `colaboradores.setor_estoque_id` — que é a evidência estrutural.
 *
 * Por isso a conversão usa o VÍNCULO como fonte primária, e o prefixo apenas
 * para o que ficou órfão.
 *
 * Todo o resto vira DEPOSITO: é o que esses setores sempre foram, e o default
 * mais conservador — um depósito a mais na lista de armazéns é um erro visível
 * e corrigível; um depósito classificado como custódia sumiria dos lançamentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setores', function (Blueprint $t) {
            $t->string('tipo', 20)->default(TipoLocalEstoque::DEPOSITO->value)->index();
        });

        $this->converter();
    }

    private function converter(): void
    {
        // Fonte primária: o vínculo estrutural. Um setor apontado por
        // `colaboradores.setor_estoque_id` é, por definição, custódia de pessoa.
        if (Schema::hasColumn('colaboradores', 'setor_estoque_id')) {
            $vinculados = DB::table('colaboradores')
                ->whereNotNull('setor_estoque_id')
                ->pluck('setor_estoque_id');

            if ($vinculados->isNotEmpty()) {
                DB::table('setores')
                    ->whereIn('id', $vinculados)
                    ->update(['tipo' => TipoLocalEstoque::CUSTODIA_PESSOA->value]);
            }
        }

        // Órfãos: o colaborador foi removido, mas o setor com a assinatura do
        // `CargaFranqueadoService` continua lá com saldo. Deixá-lo como depósito
        // faria mercadoria em poder de terceiro parecer estoque próprio.
        DB::table('setores')
            ->where('tipo', TipoLocalEstoque::DEPOSITO->value)
            ->whereRaw("UPPER(descricao) LIKE 'EM PODER DE %'")
            ->update(['tipo' => TipoLocalEstoque::CUSTODIA_PESSOA->value]);
    }

    public function down(): void
    {
        Schema::table('setores', function (Blueprint $t) {
            $t->dropColumn('tipo');
        });
    }
};
