<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * F1 — tipo de vínculo do colaborador.
 *
 * O cliente tem TRÊS perfis de campo, e só um é CLT:
 *  - funcionario: entregador com vínculo empregatício;
 *  - franqueado:  entrega e vende sem vínculo, remunerado por comissão/repasse — PJ;
 *  - industrial:  vendedor da rede para empresa/indústria, emite nota e negocia preço.
 *
 * **Por que uma coluna e não uma tabela nova.** O franqueado já é colaborador em
 * tudo que importa ao sistema: tem setor, veículo, comissão (colaborador_comissoes
 * suporta repasse via tipo_comissao=2) e presta contas pelo malote. Criar uma
 * entidade paralela duplicaria todos esses vínculos para ganhar dois campos.
 *
 * **O que isso exige de cuidado.** Relatório de RH que conta "funcionários" passa
 * a precisar filtrar por vínculo — franqueado não é CLT e não entra em folha. O
 * default 'funcionario' preserva o comportamento de todo mundo que já existe.
 *
 * `entregador` (booleano que já existia) continua respondendo "faz entrega?";
 * o vínculo responde "sob qual relação". São perguntas diferentes: um industrial
 * não é entregador, e um franqueado é.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colaboradores', function (Blueprint $t) {
            $t->string('vinculo', 20)->default('funcionario')->after('user_id');
            // PJ do franqueado — nulo para quem é CLT.
            $t->string('cnpj', 14)->nullable()->after('cpf');
        });

        // Índice para o filtro que os relatórios vão passar a fazer.
        Schema::table('colaboradores', function (Blueprint $t) {
            $t->index(['empresa_id', 'vinculo']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Fecha o conjunto no banco: vínculo inválido não entra nem por SQL solto.
            DB::statement(
                "ALTER TABLE colaboradores ADD CONSTRAINT colaboradores_vinculo_check
                 CHECK (vinculo IN ('funcionario','franqueado','industrial'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE colaboradores DROP CONSTRAINT IF EXISTS colaboradores_vinculo_check');
        }

        Schema::table('colaboradores', function (Blueprint $t) {
            $t->dropIndex(['empresa_id', 'vinculo']);
            $t->dropColumn(['vinculo', 'cnpj']);
        });
    }
};
