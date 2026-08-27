<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importação de logradouros por base de CEP.
 *
 * DUAS coisas aqui:
 *
 * 1. `ruas.bairro_id` — o schema novo perdeu um vínculo que o LEGADO tinha
 *    (a tabela espelhada `legado.ruas` tem `bairro_id`). Sem ele, rua e bairro
 *    são listas paralelas penduradas na cidade e é impossível derivar o bairro
 *    a partir da rua, que é exatamente o que um autocompletar de endereço
 *    precisa fazer. Nullable porque as 2.609 ruas já cadastradas não têm o dado.
 *
 * 2. `importacoes_logradouro` — registro de que uma cidade já foi importada.
 *    A varredura da base de CEP custa centenas de requisições; sem esse registro
 *    não há como saber se a cidade já foi feita, e reimportar seria o default
 *    acidental.
 *
 * O escopo é por GRUPO, igual a `cidades`/`ruas`/`bairros`: a importação
 * popula o cadastro do grupo, não um catálogo global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruas', function (Blueprint $t) {
            $t->foreignId('bairro_id')->nullable()->after('cidade_id')
                ->constrained('bairros')->nullOnDelete();
        });

        Schema::create('importacoes_logradouro', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cidade_id')->constrained('cidades')->cascadeOnDelete();
            $t->string('fonte', 30)->default('viacep');
            $t->unsignedInteger('ruas_criadas')->default(0);
            $t->unsignedInteger('bairros_criados')->default(0);
            $t->unsignedInteger('ruas_atualizadas')->default(0);
            $t->unsignedInteger('consultas')->default(0);
            // Quantos termos bateram o teto de 50 da API mesmo após o refino:
            // é a medida honesta de que a importação pode estar INCOMPLETA.
            $t->unsignedInteger('termos_truncados')->default(0);
            $t->string('situacao', 20)->default('concluida');
            $t->text('erro')->nullable();
            $t->foreignId('executado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['grupo_id', 'cidade_id']);
        });

        $this->rls();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON importacoes_logradouro');
        }

        Schema::dropIfExists('importacoes_logradouro');

        Schema::table('ruas', function (Blueprint $t) {
            $t->dropForeign(['bairro_id']);
            $t->dropColumn('bairro_id');
        });
    }

    /**
     * RLS por GRUPO (a tabela não tem empresa_id) + GRANT para a role de runtime.
     * A descoberta automática não alcança tabela criada depois dela — ver CLAUDE.md.
     */
    private function rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE importacoes_logradouro ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE importacoes_logradouro FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON importacoes_logradouro');
        DB::statement(
            "CREATE POLICY tenant_isolation ON importacoes_logradouro
             USING (
                 nullif(current_setting('app.grupo_id', true), '') IS NULL
                 OR grupo_id = current_setting('app.grupo_id', true)::int
             )
             WITH CHECK (
                 nullif(current_setting('app.grupo_id', true), '') IS NULL
                 OR grupo_id = current_setting('app.grupo_id', true)::int
             )"
        );

        if (DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'erp_app') AS present")->present) {
            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON importacoes_logradouro TO erp_app');
            DB::statement('GRANT USAGE, SELECT ON SEQUENCE importacoes_logradouro_id_seq TO erp_app');
        }
    }
};
