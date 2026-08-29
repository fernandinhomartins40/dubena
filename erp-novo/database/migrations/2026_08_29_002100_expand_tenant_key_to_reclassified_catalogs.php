<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reclassificacao de 2026-08-29: 19 cadastros sairam de PLATFORM para COMPANY
 * group-scoped, porque sao editaveis pela revenda e ja vinham duplicados por
 * grupo na copia real. A expansao de chave de F1-03 nao os alcancou — ela rodou
 * quando eles ainda estavam classificados como plataforma.
 *
 * Aditiva e nullable, como a F1-03: nenhuma linha recebe dono aqui. O backfill
 * e a policy canonica continuam sendo do comando documental
 * `saas:tenant:proteger-configuracao-grupo`, que exige ponte aprovada por grupo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tabelas = [
        'agencias',
        'bairros',
        'bancos',
        'cidades',
        'estados_civis',
        'feriados',
        'operacoes_fiscais',
        'parentescos',
        'produtoclasses',
        'profissoes',
        'regioes',
        'ruas',
        'segmentos',
        'telefonetipos',
        'tipo_combustiveis',
        'tipopessoas',
        'tipos_documento_veiculo',
        'tipos_exame',
        'unidadesmedida',
    ];

    public function up(): void
    {
        foreach ($this->tabelas as $tabela) {
            if (! Schema::hasTable($tabela) || Schema::hasColumn($tabela, 'tenant_account_id')) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $t) {
                $t->foreignId('tenant_account_id')->nullable()
                    ->constrained('tenant_accounts')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabelas as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'tenant_account_id')) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $t) {
                $t->dropConstrainedForeignId('tenant_account_id');
            });
        }
    }
};
