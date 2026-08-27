<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo NACIONAL de municípios do IBGE + vínculo da cidade do grupo com ele.
 *
 * MOTIVO (medido na base de produção): as 105 cidades cadastradas à mão têm
 * código IBGE inventado, zerado ou de outra cidade —
 *   - Guaratuba .......... 999999999 (inventado)
 *   - RIO DAS PEDRAS ..... 0
 *   - DESCONHECIDO ....... 1212
 *   - Maravilha .......... 89874000 (é um CEP)
 *   - CAMPO LARGO ........ 4205506 (é o código de Fraiburgo)
 *   - Coronel Vivida / CORENEL VIVIDA — a mesma cidade duas vezes
 *
 * Isso não é cosmético: `cod_ibge` vira `cMun`/`cMunFG` no XML da NF-e
 * (XmlNfeBuilder). Código de município errado é REJEIÇÃO da SEFAZ, e o cutover
 * fiscal ainda não aconteceu — está armado para falhar em produção.
 *
 * `municipios_ibge` é catálogo público e imutável da União: sem grupo_id/
 * empresa_id e, portanto, fora da RLS por natureza (como `estados`). Quem tem
 * dono continua sendo `cidades`, que ganha só o PONTEIRO para o catálogo.
 *
 * O vínculo é NULLABLE de propósito: existe cidade legada que não casa com
 * nenhum município (a própria "DESCONHECIDO"), e travar o campo impediria a
 * migration de rodar sobre a base real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios_ibge', function (Blueprint $t) {
            // O código IBGE é a chave natural — 7 dígitos, estável, nacional.
            // Usá-lo como PK evita um id sintético que não significa nada e
            // torna o upsert do comando de sincronismo trivial.
            $t->unsignedInteger('cod_ibge')->primary();
            $t->string('nome');
            $t->char('uf', 2);
            // Guardado sem acento e em minúsculas para a busca não depender de
            // o usuário digitar "Guarapuava" com a acentuação exata.
            $t->string('nome_busca');
            $t->unsignedInteger('cod_uf');
            $t->timestamps();

            $t->index(['uf', 'nome']);
            $t->index('nome_busca');
        });

        Schema::table('cidades', function (Blueprint $t) {
            $t->unsignedInteger('municipio_ibge')->nullable()->after('cod_ibge');
            $t->foreign('municipio_ibge')->references('cod_ibge')->on('municipios_ibge')->nullOnDelete();
            $t->index('municipio_ibge');
        });

        $this->grants();
    }

    public function down(): void
    {
        Schema::table('cidades', function (Blueprint $t) {
            $t->dropForeign(['municipio_ibge']);
            $t->dropIndex(['municipio_ibge']);
            $t->dropColumn('municipio_ibge');
        });

        Schema::dropIfExists('municipios_ibge');
    }

    /**
     * GRANT explícito para a role de runtime: a descoberta automática de RLS só
     * varre uma vez e não alcança tabela criada depois dela (ver CLAUDE.md).
     * Aqui não há policy — a tabela é catálogo público, sem coluna de tenant.
     */
    private function grants(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'erp_app') AS present")->present) {
            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON municipios_ibge TO erp_app');
        }
    }
};
