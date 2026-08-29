<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `config_globais` guarda o que separa concorrentes: CSRT do responsavel
 * tecnico, senha de SMTP, assinatura SAT e a chave do Google Maps. Duas
 * revendas concorrentes (ex.: Guarapuava e Pitanga) nao podem ver credencial
 * uma da outra.
 *
 * Duas falhas provadas na homologacao com a role de runtime:
 *
 * 1. A policy ainda era a LEGADA por `grupo_id`. Sem envelope nenhum, mas com
 *    `app.grupo_id` definido, a linha vinha — enquanto `clientes` na mesma
 *    condicao ja retornava zero. O consumidor `IntegracaoTenant::googleMapsKey()`
 *    ainda usa `withoutGrupo()`, entao a RLS era a unica barreira restante.
 *
 * 2. `google_maps_key` nao era `encrypted` nem `hidden`, ao contrario dos
 *    demais segredos da mesma tabela. Ela e credencial COBRADA: vazou em claro
 *    na prova.
 *
 * A coluna era `varchar(120)`; o texto cifrado passa disso e seria truncado em
 * silencio, entao ela vira `text` ANTES de cifrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // `text` cabe o payload cifrado. Feito antes do UPDATE de propósito:
        // cifrar em varchar(120) truncaria a chave e a perderia.
        Schema::table('config_globais', function ($table) {
            $table->text('google_maps_key')->nullable()->change();
        });

        // Cifra o que ja existe. `Crypt` e o mesmo mecanismo do cast
        // `encrypted`, entao o model le de volta sem tratamento especial.
        foreach (DB::table('config_globais')->whereNotNull('google_maps_key')->get(['id', 'google_maps_key']) as $linha) {
            $valor = (string) $linha->google_maps_key;
            if ($valor === '' || $this->jaCifrado($valor)) {
                continue; // reexecucao nao pode cifrar duas vezes
            }
            DB::table('config_globais')->where('id', $linha->id)
                ->update(['google_maps_key' => Crypt::encryptString($valor)]);
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE config_globais ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE config_globais FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON config_globais');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON config_globais
            USING (app_tenant_can_read_group_config(tenant_account_id, grupo_id))
            WITH CHECK (app_tenant_can_operate_group_config(tenant_account_id, grupo_id))
        SQL);
    }

    public function down(): void
    {
        // Nao volta a policy por `app.grupo_id` nem decifra a chave: seria
        // reabrir a brecha que esta migration existe para fechar.
    }

    /** Um payload do `Crypt` do Laravel e base64 de um JSON com iv/value/mac. */
    private function jaCifrado(string $valor): bool
    {
        $decodificado = base64_decode($valor, true);
        if ($decodificado === false) {
            return false;
        }
        $json = json_decode($decodificado, true);

        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }
};
