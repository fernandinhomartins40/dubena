#!/usr/bin/env python3
"""
Migra o HISTORICO de posicoes GPS do Monitora (MySQL) para monitora_posicoes (PG).

Fica fora do MonitoraMigrator (PHP) de proposito: sao ~16 milhoes de linhas e o
caminho Eloquent (updateOrCreate linha a linha) levaria horas. Aqui usa-se o
streaming do MySQL + COPY do Postgres, que e a via rapida para volume.

Correlacao: positions.deviceid -> veiculos.deviceid -> monitora_veiculos.id.
Posicoes de device desconhecido sao contadas e descartadas (nao ha veiculo a que
prende-las).

Uso:
  python migrar_posicoes.py            # tudo
  python migrar_posicoes.py --dias 30  # so os ultimos N dias
"""
import argparse
import io
import sys

import psycopg2
import pymysql

MYSQL = dict(host="127.0.0.1", port=53306, user="root", password="dubena",
             database="monitora", charset="utf8mb4")
PG_DSN = "host=127.0.0.1 port=55432 dbname=erp_novo user=postgres password=dubena"

LOTE = 100000


def mapa_veiculos(pg, my):
    """deviceid do rastreador -> (id do veiculo, empresa_id) no ERP novo.

    O MonitoraLegadoMigrator grava o `deviceid` do legado em
    monitora_veiculos.imei, entao o vinculo e direto.
    """
    pcur = pg.cursor()
    pcur.execute(
        "SELECT id, imei, empresa_id FROM monitora_veiculos WHERE imei IS NOT NULL")
    return {str(imei).strip(): (vid, emp) for vid, imei, emp in pcur.fetchall()}


def criar_veiculos_orfaos(pg, my, mapa):
    """Cria veiculo-marcador para device que emitiu posicao e nao tem cadastro.

    Ha rastreadores em `positions` que nunca entraram em `veiculos` (1,1M de
    posicoes num deles). Sem um veiculo a que prender, essas posicoes seriam
    perdidas -- entao cria-se um cadastro identificado pelo proprio device,
    inativo, para o usuario nomear depois.
    """
    cur = my.cursor()
    cur.execute("SELECT DISTINCT deviceid FROM positions WHERE deviceid IS NOT NULL")
    devices = {str(d[0]).strip() for d in cur.fetchall() if str(d[0]).strip()}
    faltantes = sorted(devices - set(mapa))
    if not faltantes:
        return 0

    pcur = pg.cursor()
    # Herdam a empresa que ja concentra a frota migrada.
    pcur.execute("""SELECT empresa_id, grupo_id FROM monitora_veiculos
                    GROUP BY empresa_id, grupo_id ORDER BY COUNT(*) DESC LIMIT 1""")
    linha = pcur.fetchone()
    if linha is None:
        return 0
    empresa, grupo = linha

    pcur.execute("SELECT COALESCE(MAX(id), 0) FROM monitora_veiculos")
    proximo = pcur.fetchone()[0]

    novos = []
    for dev in faltantes:
        proximo += 1
        novos.append((proximo, empresa, grupo, f"SEM-CAD-{dev}"[:10], dev,
                      f"Rastreador {dev} sem cadastro de veiculo no legado"))
        mapa[dev] = (proximo, empresa)

    pcur.executemany(
        """INSERT INTO monitora_veiculos
           (id, empresa_id, grupo_id, placa, imei, descricao, ativo,
            created_at, updated_at)
           VALUES (%s, %s, %s, %s, %s, %s, false, NOW(), NOW())""",
        novos,
    )
    pcur.execute("""SELECT setval(pg_get_serial_sequence('monitora_veiculos','id'),
                    COALESCE((SELECT MAX(id) FROM monitora_veiculos), 1))""")
    pg.commit()
    return len(novos)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dias", type=int, default=None,
                    help="migrar apenas os ultimos N dias (padrao: tudo)")
    args = ap.parse_args()

    my = pymysql.connect(**MYSQL)
    pg = psycopg2.connect(PG_DSN)
    pcur = pg.cursor()

    mapa = mapa_veiculos(pg, my)
    if not mapa:
        print("Nenhum veiculo casado entre legado e ERP novo. "
              "Rode o MonitoraMigrator antes (etl:run monitora).")
        return 1
    criados = criar_veiculos_orfaos(pg, my, mapa)
    if criados:
        print(f"{criados} veiculo(s)-marcador criado(s) para rastreador sem "
              f"cadastro no legado.")
    print(f"{len(mapa)} device(s) casado(s) com veiculo do ERP novo.")

    onde = ""
    if args.dias:
        onde = f"WHERE dhposition >= DATE_SUB(NOW(), INTERVAL {args.dias} DAY)"

    # SSCursor: streaming, para nao puxar 16M de linhas para a memoria
    cur = my.cursor(pymysql.cursors.SSCursor)
    cur.execute(
        "SELECT deviceid, latitude, longitude, speed, course, dhposition, created_at "
        f"FROM positions {onde}"
    )

    gravadas = 0
    sem_veiculo = 0
    sem_coord = 0
    buf = io.StringIO()
    n_buf = 0

    while True:
        linhas = cur.fetchmany(LOTE)
        if not linhas:
            break

        for deviceid, lat, lng, speed, course, dh, criado in linhas:
            achado = mapa.get(str(deviceid).strip())
            if achado is None:
                sem_veiculo += 1
                continue
            vid, empresa = achado
            if lat is None or lng is None:
                sem_coord += 1
                continue

            registrado = dh or criado
            if registrado is None:
                sem_coord += 1
                continue

            # direcao e smallint 0-359 no schema novo
            direcao = ""
            if course is not None:
                direcao = str(int(float(course)) % 360)

            quando = registrado.strftime("%Y-%m-%d %H:%M:%S")
            buf.write("\t".join([
                str(vid),
                str(empresa),              # NOT NULL: isolamento multi-tenant
                f"{float(lat):.7f}",
                f"{float(lng):.7f}",
                f"{float(speed or 0):.2f}",
                direcao if direcao else r"\N",
                "f",                       # ignicao: nao existe no legado
                quando, quando, quando,
            ]))
            buf.write("\n")
            n_buf += 1

        if n_buf:
            buf.seek(0)
            pcur.copy_expert(
                "COPY monitora_posicoes (veiculo_id, empresa_id, latitude, "
                "longitude, velocidade, direcao, ignicao, registrado_em, "
                "created_at, updated_at) FROM STDIN WITH (FORMAT text)", buf)
            pg.commit()
            gravadas += n_buf
            print(f"   ... {gravadas} gravadas", end="\r", flush=True)
            buf = io.StringIO()
            n_buf = 0

    print(f"\nPosicoes gravadas: {gravadas}")
    if sem_veiculo:
        print(f"Descartadas (device sem veiculo no ERP): {sem_veiculo}")
    if sem_coord:
        print(f"Descartadas (sem coordenada/data): {sem_coord}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
