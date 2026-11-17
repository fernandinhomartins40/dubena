<?php

use App\Domain\Cliente\BuscaCliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P2-D-05 — busca de cliente sem acento, com índice (só Postgres).
 *
 * Cria `app_busca_cliente(nome, fantasia)` e um índice GIN trigram sobre ela,
 * mais o índice trigram dos dígitos do telefone. Sem os índices, `LIKE
 * '%termo%'` varre a tabela inteira e a policy RLS roda em cada linha (custo
 * medido no CLAUDE.md: 3,2 s para 400 mil pedidos).
 *
 * Expressão indexada, não coluna: o ETL grava `clientes` por `DB::table`, e
 * uma coluna mantida pelo model nasceria vazia em toda linha migrada. A
 * função é IMMUTABLE (só `lower`, `translate`, `regexp_replace`), requisito
 * para o índice de expressão — e não depende de `unaccent`, que é extensão a
 * instalar e não é IMMUTABLE.
 *
 * Não destrutiva: só cria função e índices. No sqlite é no-op (a suíte
 * registra a função no PDO, ver `BuscaCliente`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        ['de' => $de, 'para' => $para] = BuscaCliente::traducaoPostgres();
        $sep = BuscaCliente::SEPARADOR;

        DB::statement(<<<SQL
CREATE OR REPLACE FUNCTION public.app_busca_texto(texto text)
RETURNS text LANGUAGE sql IMMUTABLE PARALLEL SAFE AS \$fn\$
    SELECT btrim(regexp_replace(
        translate(lower(coalesce(texto, '')), \$tr\${$de}\$tr\$, \$tr\${$para}\$tr\$),
        '[^a-z0-9]+', ' ', 'g'))
\$fn\$
SQL);

        DB::statement(<<<SQL
CREATE OR REPLACE FUNCTION public.app_busca_cliente(nome text, fantasia text)
RETURNS text LANGUAGE sql IMMUTABLE PARALLEL SAFE AS \$fn\$
    SELECT public.app_busca_texto(nome) || '{$sep}' || public.app_busca_texto(fantasia)
\$fn\$
SQL);

        // O runtime (erp_app) precisa executar a função dentro da consulta.
        DB::statement('GRANT EXECUTE ON FUNCTION public.app_busca_texto(text) TO PUBLIC');
        DB::statement('GRANT EXECUTE ON FUNCTION public.app_busca_cliente(text, text) TO PUBLIC');

        // Sem pg_trgm a busca funciona, só não acelera. A criação da extensão
        // fica com a migration que já a cria (2026_07_03_000400); aqui só se
        // usa o que existe — um CREATE EXTENSION que falha abortaria a
        // transação inteira da migration no Postgres.
        $temTrgm = DB::selectOne("SELECT 1 AS x FROM pg_extension WHERE extname = 'pg_trgm'") !== null;
        if ($temTrgm) {
            DB::statement('CREATE INDEX IF NOT EXISTS clientes_busca_sem_acento_trgm_idx ON clientes USING gin ((public.app_busca_cliente(nome, fantasia)) gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS clientetelefones_digitos_trgm_idx ON clientetelefones USING gin (('.BuscaCliente::TELEFONE_SO_DIGITOS.') gin_trgm_ops)');
        }

        // Por que uma função SECURITY DEFINER, medido como erp_app com 60 mil
        // clientes: sob RLS o Postgres só aplica antes da policy os operadores
        // LEAKPROOF, e `LIKE` (textlike) não é. Resultado: o índice trigram
        // nunca é usado pelo runtime — nem o novo nem os de 2026_07_03 — e a
        // busca varre a tabela rodando `app_tenant_can_read` em cada linha.
        //
        // A função só devolve IDS CANDIDATOS, achados pelo índice, e já
        // filtrados por `app_tenant_can_read` explicitamente. Quem lê o
        // cliente é a consulta de fora, pela conexão do runtime e SOB a RLS:
        // ainda que um id alheio escapasse daqui, a policy o descartaria.
        // Se o dono da função não atravessar a RLS, o resultado continua
        // certo — só volta a ser a varredura.
        $digitos = BuscaCliente::TELEFONE_SO_DIGITOS;
        DB::statement(<<<SQL
CREATE OR REPLACE FUNCTION public.app_clientes_candidatos(termo text, digitos text)
RETURNS SETOF bigint LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public, pg_temp AS \$fn\$
    SELECT c.id FROM clientes c
     WHERE termo <> ''
       AND public.app_busca_cliente(c.nome, c.fantasia) LIKE '%' || termo || '%'
       AND public.app_tenant_can_read(c.tenant_account_id, c.empresa_id)
    UNION
    SELECT t.cliente_id FROM clientetelefones t
     WHERE length(digitos) >= 4
       AND {$digitos} LIKE '%' || digitos || '%'
       AND public.app_tenant_can_read(t.tenant_account_id, t.empresa_id)
    UNION
    SELECT c.id FROM clientes c
     WHERE length(digitos) >= 4
       AND (c.cpf LIKE '%' || digitos || '%' OR c.cnpj LIKE '%' || digitos || '%')
       AND public.app_tenant_can_read(c.tenant_account_id, c.empresa_id)
\$fn\$
SQL);
        DB::statement('REVOKE ALL ON FUNCTION public.app_clientes_candidatos(text, text) FROM PUBLIC');
        DB::statement("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'erp_app') THEN GRANT EXECUTE ON FUNCTION public.app_clientes_candidatos(text, text) TO erp_app; END IF; END \$\$");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP FUNCTION IF EXISTS public.app_clientes_candidatos(text, text)');
        DB::statement('DROP INDEX IF EXISTS clientetelefones_digitos_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS clientes_busca_sem_acento_trgm_idx');
        DB::statement('DROP FUNCTION IF EXISTS public.app_busca_cliente(text, text)');
        DB::statement('DROP FUNCTION IF EXISTS public.app_busca_texto(text)');
    }
};
