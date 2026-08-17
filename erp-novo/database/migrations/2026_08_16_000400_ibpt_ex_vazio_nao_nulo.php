<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ibpt_aliquotas.ex` vazio passa a ser string vazia, nunca NULL.
 *
 * **O defeito.** A tabela tem UNIQUE (ncm, uf, ex), mas em SQL `null != null`:
 * a restrição não impede repetição quando `ex` é NULL, e o `upsert` do
 * `IbptMigrator` nunca casa essas linhas. Como `ex` (a "exceção fiscal") é
 * vazio em 304.236 das 317.520 linhas da origem, cada recarga do IBPT inseria
 * quase a tabela inteira de novo — o destino chegou a 621.756 linhas para uma
 * origem de 317.520.
 *
 * É o mesmo padrão do defeito da T2.1 (upsert com chave que não casa), em outra
 * tabela: por isso a correção não é só limpar, é tornar a chave capaz de casar.
 *
 * **A limpeza.** Mantém a linha de MENOR id de cada (ncm, uf, ex) — todas as
 * cópias vieram da mesma origem e são idênticas nos valores de alíquota.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ibpt_aliquotas') || DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // 1) Deduplica ANTES de normalizar. A ordem importa: um UPDATE em massa
        //    de NULL para '' colide com o UNIQUE assim que encontra um par cuja
        //    versão com ex='' já existe. Agrupa-se por `coalesce(ex,'')` para
        //    tratar NULL e '' como a mesma chave, que é o que eles significam.
        $removidas = DB::affectingStatement(<<<'SQL'
            DELETE FROM public.ibpt_aliquotas t
             USING (
                   SELECT min(id) AS manter, ncm, uf, coalesce(ex, '') AS ex_norm
                     FROM public.ibpt_aliquotas
                    GROUP BY ncm, uf, coalesce(ex, '')
                   HAVING count(*) > 1
             ) d
             WHERE t.id <> d.manter
               AND t.ncm IS NOT DISTINCT FROM d.ncm
               AND t.uf  IS NOT DISTINCT FROM d.uf
               AND coalesce(t.ex, '') = d.ex_norm
        SQL);

        // 2) Só agora normaliza: sem duplicatas, nenhum UPDATE colide.
        DB::statement("UPDATE public.ibpt_aliquotas SET ex = '' WHERE ex IS NULL");

        // 3) NOT NULL com default '' fecha a porta: mesmo que alguém volte a
        //    gravar sem `ex`, a coluna guarda '' e o UNIQUE volta a valer.
        DB::statement("ALTER TABLE public.ibpt_aliquotas ALTER COLUMN ex SET DEFAULT ''");
        DB::statement('ALTER TABLE public.ibpt_aliquotas ALTER COLUMN ex SET NOT NULL');

        if ($removidas > 0) {
            echo "  ibpt_aliquotas: {$removidas} linha(s) duplicada(s) removida(s).\n";
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ibpt_aliquotas') || DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE public.ibpt_aliquotas ALTER COLUMN ex DROP NOT NULL');
        DB::statement('ALTER TABLE public.ibpt_aliquotas ALTER COLUMN ex DROP DEFAULT');
    }
};
