<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * COMPATIBILIDADE POSTGRES: converte todas as colunas boolean do schema public
 * para smallint.
 *
 * Motivo: o ERP tem 25 anos de SQL escrito para Oracle/MySQL, que tratam flags
 * como inteiro (tinyint) e comparam com `= 1` / `= 0` em 100+ pontos de raw SQL.
 * No PostgreSQL, boolean real NÃO compara com integer ("operator does not exist:
 * boolean = integer"), quebrando o app em runtime.
 *
 * Converter boolean -> smallint reproduz o comportamento do tinyint(1) do MySQL
 * original e faz todo o código legado funcionar sem alterar os 100+ pontos.
 * Só roda no PostgreSQL (no MySQL/Oracle não há o conflito).
 */
class ConvertBooleansToSmallintPg extends Migration
{
    public function up()
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $cols = DB::select("
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'public' AND data_type = 'boolean'
            ORDER BY table_name, column_name
        ");

        foreach ($cols as $c) {
            $t = $c->table_name;
            $col = $c->column_name;
            // Remove o default boolean antes (senão o ALTER TYPE falha),
            // converte para smallint (true->1, false->0) e repõe default 0.
            DB::statement("ALTER TABLE \"{$t}\" ALTER COLUMN \"{$col}\" DROP DEFAULT");
            DB::statement("ALTER TABLE \"{$t}\" ALTER COLUMN \"{$col}\" TYPE smallint USING (CASE WHEN \"{$col}\" THEN 1 ELSE 0 END)");
            DB::statement("ALTER TABLE \"{$t}\" ALTER COLUMN \"{$col}\" SET DEFAULT 0");
        }
    }

    public function down()
    {
        // Sem rollback automático: reverter exigiria saber quais eram boolean.
        // (Conversão é parte da compatibilização permanente com Postgres.)
    }
}
