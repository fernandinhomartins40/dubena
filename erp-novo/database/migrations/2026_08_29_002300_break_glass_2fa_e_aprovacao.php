<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha o F2-05: 2FA no ato da concessao, aprovacao para escopo critico e
 * anti-replay de OTP.
 *
 * O que faltava depois de F2-05:
 *
 * 1. Qualquer um com acesso ao console concedia break-glass. Agora a concessao
 *    exige o OTP do proprio usuario elevado — quem pede prova que e ele.
 * 2. Nao havia diferenca entre "olhar um cadastro" e "mexer em dinheiro". O
 *    campo `escopo` separa LEITURA de OPERACAO, e OPERACAO exige aprovacao de um
 *    PlatformAdmin distinto de quem pediu.
 * 3. `Totp::verificar` aceita uma janela de +-1 passo, entao o MESMO codigo de 6
 *    digitos vale por ~90 segundos e podia ser reapresentado a vontade. A tabela
 *    `otp_consumidos` registra cada par usuario+codigo usado e recusa o segundo
 *    uso — o replay que o gate F2-07 exige impedir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('break_glass_grants', function (Blueprint $t) {
            // LEITURA e o default: o acesso menos perigoso nao precisa de
            // aprovacao, e tornar OPERACAO o padrao convida a pedir demais.
            $t->string('escopo', 20)->default('LEITURA')->after('empresa_id');

            $t->timestamp('aprovado_em')->nullable()->after('concedido_por_platform_admin_id');
            $t->foreignId('aprovado_por_platform_admin_id')->nullable()->after('aprovado_em')
                ->constrained('platform_admins')->nullOnDelete();

            // Prova de que o OTP foi conferido no ato da concessao.
            $t->timestamp('twofa_verificado_em')->nullable()->after('aprovado_por_platform_admin_id');
        });

        Schema::create('otp_consumidos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Hash, nao o codigo: a trilha nao precisa guardar o segredo em claro.
            $t->string('codigo_hash', 64);
            $t->timestamp('usado_em')->useCurrent();

            // A unicidade e a barreira: o segundo INSERT do mesmo par falha.
            $t->unique(['user_id', 'codigo_hash']);
            $t->index('usado_em');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Ato de plataforma: o runtime le para decidir, mas nao escreve.
        DB::statement('ALTER TABLE otp_consumidos ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE otp_consumidos FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON otp_consumidos');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON otp_consumidos
            USING (true)
            WITH CHECK (true)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_consumidos');

        Schema::table('break_glass_grants', function (Blueprint $t) {
            $t->dropConstrainedForeignId('aprovado_por_platform_admin_id');
            $t->dropColumn(['escopo', 'aprovado_em', 'twofa_verificado_em']);
        });
    }
};
