<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * F7 — idempotência por chave de requisição, base da operação offline.
 *
 * **O problema.** O app em rota perde sinal, guarda a ação numa fila local e
 * reenvia quando a rede volta. Sem idempotência, um reenvio que o servidor já
 * processou (mas cuja resposta se perdeu no caminho) baixa o mesmo pedido duas
 * vezes, ou cria dois pedidos para a mesma venda.
 *
 * **Por que o legado não precisou disto.** O MovelApp não é offline de verdade:
 * `PedidoStatusActivity:264` só grava no SQLite local DEPOIS do `status OK` do
 * servidor — a baixa exige rede. Ele evita o problema não tendo fila. Fazer
 * melhor exige resolver o que ele nunca resolveu.
 *
 * **Como funciona.** O app manda `Idempotency-Key` (uuid gerado no dispositivo,
 * estável entre tentativas). Na primeira vez, a resposta é gravada aqui; nas
 * seguintes, é devolvida sem reexecutar. A chave é única POR EMPRESA — chave
 * repetida entre tenants não colide.
 *
 * O corpo guardado tem prazo: `expira_em` permite limpar o que já não pode ser
 * reenviado (o app desiste muito antes disso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisicoes_idempotentes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('chave', 100);
            // Guarda rota+método: a mesma chave reusada noutra rota é erro do app,
            // e responder o corpo da rota errada seria pior que recusar.
            $t->string('rota', 190);
            $t->string('metodo', 10);

            // Impressão do payload: reenvio com corpo diferente sob a mesma chave
            // é bug do cliente — melhor recusar que devolver resposta que não
            // corresponde ao que foi pedido agora.
            $t->string('payload_hash', 64);

            $t->unsignedSmallInteger('status_http')->nullable();
            $t->json('resposta')->nullable();

            // Marca de "em andamento": duas tentativas simultâneas (rede voltou
            // durante o retry) não podem executar as duas.
            $t->boolean('concluida')->default(false);

            $t->timestamp('expira_em')->nullable();
            $t->timestamps();

            $t->unique(['empresa_id', 'chave']);
            $t->index('expira_em');
        });

        $this->aplicarRls('requisicoes_idempotentes');
        $this->conceder('requisicoes_idempotentes');
    }

    public function down(): void
    {
        Schema::dropIfExists('requisicoes_idempotentes');
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
