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

        $antes = (int) DB::table('ibpt_aliquotas')->count();

        // Deduplica e normaliza em UM passo, por reconstrução da tabela.
        //
        // A via óbvia — `DELETE ... USING (SELECT ... GROUP BY)` — é
        // impraticável aqui: sem índice na chave natural, o Postgres reavalia o
        // agrupamento e levou mais de 2 HORAS sem terminar num teste com 621 mil
        // linhas. `DISTINCT ON` sobre um seq scan único resolve em minutos, e a
        // tabela é derivável (recarregável do legado ou do CSV do IBPT), então
        // reconstruí-la não corre risco de perda que o dump não cubra.
        //
        // `coalesce(ex,'')` no ORDER BY/DISTINCT trata NULL e '' como a mesma
        // chave — que é o que eles significam aqui.
        DB::statement(<<<'SQL'
            CREATE TABLE public.ibpt_aliquotas_novo AS
            SELECT DISTINCT ON (ncm, uf, coalesce(ex, ''))
                   id, ncm, uf, coalesce(ex, '') AS ex, nacional, importado,
                   estadual, municipal, versao, vigencia_inicio, vigencia_fim,
                   created_at, updated_at
              FROM public.ibpt_aliquotas
             ORDER BY ncm, uf, coalesce(ex, ''), id
        SQL);

        DB::statement('DROP TABLE public.ibpt_aliquotas');
        DB::statement('ALTER TABLE public.ibpt_aliquotas_novo RENAME TO ibpt_aliquotas');

        // Recria PK, sequence e índices (o CREATE TABLE AS não os herda).
        DB::statement('ALTER TABLE public.ibpt_aliquotas ADD PRIMARY KEY (id)');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS ibpt_aliquotas_id_seq OWNED BY public.ibpt_aliquotas.id');
        DB::statement("ALTER TABLE public.ibpt_aliquotas ALTER COLUMN id SET DEFAULT nextval('ibpt_aliquotas_id_seq')");
        DB::statement("SELECT setval('ibpt_aliquotas_id_seq', COALESCE((SELECT MAX(id) FROM public.ibpt_aliquotas), 1))");
        DB::statement('CREATE UNIQUE INDEX ibpt_aliquotas_ncm_uf_ex_unique ON public.ibpt_aliquotas (ncm, uf, ex)');
        DB::statement('CREATE INDEX ibpt_aliquotas_ncm_index ON public.ibpt_aliquotas (ncm)');
        DB::statement('CREATE INDEX ibpt_aliquotas_uf_index ON public.ibpt_aliquotas (uf)');

        $removidas = $antes - (int) DB::table('ibpt_aliquotas')->count();

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
