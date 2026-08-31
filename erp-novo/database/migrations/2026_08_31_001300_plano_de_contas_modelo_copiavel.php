<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F5-01 — plano de contas **modelo copiável**.
 *
 * ## O que faltava
 *
 * `planos_conta` é por grupo, com `tenant_account_id` e trigger de hierarquia
 * (F1-08) — o ownership está resolvido. O que não existe é a **origem**: uma
 * revenda nova entra no SaaS com a árvore vazia.
 *
 * Sem plano de contas, o DRE agrupa tudo em "Sem plano" e a conciliação
 * contábil não tem para onde apontar. O sistema funciona e não serve.
 *
 * Hoje o único plano de contas do repositório vive dentro do
 * `DemoGuarapuavaSeeder` — massa de demonstração, que não roda para revenda
 * real. Ou seja: quem entrasse amanhã montaria a árvore à mão, do zero, sem
 * saber o que é obrigatório.
 *
 * ## Por que uma TABELA e não uma constante no código
 *
 * O plano-modelo é valor de negócio, não regra de engenharia: quais receitas e
 * despesas uma revenda de GLP acompanha é decisão de quem opera, muda com o
 * tempo, e difere entre um cliente que também vende água e outro que só
 * distribui.
 *
 * Constante em PHP obrigaria um deploy para cada ajuste, e — pior — colocaria a
 * plataforma no papel de decidir a contabilidade da revenda. Como tabela, o
 * catálogo é editável pelo painel e versionável por vigência.
 *
 * ## Por que é de PLATAFORMA e não do grupo
 *
 * O modelo é o mesmo para todas as revendas na largada; o que cada uma faz
 * depois com a **sua** árvore é dela e vive em `planos_conta`. Duplicar o modelo
 * por grupo criaria N cópias para manter, e a correção de um erro no modelo não
 * alcançaria ninguém.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plano_conta_modelos', function (Blueprint $t) {
            $t->id();

            // Nó pai DENTRO do modelo. A árvore copiada preserva o formato.
            $t->foreignId('pai_id')->nullable()
                ->constrained('plano_conta_modelos')->cascadeOnDelete();

            $t->string('codigo', 30)->nullable();
            $t->string('descricao');
            $t->char('pagarreceber', 1)->default('R'); // P=pagar, R=receber
            $t->unsignedSmallInteger('nivel')->default(1);

            // Qual modelo: uma revenda que só distribui GLP não tem as mesmas
            // contas de quem também vende água ou conveniência. `padrao` é o que
            // se copia quando ninguém escolhe.
            $t->string('perfil', 40)->default('padrao');

            // Desligar uma linha do modelo sem apagá-la: quem já copiou fica
            // como está, e quem copiar depois não recebe mais.
            $t->boolean('ativo')->default(true);

            $t->unsignedSmallInteger('ordem')->default(0);
            $t->timestamps();

            $t->index(['perfil', 'ativo']);
        });

        $this->aplicarGrants();
        $this->semear();
    }

    public function down(): void
    {
        Schema::dropIfExists('plano_conta_modelos');
    }

    /**
     * Tabela de PLATAFORMA: leitura para todos, escrita só pelo owner.
     *
     * Sem RLS por tenant, de propósito — o catálogo é o mesmo para todas as
     * revendas, e é o que a classificação chama de PLATFORM. Mas `erp_app` não
     * pode escrever: uma revenda não edita o modelo que serve às outras.
     */
    private function aplicarGrants(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('GRANT SELECT ON plano_conta_modelos TO erp_app');
    }

    /**
     * O modelo inicial de uma revenda de GLP.
     *
     * Ponto de partida deliberadamente enxuto: as contas que qualquer revenda
     * precisa para o DRE fazer sentido no primeiro mês. Uma árvore de sessenta
     * linhas seria mais impressionante e menos usada — ninguém classifica um
     * lançamento escolhendo entre sessenta opções, e o resto vira "Sem plano"
     * do mesmo jeito.
     *
     * Os valores ficam aqui porque uma tabela vazia não serve de modelo; a
     * partir da primeira edição pelo painel, a fonte da verdade é a tabela.
     */
    private function semear(): void
    {
        $agora = now();
        $inserir = function (array $linha) use ($agora): int {
            return (int) DB::table('plano_conta_modelos')->insertGetId($linha + [
                'perfil' => 'padrao', 'ativo' => true,
                'created_at' => $agora, 'updated_at' => $agora,
            ]);
        };

        $receitas = $inserir(['codigo' => '1', 'descricao' => 'Receitas', 'pagarreceber' => 'R', 'nivel' => 1, 'ordem' => 10]);

        foreach ([
            ['1.01', 'Venda de GLP', 20],
            ['1.02', 'Venda de água', 30],
            ['1.03', 'Venda de acessórios', 40],
            ['1.04', 'Outras receitas', 50],
        ] as [$codigo, $descricao, $ordem]) {
            $inserir([
                'pai_id' => $receitas, 'codigo' => $codigo, 'descricao' => $descricao,
                'pagarreceber' => 'R', 'nivel' => 2, 'ordem' => $ordem,
            ]);
        }

        $despesas = $inserir(['codigo' => '2', 'descricao' => 'Despesas', 'pagarreceber' => 'P', 'nivel' => 1, 'ordem' => 100]);

        foreach ([
            ['2.01', 'Compra de mercadorias', 110],
            ['2.02', 'Combustível e manutenção de frota', 120],
            ['2.03', 'Folha e encargos', 130],
            ['2.04', 'Impostos e taxas', 140],
            ['2.05', 'Aluguel e utilidades', 150],
            ['2.06', 'Despesas administrativas', 160],
            ['2.07', 'Despesas financeiras', 170],
        ] as [$codigo, $descricao, $ordem]) {
            $inserir([
                'pai_id' => $despesas, 'codigo' => $codigo, 'descricao' => $descricao,
                'pagarreceber' => 'P', 'nivel' => 2, 'ordem' => $ordem,
            ]);
        }
    }
};
