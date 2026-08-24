<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devolução parcial de comodato com contrato reemitido.
 *
 * **O problema.** `comodatos` guarda só o acumulado (`quantidade_devolvida`).
 * Isso responde "quanto ainda está com o cliente" e mais nada: não diz quando
 * cada devolução aconteceu, quantas foram, quem recebeu, nem permite reimprimir
 * o comprovante daquela entrega ou estornar um lançamento errado. Na prática o
 * operador evitava a parcial e fazia devolução total na mão.
 *
 * **O contrato é o ponto.** O documento de comodato descreve a posse VIGENTE.
 * Quando o cliente devolve 2 de 5, a posse muda — e o contrato assinado antes
 * deixa de descrever a realidade. Hoje o PDF é gerado do estado atual, então
 * depois da devolução ele simplesmente muda: o papel que o cliente assinou
 * some, e não há prova de qual versão foi assinada.
 *
 * Por isso duas tabelas:
 *
 * `comodato_movimentos` — o extrato. Uma linha por EMPRÉSTIMO, DEVOLUÇÃO ou
 * ESTORNO, com quantidade, data, quem lançou e o saldo resultante. É o que
 * torna o comodato auditável e a devolução parcial reversível.
 *
 * `comodato_contratos` — as versões emitidas. Cada emissão congela o texto e os
 * números daquele momento e ganha um número de versão. O contrato assinado
 * continua existindo depois da devolução parcial, e a versão nova (com o saldo
 * já descontado) é o que vai para assinatura.
 *
 * **`ENCERRADO` no vocabulário.** O ETL trouxe 745 comodatos do legado com essa
 * situação, que o código não conhecia — o guarda do PDF só barrava `DEVOLVIDO`,
 * então os 745 imprimiam contrato afirmando uma posse encerrada. Ver
 * `ComodatoPdfService::exigirImprimivel()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comodato_movimentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('comodato_id')->constrained('comodatos')->cascadeOnDelete();

            // EMPRESTIMO | DEVOLUCAO | ESTORNO
            $t->string('tipo', 20);

            // Sempre positiva. O sinal é dado pelo tipo — guardar negativo aqui
            // faria toda soma depender de lembrar da convenção.
            $t->decimal('quantidade', 14, 3);

            // Saldo em poder do cliente DEPOIS deste movimento. Redundante com a
            // soma do extrato, e proposital: é o que permite conferir a linha
            // isoladamente e detectar divergência sem recalcular a série.
            $t->decimal('saldo_apos', 14, 3);

            $t->date('data');

            // Estorno aponta para o movimento que anula — sem isso não há como
            // saber qual devolução foi cancelada, só que o saldo voltou.
            $t->foreignId('estorna_id')->nullable()->constrained('comodato_movimentos')->nullOnDelete();

            $t->string('observacao', 255)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['comodato_id', 'data']);
            $t->index(['empresa_id', 'tipo']);
        });

        Schema::create('comodato_contratos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('comodato_id')->constrained('comodatos')->cascadeOnDelete();

            // 1, 2, 3... Reemitir após devolução parcial gera a próxima versão;
            // a anterior continua válida como o papel que foi assinado.
            $t->unsignedInteger('versao');

            // Números CONGELADOS na emissão. Não são lidos do comodato na hora
            // de imprimir: o contrato tem que continuar dizendo o que dizia
            // quando foi assinado, mesmo que o saldo mude depois.
            $t->decimal('quantidade_contratada', 14, 3);
            $t->decimal('quantidade_devolvida', 14, 3);
            $t->decimal('quantidade_em_posse', 14, 3);

            // O que motivou esta versão: EMISSAO_INICIAL | DEVOLUCAO_PARCIAL |
            // REEMISSAO (dados do cliente mudaram, contrato reimpresso).
            $t->string('motivo', 30)->default('EMISSAO_INICIAL');

            // Movimento que disparou a reemissão, quando houver.
            $t->foreignId('movimento_id')->nullable()->constrained('comodato_movimentos')->nullOnDelete();

            // Marcado quando a via assinada volta. Contrato emitido e não
            // assinado não protege patrimônio nenhum — a diferença precisa
            // aparecer na tela.
            $t->timestamp('assinado_em')->nullable();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['comodato_id', 'versao']);
            $t->index(['empresa_id', 'comodato_id']);
        });

        $this->rls();
    }

    public function down(): void
    {
        Schema::dropIfExists('comodato_contratos');
        Schema::dropIfExists('comodato_movimentos');
    }

    /**
     * RLS por empresa + GRANT para a role de runtime.
     * A descoberta automática rodou uma vez e não alcança tabela criada depois
     * dela — ver CLAUDE.md.
     */
    private function rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['comodato_movimentos', 'comodato_contratos'] as $tabela) {
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

            try {
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO erp_app");
                DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$tabela}_id_seq TO erp_app");
            } catch (Throwable) {
                // Role inexistente (dev/CI): segue.
            }
        }
    }
};
