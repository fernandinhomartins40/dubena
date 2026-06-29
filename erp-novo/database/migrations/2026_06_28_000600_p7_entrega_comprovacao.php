<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P7 — Comprovação de entrega + ocorrências (app do entregador).
 *
 *  - pedido_ocorrencias: imprevistos da entrega (ausente, recusou, endereço não
 *    encontrado, etc.), com observação e opcional foto.
 *  - pedido_comprovacoes: prova de entrega — foto e/ou assinatura (paths em storage
 *    PRIVADO), além de quem recebeu.
 *
 * Ambas tenant-scoped (empresa_id) → RLS aplicada aqui. As fotos/assinaturas ficam
 * em disco privado (storage/app/private) com path por tenant — nunca em public.
 *
 * NO-OP de RLS fora do PostgreSQL (sqlite em teste). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_ocorrencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $t->foreignId('entregador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('tipo', 40);                 // ausente | recusou | endereco_nao_encontrado | outro
            $t->string('descricao', 255)->nullable();
            $t->string('foto_path')->nullable();    // storage privado
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'pedido_id']);
        });

        Schema::create('pedido_comprovacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $t->foreignId('entregador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('recebido_por')->nullable(); // nome de quem recebeu
            $t->string('foto_path')->nullable();        // storage privado
            $t->string('assinatura_path')->nullable();  // storage privado (PNG do canvas)
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'pedido_id']);
        });

        $this->aplicarRls(['pedido_ocorrencias', 'pedido_comprovacoes']);
    }

    public function down(): void
    {
        $this->removerRls(['pedido_ocorrencias', 'pedido_comprovacoes']);
        Schema::dropIfExists('pedido_comprovacoes');
        Schema::dropIfExists('pedido_ocorrencias');
    }

    /** @param  list<string>  $tabelas */
    private function aplicarRls(array $tabelas): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tabelas as $tabela) {
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
    }

    /** @param  list<string>  $tabelas */
    private function removerRls(array $tabelas): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tabelas as $tabela) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
        }
    }
};
