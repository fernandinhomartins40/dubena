<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro OFICIAL de logradouros (CNEFE — IBGE, Censo 2022).
 *
 * Por que esta tabela existe separada de `ruas`:
 *
 * `ruas` é o cadastro DO GRUPO — o que o operador digitou, com os erros que ele
 * digitou ("Rua Sete de Seetembro", "Rua 10 de Setembro" e "Rua Dez de
 * Setembro" como registros distintos da MESMA rua). Não dá para consertá-lo
 * sobrescrevendo: 44.338 clientes apontam para `ruas.id`.
 *
 * Esta é a REFERÊNCIA contra a qual aquilo é comparado. Fonte: CNEFE, o
 * cadastro do Censo 2022 — 106,8 milhões de endereços, 5.570 municípios, com
 * numeração e coordenada. É catálogo público federal: sem grupo_id/empresa_id,
 * fora da RLS por natureza, como `municipios_ibge` e `estados`.
 *
 * GRANULARIDADE: uma linha por (município, logradouro, bairro). O CNEFE traz um
 * registro por ENDEREÇO (90.559 só em Guarapuava) — guardar tudo seria dezenas
 * de GB para um ganho que não temos como usar. O que interessa é o conjunto de
 * logradouros e a FAIXA de numeração; o número exato do cliente continua sendo
 * digitado e validado por geocodificação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logradouros_oficiais', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('cod_ibge');
            $t->string('tipo', 30)->nullable();
            $t->string('nome');
            $t->string('bairro')->nullable();
            $t->string('cep', 8)->nullable();

            // Chave de casamento: nome normalizado (sem acento, sem caixa, sem
            // o tipo do logradouro). É por ela que "R. das Flores" encontra
            // "RUA DAS FLORES" — comparar o texto cru nunca casaria.
            $t->string('nome_busca');

            // Faixa de numeração observada no Censo. Não é a faixa LEGAL da via
            // (essa não existe em fonte pública), é o que foi recenseado — serve
            // para alertar sobre número absurdo, não para recusar cadastro.
            $t->unsignedInteger('numero_min')->nullable();
            $t->unsignedInteger('numero_max')->nullable();
            $t->unsignedInteger('enderecos')->default(0);

            // Centroide dos endereços do logradouro: dá ao geocoding um ponto de
            // partida quando o Google não resolve o endereço exato.
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();

            $t->timestamps();

            $t->index(['cod_ibge', 'nome_busca']);
            $t->index(['cod_ibge', 'bairro']);
            $t->unique(['cod_ibge', 'nome_busca', 'bairro'], 'logradouros_oficiais_chave');
        });

        // Controle do que já foi importado, por município.
        Schema::create('importacoes_cnefe', function (Blueprint $t) {
            $t->unsignedInteger('cod_ibge')->primary();
            $t->string('municipio');
            $t->char('uf', 2);
            $t->unsignedInteger('logradouros')->default(0);
            $t->unsignedInteger('bairros')->default(0);
            $t->unsignedInteger('enderecos')->default(0);
            $t->string('versao', 20)->default('censo2022');
            $t->timestamps();
        });

        $this->grants();
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes_cnefe');
        Schema::dropIfExists('logradouros_oficiais');
    }

    /**
     * GRANT para a role de runtime: a descoberta automática de RLS só varre uma
     * vez e não alcança tabela criada depois dela (ver CLAUDE.md). Sem policy —
     * são catálogos públicos, sem coluna de tenant.
     */
    private function grants(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'erp_app') AS present")->present) {
            foreach (['logradouros_oficiais', 'importacoes_cnefe'] as $tabela) {
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO erp_app");
            }
            DB::statement('GRANT USAGE, SELECT ON SEQUENCE logradouros_oficiais_id_seq TO erp_app');
        }
    }
};
