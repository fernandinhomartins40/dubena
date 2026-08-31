<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F4-01 — o ledger de estoque ganha fronteira de tenant e chave de idempotência.
 *
 * `estoquehistorico` já É um ledger, e um bom: quantidade assinada
 * (+entrada/−saída), tipo, evento causal (`origem`/`origem_id`), ator e
 * `saldo_resultante`. O que a tarefa pede e não existia:
 *
 * ## 1. `tenant_account_id` — a coluna já existia, vazia
 *
 * A migration `000300` (F1) a acrescentou a `estoquehistorico` junto com dezenas
 * de outras tabelas, e **nada a preenchia**. Coluna vazia não responde pergunta
 * nenhuma, e é pior que coluna ausente porque parece resolvida — foi exatamente
 * o mesmo achado do F2-06 nas trilhas de auditoria.
 *
 * Aqui, portanto, a migration só faz o backfill; quem passa a preencher nas
 * linhas novas é o `EstoqueService`.
 *
 * ## 2. `chave_idempotencia`
 *
 * O gate da F4 exige: **"rerun não duplica"**.
 *
 * Hoje a proteção existe, mas é POR CASO DE USO — o pedido tem a flag
 * `estoque_movimentado`. Transferência, acerto e carga do franqueado não têm
 * nada: reprocessar um job, ou o operador clicando duas vezes, grava o
 * movimento de novo. E movimento de estoque duplicado não dá erro: dá um saldo
 * que não bate, descoberto no inventário, quando ninguém mais liga o sintoma à
 * causa.
 *
 * A chave é `NULL`-ável de propósito: movimento sem chave continua sendo
 * aceito, porque exigir uma de todos os chamadores num único lote quebraria os
 * que ainda não a informam. O que a coluna garante é que **quem informa uma
 * chave nunca duplica** — e a unicidade parcial no Postgres é o que faz isso
 * valer no banco, não só no código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoquehistorico', function (Blueprint $t) {
            // A coluna ja veio da 000300 (F1) — aqui so o que falta.
            if (! Schema::hasColumn('estoquehistorico', 'tenant_account_id')) {
                $t->unsignedBigInteger('tenant_account_id')->nullable()->after('empresa_id')->index();
            }

            $t->string('chave_idempotencia', 120)->nullable()->after('origem_id');
        });

        $this->herdarTenantDaEmpresa();
        $this->unicidadeParcial();
    }

    /**
     * Herda o tenant das empresas — o movimento sempre pertenceu a uma.
     *
     * Subselect correlacionado (e não `UPDATE ... FROM`) porque Postgres e
     * sqlite escrevem o segundo de formas diferentes.
     */
    private function herdarTenantDaEmpresa(): void
    {
        if (! Schema::hasColumn('empresas', 'tenant_account_id')) {
            return;
        }

        DB::statement(
            'UPDATE estoquehistorico SET tenant_account_id = ('
            .'SELECT tenant_account_id FROM empresas WHERE empresas.id = estoquehistorico.empresa_id'
            .') WHERE tenant_account_id IS NULL'
        );
    }

    /**
     * Unicidade só onde a chave existe.
     *
     * Índice PARCIAL: sem o `WHERE`, todas as linhas sem chave colidiriam entre
     * si — e são a maioria. O sqlite dos testes também aceita índice parcial, o
     * que mantém o comportamento igual nos dois bancos.
     *
     * A chave inclui `empresa_id`: duas revendas podem legitimamente usar o
     * mesmo identificador de origem (o número do pedido reinicia por empresa), e
     * uma unicidade global faria a segunda revenda perder o movimento.
     */
    private function unicidadeParcial(): void
    {
        // A sintaxe de indice parcial e a mesma nos dois bancos, entao nao ha
        // ramo por driver aqui — o que muda entre eles e so o DROP no `down`.
        DB::statement(
            'CREATE UNIQUE INDEX estoquehistorico_idempotencia_unq ON estoquehistorico '
            .'(empresa_id, chave_idempotencia) WHERE chave_idempotencia IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS estoquehistorico_idempotencia_unq');

        // `tenant_account_id` NAO e removida: ela e da migration 000300, e
        // derruba-la aqui faria este rollback destruir o trabalho de outra.
        Schema::table('estoquehistorico', function (Blueprint $t) {
            $t->dropColumn('chave_idempotencia');
        });
    }
};
