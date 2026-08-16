#!/usr/bin/env python3
"""
Diagnostico SOMENTE-LEITURA da producao (T2.8 do PLANO_PRODUCAO).

Responde a unica pergunta que pode invalidar toda a F2: **a producao tem a
mesma duplicacao 4x de clientes que o ambiente local tinha?** A auditoria foi
instruida a nao acessar a VPS, entao isso e conjectura ate ser medido.

REGRA ABSOLUTA: este script so executa SELECT. Nenhum INSERT/UPDATE/DELETE/DDL,
nenhum `php artisan migrate`, nenhum seeder. A T2.8 e levantamento, nao correcao
— corrigir a producao exige backup antes (T3.4), que ainda nao existe.

Uso (na VPS, dentro do container do app ou com acesso ao Postgres):

    python3 diagnostico_producao.py                 # usa DATABASE_URL / PG* do ambiente
    python3 diagnostico_producao.py --dsn "host=... dbname=... user=... password=..."

A saida e um relatorio de texto para colar no registro da T2.8.
"""

import argparse
import os
import sys
from datetime import datetime

try:
    import psycopg2
except ImportError:  # pragma: no cover
    sys.exit("psycopg2 nao instalado. Rode: pip install psycopg2-binary")


# Cada consulta e (rotulo, sql, como_interpretar). Todas SELECT.
CONSULTAS = [
    (
        "clientes_total",
        "SELECT count(*) FROM public.clientes",
        "Local tinha 88.765 ANTES da dedup e 55.453 depois.",
    ),
    (
        "clientes_do_app",
        "SELECT count(*) FROM public.clientes "
        "WHERE observacoes LIKE 'Cadastro originado do app%'",
        "Local: 44.416 antes da dedup (11.104 x 4).",
    ),
    (
        "origens_distintas",
        "SELECT count(DISTINCT substring(observacoes from 'id de origem: ([0-9]+)')) "
        "FROM public.clientes WHERE observacoes LIKE 'Cadastro originado do app%'",
        "Local: 11.104. Se clientes_do_app / origens_distintas > 1, HA duplicacao.",
    ),
    (
        "histograma_duplicacao",
        """
        SELECT vezes, count(*) AS grupos FROM (
          SELECT substring(observacoes from 'id de origem: ([0-9]+)') AS origem,
                 count(*) AS vezes
            FROM public.clientes
           WHERE observacoes LIKE 'Cadastro originado do app%'
           GROUP BY 1
        ) t GROUP BY vezes ORDER BY vezes
        """,
        "A pergunta central da T2.8. 'vezes=1' para todos = producao LIMPA. "
        "'vezes=4' = mesma corrupcao do local (o ETL rodou 4x).",
    ),
    (
        "tem_coluna_api_id",
        "SELECT count(*) FROM information_schema.columns "
        "WHERE table_schema='public' AND table_name='clientes' AND column_name='api_id'",
        "1 = as migrations da T2.1 ja foram aplicadas la; 0 = ainda nao.",
    ),
    (
        "pedidos_apontando_para_faixa_do_app",
        "SELECT count(*) FROM public.pedidos WHERE cliente_id > 101122",
        "Local: 430. Se > 0 e houver duplicacao, deduplicar EXIGE remapear FK "
        "(nunca DELETE direto).",
    ),
    (
        "orfaos_pedidos",
        "SELECT count(*) FROM public.pedidos p "
        "LEFT JOIN public.clientes c ON c.id = p.cliente_id "
        "WHERE p.cliente_id IS NOT NULL AND c.id IS NULL",
        "Tem de ser 0. Qualquer valor > 0 e corrupcao referencial ja instalada.",
    ),
    (
        "orfaos_telefones",
        "SELECT count(*) FROM public.clientetelefones t "
        "LEFT JOIN public.clientes c ON c.id = t.cliente_id WHERE c.id IS NULL",
        "Tem de ser 0.",
    ),
    (
        "telefones_duplicados",
        "SELECT coalesce(sum(n - 1), 0) FROM ("
        "  SELECT count(*) AS n FROM public.clientetelefones "
        "   GROUP BY cliente_id, telefone HAVING count(*) > 1) x",
        "Local: 30.492 linhas redundantes antes da fusao.",
    ),
    (
        "soma_financeiros",
        "SELECT round(sum(valor)::numeric, 2) FROM public.financeiros",
        "Local: 250029904.80 (bate ao centavo com o legado). Divergencia aqui e grave.",
    ),
    (
        "tabelas_no_espelho",
        "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'legado'",
        "Local: 121. A auditoria mediu 43 na VPS — se ainda for 43, o espelho de "
        "producao esta MUITO atras e o cutover:check de la passa por omissao.",
    ),
    (
        "indices_faltando_em_fks_de_clientes",
        """
        SELECT count(*) FROM pg_constraint c
          JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
         WHERE c.contype = 'f' AND c.confrelid = 'public.clientes'::regclass
           AND NOT EXISTS (SELECT 1 FROM pg_index i
                            WHERE i.indrelid = c.conrelid AND i.indkey[0] = c.conkey[1])
        """,
        "Local tinha 23 de 24 sem indice. Afeta toda operacao de cliente, nao so o ETL.",
    ),
]


def dsn_do_ambiente() -> str:
    if os.environ.get("DATABASE_URL"):
        return os.environ["DATABASE_URL"]

    partes = {
        "host": os.environ.get("DB_HOST", "127.0.0.1"),
        "port": os.environ.get("DB_PORT", "5432"),
        "dbname": os.environ.get("DB_DATABASE", "erp_novo"),
        "user": os.environ.get("DB_USERNAME", "postgres"),
        "password": os.environ.get("DB_PASSWORD", ""),
    }
    return " ".join(f"{k}={v}" for k, v in partes.items() if v)


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--dsn", help="DSN do Postgres (default: variaveis de ambiente)")
    args = ap.parse_args()

    dsn = args.dsn or dsn_do_ambiente()

    print("=" * 78)
    print("DIAGNOSTICO DA PRODUCAO — T2.8 (SOMENTE LEITURA)")
    print(f"Executado em: {datetime.now().isoformat(timespec='seconds')}")
    print("=" * 78)

    conn = psycopg2.connect(dsn)
    # Sessao read-only no proprio servidor: mesmo um erro de codigo aqui nao
    # consegue escrever. Cinto e suspensorio.
    conn.set_session(readonly=True, autocommit=True)

    with conn.cursor() as cur:
        for rotulo, sql, nota in CONSULTAS:
            print(f"\n--- {rotulo} ---")
            try:
                cur.execute(sql)
                linhas = cur.fetchall()
                if len(linhas) == 1 and len(linhas[0]) == 1:
                    print(f"  {linhas[0][0]}")
                else:
                    for linha in linhas:
                        print("  " + " | ".join(str(v) for v in linha))
            except Exception as e:  # noqa: BLE001 — relatorio nao pode abortar no meio
                print(f"  ERRO: {e}")
            print(f"  ({nota})")

    conn.close()

    print("\n" + "=" * 78)
    print("VEREDITO — preencher a mao ao registrar a T2.8:")
    print("  [ ] producao LIMPA  (histograma so com vezes=1)")
    print("  [ ] producao DUPLICADA (vezes>1) → dedup la SO DEPOIS do backup (T3.4)")
    print("=" * 78)

    return 0


if __name__ == "__main__":
    sys.exit(main())
