<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vigilância do comodato: o vasilhame roda ou está parado na concorrência?
 *
 * **O problema relatado.** Cliente com comodato grande pode estar enchendo o
 * botijão da revenda em outro lugar. Hoje nada cruza posse de vasilhame com
 * histórico de compra — a revenda só descobre quando alguém estranha.
 *
 * **O que os dados mostram** (produção, 2026-08-24). O giro — quantidade
 * comprada em 180 dias dividida pelos vasilhames em posse — separa nitidamente:
 *
 *     BRASILCOMP    12 vasilhames, 300 compras → 25,0x   saudável
 *     REPINHO       48 vasilhames, 1077       → 22,4x   saudável
 *     RESIDENCIAL   25 vasilhames,   92       →  3,7x   desproporcional
 *     MULTI PLY     13 vasilhames,   17       →  1,3x   desproporcional
 *     J ALEI        60 vasilhames,    0       →  0,0x   parado desde 2022
 *
 * **Por que não basta um limiar fixo.** Hospital, restaurante, condomínio e
 * revendedor consomem em ritmos diferentes; um corte único puniria quem sempre
 * consumiu pouco e deixaria passar quem caiu de 20x para 8x — que é justamente
 * o sinal de desvio. Por isso a régua é ADAPTATIVA: cada cliente é comparado com
 * o que ELE mesmo comprava (`baseline_giro`), com um piso absoluto para o caso
 * de quem nunca teve histórico.
 *
 * Três tabelas:
 *
 * `comodato_avaliacoes` — a foto periódica de cada cliente com comodato: posse,
 * compras na janela, giro, baseline, dias sem comprar. É o que permite dizer
 * "caiu" em vez de só "está baixo", e o que dá o gráfico da evolução.
 *
 * `alertas` — a central que a equipe tria. Genérica de propósito (`origem`):
 * nasce com o comodato, mas estoque baixo e inconsistência de saldo cabem aqui
 * depois, em vez de cada um inventar sua fila.
 *
 * `comodato_config` — a régua por empresa. Sem isto os limiares ficariam
 * enterrados no código, e calibrar exigiria deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comodato_avaliacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

            $t->date('referencia');

            // Vasilhames em poder do cliente no momento da avaliação.
            $t->decimal('em_posse', 14, 3);

            // Quanto comprou do produto compatível na janela (dias_janela).
            $t->decimal('comprado_janela', 14, 3)->default(0);
            $t->unsignedSmallInteger('dias_janela');
            $t->unsignedInteger('pedidos_janela')->default(0);

            // comprado_janela / em_posse. A métrica central: quantas vezes cada
            // vasilhame emprestado foi reabastecido na janela.
            $t->decimal('giro', 8, 3)->default(0);

            // O giro histórico DESTE cliente, dos 12 meses anteriores à janela.
            // Nulo quando não há histórico suficiente — e nesse caso a régua cai
            // para o piso absoluto, nunca para "sem baseline logo sem alerta".
            $t->decimal('baseline_giro', 8, 3)->nullable();

            // Queda percentual contra o próprio baseline. É o sinal forte.
            $t->decimal('variacao', 8, 3)->nullable();

            $t->unsignedSmallInteger('dias_sem_compra')->nullable();

            // OK | ATENCAO | CRITICO — o veredito desta avaliação.
            $t->string('classificacao', 12);

            // Por que classificou assim, em texto legível pelo operador. Guardar
            // o motivo evita a pergunta "por que este cliente apareceu aqui?"
            // seis meses depois, quando a régua já mudou.
            $t->string('motivo', 255)->nullable();

            $t->timestamps();

            // Uma avaliação por cliente por dia: o cron é idempotente, e rodar
            // duas vezes não pode duplicar a série histórica.
            $t->unique(['cliente_id', 'referencia']);
            $t->index(['empresa_id', 'referencia']);
            $t->index(['empresa_id', 'classificacao']);
        });

        Schema::create('alertas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();

            // Quem gerou: comodato_giro, comodato_vencimento, ... Genérico para
            // outros domínios entrarem sem tabela nova.
            $t->string('origem', 40);

            // BAIXA | MEDIA | ALTA — ordena a fila de triagem.
            $t->string('severidade', 10)->default('MEDIA');

            $t->string('titulo');
            $t->text('descricao')->nullable();

            // A quem o alerta se refere (polimórfico leve: a maioria é cliente).
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->cascadeOnDelete();
            $t->foreignId('comodato_id')->nullable()->constrained('comodatos')->cascadeOnDelete();

            // Números que sustentam o alerta, para a tela não recalcular.
            $t->json('dados')->nullable();

            // ABERTO | EM_ANALISE | RESOLVIDO | IGNORADO
            $t->string('situacao', 12)->default('ABERTO');

            // Chave de deduplicação: o mesmo problema não pode virar alerta novo
            // a cada execução do cron. Enquanto houver um ABERTO com esta chave,
            // a rodada seguinte ATUALIZA em vez de criar.
            $t->string('chave', 120);

            $t->foreignId('responsavel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('resolucao')->nullable();
            $t->timestamp('resolvido_em')->nullable();
            $t->foreignId('resolvido_por')->nullable()->constrained('users')->nullOnDelete();

            // Quantas rodadas seguidas o problema persistiu. Um alerta que volta
            // há 5 meses diz algo diferente de um que apareceu ontem.
            $t->unsignedInteger('ocorrencias')->default(1);
            $t->timestamp('ultima_ocorrencia')->nullable();

            $t->timestamps();

            $t->index(['empresa_id', 'situacao', 'severidade']);
            $t->index(['empresa_id', 'origem']);
            // Parcial: só alerta ABERTO precisa ser único por chave. Resolvido
            // vira histórico, e o mesmo problema pode legitimamente reabrir.
            $t->index(['chave', 'situacao']);
        });

        Schema::create('comodato_config', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();

            // Janela de apuração do giro. 180 dias: curto demais confunde
            // sazonalidade com desvio; longo demais atrasa a descoberta.
            $t->unsignedSmallInteger('dias_janela')->default(180);

            // Piso absoluto de giro, para quem não tem baseline. 4,0 vem da
            // medição: abaixo disso todos os casos observados eram anômalos.
            $t->decimal('giro_minimo', 8, 3)->default(4);

            // Giro abaixo deste valor é CRÍTICO independentemente do baseline.
            $t->decimal('giro_critico', 8, 3)->default(1);

            // Queda percentual contra o próprio histórico que dispara ATENCAO.
            $t->decimal('queda_atencao', 5, 2)->default(40);

            // ... e CRITICO. Cair 70% do que se comprava é o sinal forte.
            $t->decimal('queda_critica', 5, 2)->default(70);

            // Dias sem nenhuma compra que já bastam para alertar, sozinhos.
            $t->unsignedSmallInteger('dias_sem_compra_alerta')->default(90);

            // Comodato menor que isto não é vigiado: 1 ou 2 vasilhames num
            // cliente doméstico gera ruído sem risco patrimonial relevante.
            $t->decimal('posse_minima_vigiada', 14, 3)->default(4);

            // Antecedência do aviso de vencimento.
            $t->unsignedSmallInteger('dias_aviso_vencimento')->default(30);

            $t->boolean('ativo')->default(true);
            $t->timestamps();

            $t->unique('empresa_id');
        });

        $this->rls();
    }

    public function down(): void
    {
        Schema::dropIfExists('comodato_config');
        Schema::dropIfExists('alertas');
        Schema::dropIfExists('comodato_avaliacoes');
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

        foreach (['comodato_avaliacoes', 'alertas', 'comodato_config'] as $tabela) {
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
