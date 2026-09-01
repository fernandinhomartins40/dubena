<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F7-03 — retrato da fonte legada no instante da leitura.
 *
 * ## O que a tarefa pede, e o que dá para entregar
 *
 * O plano pede "fonte bruta imutável, manifesto nominal, schema, hashes,
 * contagens, watermark e LOB integral; carga nova nunca derruba a última boa".
 *
 * Duas dessas exigências pressupõem uma **área de staging** — copiar a fonte
 * bruta para um lugar nosso antes de transformar. Este ETL não tem staging: ele
 * lê a conexão `legado` ao vivo e escreve no destino. São elas:
 *
 *  - **LOB integral** — copiar os binários exigiria onde pôr;
 *  - **carga nova nunca derruba a última boa** — só faz sentido havendo carga
 *    guardada que se possa derrubar.
 *
 * As outras cinco — manifesto nominal, schema, hashes, contagens e watermark —
 * **não dependem de staging**: são medições da fonte no instante da leitura. E
 * são elas que respondem a pergunta que a tarefa existe para responder:
 *
 * > *A fonte mudou entre o ensaio e o cutover?*
 *
 * Sem isso, um ensaio bem-sucedido na sexta não diz nada sobre a virada no
 * domingo — e é exatamente esse o risco que o F8 tenta cobrir.
 *
 * Entregar as cinco e declarar as duas faltantes é melhor que não entregar
 * nenhuma esperando a decisão de staging. O que **não** se pode fazer é chamar
 * isto de F7-03 completo: a linha `lob_integral` existe justamente para que o
 * gate de cutover consiga reprovar enquanto for `false`.
 *
 * ## Por que hash por tabela, e não do banco inteiro
 *
 * Um hash único responde "mudou?" e nada mais. Por tabela, responde **onde**
 * mudou — que é o que decide se a mudança é inócua (log crescendo) ou fatal
 * (cliente editado depois do ensaio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversao_snapshots', function (Blueprint $t) {
            $t->id();

            // Nulo: o snapshot pode ser tirado ANTES de existir execução — é
            // justamente o uso mais importante, medir a fonte para decidir se a
            // conversão pode começar.
            $t->foreignId('conversao_execucao_id')->nullable()
                ->constrained('conversao_execucoes')->cascadeOnDelete();

            $t->string('sistema_origem', 40);   // oracle | mysql_app | mysql_monitora
            $t->string('tabela', 120);

            // O manifesto NOMINAL: as colunas como a fonte as declara, em ordem,
            // com tipo. Coluna que some entre o ensaio e o cutover é o defeito
            // que passa despercebido — o migrador lê `null` e grava `null`, sem
            // erro nenhum.
            $t->json('colunas')->nullable();

            $t->unsignedBigInteger('linhas');

            // Hash do CONTEÚDO da tabela. Duas leituras com o mesmo hash provam
            // que nada mudou entre elas; hashes diferentes dizem exatamente qual
            // tabela mexeu.
            $t->string('hash_conteudo', 64)->nullable();

            // A marca d'água: o maior valor da coluna de corte (data de
            // alteração, id). É o que permite perguntar "entrou algo novo
            // depois do ensaio?" sem reler a tabela inteira.
            $t->string('watermark_coluna', 60)->nullable();
            $t->string('watermark_valor', 120)->nullable();

            // Declarado e FALSO enquanto não houver staging. Existe para que o
            // gate consiga reprovar: um campo ausente seria lido como "não se
            // aplica", e é o oposto — se aplica e está faltando.
            $t->boolean('lob_integral')->default(false);

            $t->timestamp('lido_em');
            $t->timestamps();

            // Uma linha por (execução, sistema, tabela). Sem a execução no
            // índice, um snapshot novo sobrescreveria o anterior — e comparar
            // dois momentos é o uso inteiro da tabela.
            $t->index(['sistema_origem', 'tabela', 'lido_em']);
        });

        $this->protegerNoPostgres();
    }

    /**
     * PLATFORM, como as outras tabelas da conversão.
     *
     * O snapshot mede a fonte ANTES de existir tenant — é o processo que cria os
     * tenants a partir do legado. Uma RLS por tenant esconderia justamente a
     * medição de quem ainda não foi criado.
     */
    private function protegerNoPostgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Escreve e NÃO apaga.
        //
        // Quase revoguei a escrita aqui, com a justificativa "quem escreve é o
        // console, como owner". Fui conferir e é **falso**: `SnapshotDaFonte`
        // usa `DB::table(...)`, ou seja a conexão default — que é `erp_app`. Só
        // as *migrations* rodam como `pgsql_owner`.
        //
        // Revogar quebraria o snapshot em silêncio: a escrita é protegida por
        // `catch` (fonte indisponível não pode derrubar quem chamou), então o
        // comando sairia dizendo que tirou o retrato sem ter gravado nada.
        //
        // O que dá para proteger sem quebrar nada é o `DELETE`: retrato apagado
        // é comparação que deixa de existir, e é justamente ela que autoriza —
        // ou barra — o cutover.
        DB::statement('REVOKE DELETE ON conversao_snapshots FROM erp_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('conversao_snapshots');
    }
};
