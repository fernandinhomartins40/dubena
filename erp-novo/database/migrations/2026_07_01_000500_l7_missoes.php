<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L7 — Missões de campo. Quando o entregador fica ocioso (sem entregas), o ERP o
 * coloca para trabalhar o comercial: panfletagem, visita, divulgação do Vale Gás,
 * prospecção, ação promocional, campanha de bairro.
 *
 *  - `missoes`             : o MOLDE (tipo, área por cerca OU centro+raio, meta, janela, exigência de foto).
 *  - `missao_atribuicoes`  : a EXECUÇÃO por entregador (status + auditoria/aprovação — L9 + adiamento).
 *  - `missao_visitas`      : cada RESIDÊNCIA visitada (status, tempo, venda vinculada).
 *  - `missao_evidencias`   : fotos (fachada/panfleto/visita) — storage privado, como o P7.
 *  - `missao_trilha`       : GPS contínuo durante a missão (percurso/distância/tempo por casa).
 *
 * + `logistica_configs.ociosidade_min`: minutos sem entrega para o gerador agir.
 *
 * Tenant-scoped → RLS inline em todas. NO-OP fora do PostgreSQL. Nada destrutivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->unsignedBigInteger('grupo_id')->nullable();
            // panfletagem | visita_comercial | divulgacao_valegas | prospeccao | acao_promocional | campanha_bairro
            $t->string('tipo', 30);
            $t->string('titulo', 160);
            $t->text('descricao')->nullable();
            // Área: cerca poligonal do Monitora OU centro+raio (fallback simples).
            $t->foreignId('cerca_id')->nullable()->constrained('monitora_cercas')->nullOnDelete();
            $t->decimal('centro_lat', 10, 7)->nullable();
            $t->decimal('centro_lng', 10, 7)->nullable();
            $t->unsignedInteger('raio_m')->nullable();
            $t->unsignedInteger('meta_visitas')->nullable();
            $t->time('janela_inicio')->nullable();
            $t->time('janela_fim')->nullable();
            $t->boolean('exige_foto')->default(true);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['empresa_id', 'ativo']);
        });

        Schema::create('missao_atribuicoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('missao_id')->constrained('missoes')->cascadeOnDelete();
            $t->foreignId('entregador_user_id')->constrained('users')->cascadeOnDelete();
            // atribuida | em_andamento | concluida | adiada | cancelada
            $t->string('status', 20)->default('atribuida');
            $t->boolean('automatica')->default(false); // gerada pelo motor de ociosidade?
            $t->dateTime('iniciada_em')->nullable();
            $t->dateTime('concluida_em')->nullable();
            // Adiamento (ETAPA 11): motivo + quando + aprovação do operador.
            $t->string('adiamento_motivo', 40)->nullable(); // nova_entrega|emergencia|veiculo|clima|outro
            $t->string('adiamento_detalhe', 255)->nullable();
            $t->dateTime('adiada_em')->nullable();
            $t->string('adiamento_aprovacao', 20)->nullable(); // pendente|aprovado|reprovado
            // Auditoria (L9): resultado da revisão do operador.
            $t->string('auditoria_resultado', 20)->nullable(); // aprovada|reprovada|revisao
            $t->foreignId('auditoria_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('auditoria_em')->nullable();
            $t->string('auditoria_observacao', 255)->nullable();
            $t->string('auditoria_sancao', 20)->nullable(); // advertencia|bonificacao|nenhuma
            $t->timestamps();
            $t->index(['empresa_id', 'entregador_user_id', 'status']);
            $t->index(['empresa_id', 'status']);
        });

        Schema::create('missao_visitas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('missao_atribuicao_id')->constrained('missao_atribuicoes')->cascadeOnDelete();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            // visitada | ausente | interessado | venda | frustrada
            $t->string('status', 20);
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $t->dateTime('iniciada_em')->nullable();
            $t->dateTime('finalizada_em')->nullable();
            $t->unsignedInteger('duracao_seg')->nullable();
            $t->string('observacao', 255)->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'missao_atribuicao_id']);
        });

        Schema::create('missao_evidencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('missao_visita_id')->constrained('missao_visitas')->cascadeOnDelete();
            $t->string('tipo', 20); // fachada | panfleto | visita
            $t->string('foto_path', 255);
            $t->timestamps();
        });

        Schema::create('missao_trilha', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('missao_atribuicao_id')->constrained('missao_atribuicoes')->cascadeOnDelete();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->dateTime('registrado_em');
            $t->index(['missao_atribuicao_id', 'registrado_em']);
        });

        // Ociosidade (minutos sem entrega) que dispara o gerador de missões.
        Schema::table('logistica_configs', function (Blueprint $t) {
            $t->unsignedInteger('ociosidade_min')->default(30)->after('teto_carga');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['missoes', 'missao_atribuicoes', 'missao_visitas', 'missao_evidencias', 'missao_trilha'] as $tabela) {
            $this->aplicarRls($tabela);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach (['missoes', 'missao_atribuicoes', 'missao_visitas', 'missao_evidencias', 'missao_trilha'] as $tabela) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            }
        }

        Schema::table('logistica_configs', fn (Blueprint $t) => $t->dropColumn('ociosidade_min'));
        Schema::dropIfExists('missao_trilha');
        Schema::dropIfExists('missao_evidencias');
        Schema::dropIfExists('missao_visitas');
        Schema::dropIfExists('missao_atribuicoes');
        Schema::dropIfExists('missoes');
    }

    private function aplicarRls(string $tabela): void
    {
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
};
