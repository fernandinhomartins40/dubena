<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mesma lacuna que a `001800` fechou para os pivots, agora para a configuracao
 * group-scoped: `transportadoras` e `malha_fiscal` seguiam na policy LEGADA por
 * `grupo_id` e so trocariam para a canonica pelo comando de conversao
 * documental — que existe para converter legado sob evidencia.
 *
 * Na copia de homologacao as duas estao VAZIAS. Sem linha nao ha titularidade a
 * decidir, e num tenant novo (que nunca roda a conversao) elas ficariam
 * indefinidamente na policy antiga, que confia em `app.grupo_id` — exatamente a
 * barreira fail-open que F1 substitui.
 *
 * Tabela com dados nao e tocada: inferir dono de linha existente e o que o
 * resto de F1 proibe. Ela continua esperando o comando documental.
 *
 * Estas sao group-scoped, entao a policy usa as funcoes de configuracao de
 * grupo — nao as COMPANY usadas pela `001800`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CONFIGURACOES = [
        'transportadoras',
        'malha_fiscal',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CONFIGURACOES as $tabela) {
            if ((int) DB::table($tabela)->count() > 0) {
                continue;
            }

            DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$tabela}
                USING (app_tenant_can_read_group_config(tenant_account_id, grupo_id))
                WITH CHECK (app_tenant_can_operate_group_config(tenant_account_id, grupo_id))
            SQL);
        }
    }

    public function down(): void
    {
        // Nao restaura a policy legada por grupo_id: uma tabela que passou a ser
        // isolada pela fronteira aprovada nao volta a confiar em app.grupo_id.
    }
};
