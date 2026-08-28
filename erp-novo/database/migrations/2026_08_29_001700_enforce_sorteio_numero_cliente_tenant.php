<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1-08 (item 4 do gate): a ultima FK que atravessava tenant sem guarda.
 *
 * Dos 175 relacionamentos entre tabelas com chave SaaS, 168 tem `empresa_id` no
 * proprio filho — a policy canonica ja valida a linha na escrita, entao um pai
 * de outro tenant nao ajuda ninguem. Dos 7 filhos sem `empresa_id`, 6 sao os
 * grafos ja cobertos pelo protetor documental.
 *
 * Sobrava `sorteio_numeros.cliente_id`. A chave de tenant do numero vem do
 * `sorteios` pai (correto), mas o cliente nunca era conferido contra ela: um
 * numero de sorteio do tenant A podia ficar amarrado a um cliente do tenant B.
 * Provado em PostgreSQL antes desta migration.
 *
 * `cliente_id` e nullable de proposito (numero emitido sem dono ainda), e nesse
 * caso nao ha o que validar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.app_enforce_sorteio_numero_cliente_tenant()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    cliente_tenant_account_id bigint;
BEGIN
    IF NEW.cliente_id IS NULL THEN
        RETURN NEW; -- numero ainda sem dono: nada a conferir
    END IF;

    SELECT tenant_account_id INTO cliente_tenant_account_id
      FROM public.clientes WHERE id = NEW.cliente_id;

    -- Cliente ainda sem chave SaaS e a unica compatibilidade permitida antes da
    -- conversao; depois dela a divergencia e recusada.
    IF cliente_tenant_account_id IS NOT NULL
       AND (NEW.tenant_account_id IS NULL OR NEW.tenant_account_id <> cliente_tenant_account_id) THEN
        RAISE EXCEPTION 'F1 recusada: sorteio_numeros.cliente_id precisa referenciar cliente do mesmo tenant'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS sorteio_numeros_cliente_tenant ON public.sorteio_numeros;
CREATE TRIGGER sorteio_numeros_cliente_tenant
BEFORE INSERT OR UPDATE OF tenant_account_id, cliente_id ON public.sorteio_numeros
FOR EACH ROW EXECUTE FUNCTION public.app_enforce_sorteio_numero_cliente_tenant();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS sorteio_numeros_cliente_tenant ON public.sorteio_numeros;
DROP FUNCTION IF EXISTS public.app_enforce_sorteio_numero_cliente_tenant();
SQL);
    }
};
