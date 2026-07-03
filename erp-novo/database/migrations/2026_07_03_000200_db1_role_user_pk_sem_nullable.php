<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DB-1 (auditoria) — converte a PK de `role_user` em bancos JÁ MIGRADOS.
 *
 * PROBLEMA: a PK composta (user_id, role_id, empresa_id) forçou NOT NULL em
 * `empresa_id` no PostgreSQL (PK não aceita NULL). O código lê papéis GLOBAIS
 * (`wherePivotNull('empresa_id')` em User::temPermissao), mas persistir um papel
 * global falhava com not-null violation.
 *
 * SOLUÇÃO: PK própria `id` + unicidade em DOIS índices parciais (por-empresa e
 * global). A migration 0001 já cria a estrutura nova em instalações FRESCAS
 * (sqlite/testes/CI incluídos); esta converte bancos pgsql existentes
 * (homolog/produção). Idempotente: só age se a PK antiga ainda existir.
 * NO-OP fora do pgsql (fresh sqlite já nasce certo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $temPkAntiga = DB::selectOne(
            "SELECT 1 AS x FROM pg_constraint WHERE conname = 'role_user_pk' AND conrelid = 'role_user'::regclass",
        );
        if (! $temPkAntiga) {
            return; // já convertida (ou instalação fresca com a 0001 nova)
        }

        DB::statement('ALTER TABLE role_user DROP CONSTRAINT role_user_pk');

        // A PK antiga marcou empresa_id NOT NULL implicitamente; soltar o NOT NULL
        // é o que permite persistir o papel global.
        DB::statement('ALTER TABLE role_user ALTER COLUMN empresa_id DROP NOT NULL');

        DB::statement('ALTER TABLE role_user ADD COLUMN IF NOT EXISTS id BIGSERIAL');
        DB::statement('ALTER TABLE role_user ADD PRIMARY KEY (id)');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS role_user_empresa_unique ON role_user (user_id, role_id, empresa_id) WHERE empresa_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS role_user_global_unique ON role_user (user_id, role_id) WHERE empresa_id IS NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Volta só se não houver papéis globais (a PK antiga não os comporta).
        $temGlobais = DB::selectOne('SELECT 1 AS x FROM role_user WHERE empresa_id IS NULL LIMIT 1');
        if ($temGlobais) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS role_user_empresa_unique');
        DB::statement('DROP INDEX IF EXISTS role_user_global_unique');
        DB::statement('ALTER TABLE role_user DROP COLUMN IF EXISTS id');
        DB::statement('ALTER TABLE role_user ALTER COLUMN empresa_id SET NOT NULL');
        DB::statement('ALTER TABLE role_user ADD CONSTRAINT role_user_pk PRIMARY KEY (user_id, role_id, empresa_id)');
    }
};
