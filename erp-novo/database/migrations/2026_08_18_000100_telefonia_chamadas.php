<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Telefonia / bina no atendimento (T4.4 do PLANO_PRODUCAO).
 *
 * **A lacuna.** No legado, o atendimento do disk-gás identifica a chamada
 * entrante e abre a ficha do cliente. O PABX faz POST em `gravarTelefone`
 * (`ApiController:176`), que grava em `monitoramentochamadas`; a tela faz
 * polling e o operador aceita (abre a ficha) ou rejeita — e a rejeição vira
 * registro em `ligacoestelefonicas` (`routes/web.php:1027`).
 *
 * No erp-novo: greps por `ligacoestelefonicas` / `monitoramentochamadas` /
 * `\bbina\b` retornavam **zero**.
 *
 * ⚠️ **Condicionada à decisão do dono.** O plano pergunta se o call-center usa
 * bina hoje. Implementado para que "sim" não custe nada; se for "não", remover
 * é apagar a tabela, o service e o controller.
 *
 * **Duas tabelas porque são dois tempos.** `telefonia_chamadas` é a FILA — o que
 * está tocando agora, efêmero, esvaziado conforme o operador atende. O legado
 * fazia `delete()` na aceitação. `telefonia_ligacoes` é o HISTÓRICO, que
 * sobrevive: é dele que sai qualquer relatório de atendimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telefonia_chamadas')) {
            Schema::create('telefonia_chamadas', function (Blueprint $t) {
                $t->id();

                $t->unsignedBigInteger('empresa_id')->nullable();
                $t->unsignedBigInteger('grupo_id')->nullable();

                // Telefone COMO O PABX MANDOU, sem máscara. O legado formatava
                // na gravação — o que tornava impossível casar com o cadastro
                // quando o formato divergia. Aqui a normalização é na busca.
                $t->string('telefone', 30);

                // Ramal/origem que o PABX informa, quando informa.
                $t->string('ramal', 20)->nullable();

                // Cliente resolvido pelo telefone, se houver um só. Nulo quando
                // não achou ou quando há mais de um — a tela mostra as opções.
                $t->unsignedBigInteger('cliente_id')->nullable();

                $t->timestamp('recebida_em')->useCurrent();

                $t->timestamps();

                // A tela consulta "o que está tocando agora nesta empresa".
                $t->index(['empresa_id', 'recebida_em']);
                $t->index('telefone');
            });
        }

        if (! Schema::hasTable('telefonia_ligacoes')) {
            Schema::create('telefonia_ligacoes', function (Blueprint $t) {
                $t->id();

                $t->unsignedBigInteger('empresa_id')->nullable();
                $t->unsignedBigInteger('grupo_id')->nullable();

                $t->string('telefone', 30);
                $t->unsignedBigInteger('cliente_id')->nullable();

                // Quem atendeu. Nulo se a chamada expirou sem ninguém pegar.
                $t->unsignedBigInteger('user_id')->nullable();

                $t->boolean('atendida')->default(false);
                $t->boolean('rejeitada')->default(false);

                // Por que foi rejeitada — trote, engano, cliente desligou.
                $t->string('motivo', 255)->nullable();

                // Pedido gerado a partir da chamada, quando virou venda. É o que
                // permite medir conversão do call-center.
                $t->unsignedBigInteger('pedido_id')->nullable();

                $t->timestamps();

                $t->index(['empresa_id', 'created_at']);
                $t->index('telefone');
            });
        }

        $this->aplicarRls('telefonia_chamadas');
        $this->aplicarRls('telefonia_ligacoes');
    }

    public function down(): void
    {
        Schema::dropIfExists('telefonia_ligacoes');
        Schema::dropIfExists('telefonia_chamadas');
    }

    /**
     * RLS por empresa + GRANT para a role de runtime.
     *
     * Isolamento por `empresa_id`: a chamada entra num número que é de uma
     * revenda específica, e o atendente de uma filial não pode ver a fila de
     * outra.
     */
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
                 OR empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
             )
             WITH CHECK (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
             )"
        );

        $role = 'erp_app';
        if (DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]) === null) {
            return;
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE {$tabela}_id_seq TO {$role}");
    }
};
