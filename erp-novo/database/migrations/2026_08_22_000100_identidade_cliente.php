<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade de cliente sem depender de CPF.
 *
 * Medido na base real: 50.187 de 55.453 clientes (90,5%) não têm CPF nem CNPJ —
 * o documento simplesmente não é o identificador nesta operação. Sem outro
 * critério, o mesmo cliente vira vários cadastros: 3.486 clientes duplicados
 * carregando 20.702 pedidos (5% do histórico) foram medidos antes desta fase.
 *
 * A identificação passa a ser por TRAÇOS (telefone, nome fonético, endereço,
 * e-mail, documento), cada um com um peso. A soma dos traços que batem é o
 * escore de confiança, e é ele que decide entre consolidar, revisar ou criar.
 *
 * Três tabelas:
 *  - `cliente_identidades`: os traços de cada cliente, normalizados e indexados;
 *  - `cliente_vinculos`: quem foi consolidado em quem (histórico, não apaga);
 *  - `cliente_revisoes`: a fila de pares suspeitos para decisão humana.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Traços de identidade. Uma LINHA POR TRAÇO (não uma coluna por traço):
        // um cliente tem vários telefones e pode ter vários endereços, e a busca
        // por traço vira um índice simples em vez de OR sobre N colunas.
        Schema::create('cliente_identidades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

            // telefone | cpf | cnpj | email | nome_fonetico | endereco
            $t->string('tipo', 20);
            // Valor JÁ NORMALIZADO (minúsculo, sem acento, só dígitos quando
            // for o caso). Comparação é sempre igualdade exata sobre este valor.
            $t->string('valor', 180);

            // De onde veio: app | entregador | admin | legado | etl. Serve para
            // medir qual canal gera mais duplicata — e para desempatar: um
            // telefone verificado por SMS vale mais que um digitado à mão.
            $t->string('origem', 20)->nullable();
            $t->boolean('verificado')->default(false);

            $t->timestamps();

            // O índice que sustenta a busca de candidatos.
            $t->index(['empresa_id', 'tipo', 'valor']);
            $t->index(['cliente_id', 'tipo']);
            // Um traço idêntico não se repete para o mesmo cliente.
            $t->unique(['cliente_id', 'tipo', 'valor']);
        });

        // Consolidação: quem virou quem. NUNCA apaga o perdedor — ele é
        // desativado e apontado para o vencedor, para que um pedido antigo ou
        // um link externo com o id antigo ainda resolva para o cadastro certo.
        Schema::create('cliente_vinculos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();      // o que foi absorvido
            $t->foreignId('principal_id')->constrained('clientes')->cascadeOnDelete();    // o sobrevivente
            $t->unsignedSmallInteger('escore');       // confiança no momento da fusão
            $t->json('tracos')->nullable();           // quais traços casaram
            $t->string('decidido_por', 20);           // automatico | humano
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['empresa_id', 'principal_id']);
            // Um cliente só pode ter sido absorvido uma vez.
            $t->unique('cliente_id');
        });

        // Fila de revisão: o par ficou na faixa de dúvida (não consolida
        // sozinho, mas não é para ignorar). A VENDA JÁ ACONTECEU quando esta
        // linha é criada — a dúvida é trabalho de retaguarda, nunca trava de
        // balcão. Era exatamente a trava que fazia o entregador desistir do
        // cadastro e criar outro.
        Schema::create('cliente_revisoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();     // o recém-criado
            $t->foreignId('candidato_id')->constrained('clientes')->cascadeOnDelete();   // o possível mesmo
            $t->unsignedSmallInteger('escore');
            $t->json('tracos')->nullable();
            $t->string('origem', 20)->nullable();      // qual canal gerou

            // pendente | consolidado | descartado
            $t->string('situacao', 20)->default('pendente');
            $t->foreignId('decidido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('decidido_em')->nullable();
            $t->string('observacao', 255)->nullable();

            $t->timestamps();

            $t->index(['empresa_id', 'situacao', 'escore']);
            // O mesmo par não entra duas vezes na fila.
            $t->unique(['cliente_id', 'candidato_id']);
        });

        foreach (['cliente_identidades', 'cliente_vinculos', 'cliente_revisoes'] as $tabela) {
            $this->aplicarRls($tabela);
            $this->conceder($tabela);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_revisoes');
        Schema::dropIfExists('cliente_vinculos');
        Schema::dropIfExists('cliente_identidades');
    }

    private function aplicarRls(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = current_setting('app.empresa_id', true)::int
             )
             WITH CHECK (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = current_setting('app.empresa_id', true)::int
             )"
        );
    }

    private function conceder(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $role = 'erp_app';
        if (DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]) === null) {
            return;
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE {$tabela}_id_seq TO {$role}");
    }
};
