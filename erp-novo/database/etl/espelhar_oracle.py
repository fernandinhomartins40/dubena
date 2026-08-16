#!/usr/bin/env python3
"""
Espelha o schema legado ORACLE (CTRL2QTI) para o Postgres, no schema `legado`.

Por que existe: os Migrators de app/Etl leem da conexao `legado` (Postgres) usando
os NOMES DE TABELA DO LEGADO (clientes, pedidos, pedidooperacaos, ...). O dump real
do ERP antigo, porem, e um Oracle 11g -- que o python-oracledb thin nao suporta e
para o qual nao ha Instant Client no host. Este script materializa o Oracle em
Postgres com esses mesmos nomes, para que o ETL existente rode sem reescrita.

Estrategia: le por `sqlplus` (stdout, nao SPOOL -- SPOOL trunca em 2499 chars),
concatenando as colunas com um separador improvavel; grava com COPY do psycopg2.
Paginacao por ROWNUM em blocos, para nao materializar tabelas grandes na memoria.

Uso:
  python espelhar_oracle.py                 # todas as tabelas do MAPA
  python espelhar_oracle.py clientes pedidos
"""
import io
import os
import subprocess
import sys

import psycopg2

ORA = os.environ.get("ORA_CONTAINER", "dubena-ora")
ORA_CONN = "CTRL2QTI/dubena@localhost/XE"
PG_DSN = "host=127.0.0.1 port=55432 dbname=erp_novo user=postgres password=dubena"

SEP = "~|~"
MARCA = "#### DADOS ####"
BLOCO = 50000

# tabela_oracle -> tabela_postgres (nome que os Migrators esperam)
MAPA = {
    "EMPRESAS_GRUPOS": "empresasgrupos",
    "EMPRESAS": "empresas",
    "CIDADES": "cidades",
    "BAIRROS": "bairros",
    "RUAS": "ruas",
    "SETORS": "setors",
    "USERS": "users",
    "CLIENTES": "clientes",
    "CLIENTETELEFONES": "clientetelefones",
    "PRODUTOS": "produtos",
    "CONDICAOPAGAMENTOS": "condicaopagamentos",
    "PEDIDOOPERACAOS": "pedidooperacaos",
    "PEDIDOSITUACAOS": "pedidosituacaos",
    "PEDIDOS": "pedidos",
    "PEDIDOITEMS": "pedidoprodutos",
    # Estoque
    "ESTOQUESETORS": "estoquesetors",
    "ESTOQUESETORHISTORICOS": "estoquesetorhistoricos",
    "ESTOQUETRANSFERENCIAS": "estoquetransferencias",
    "ESTOQUETRANSFERENCIAITEMS": "estoquetransferenciaitems",
    # Financeiro
    "FINANCEIROS": "financeiros",
    "FINANCEIROPARCELAS": "financeiroparcelas",
    # Fiscal
    "NFEMITIDAS": "nfemitidas",
    "NFEMITIDAITEMS": "nfemitidaitems",
    "NFRECEBIDAS": "nfrecebidas",
    "NFRECEBIDAITEMS": "nfrecebidaitems",
    "NFOPERACAOS": "nfoperacaos",
    "NFSITUACAOS": "nfsituacaos",
    # Caixa / conta (nao ha tabela `caixas` no legado: o caixa e a CONTA)
    "CONTAS": "contas",
    "CONTAMOVIMENTOS": "contamovimentos",
    # Cobranca
    "BOLETOS": "boletos",
    # Satelites (convenio e um FECHAMENTO por cliente, nao um cadastro proprio)
    "CONVENIOFECHAMENTOS": "conveniofechamentos",
    "VALEGAS": "valegas",
    "COMODATOS": "comodatos",
    # Config da empresa (credenciais PIX/Maps)
    "EMPRESACONFIGS": "empresaconfigs",
    # Complementos (tabelas recriadas na refatoracao)
    "NFEMITIDAVOLUMES": "nfemitidavolumes",
    "BOLETOHISTORICOS": "boletohistoricos",
    "CONTATRANSFERENCIAS": "contatransferencias",
    "ESTOQUESETORACERTOS": "estoquesetoracertos",
    "VALEGASVENDAS": "valegasvendas",
    "CONDICAOPAGAMENTOPARCELAS": "condicaopagamentoparcelas",
    "PROMOTORVENDAS": "promotorvendas",
    "CONTAMOVIMENTOESTORNOS": "contamovimentoestornos",
    "VENDAATIVAS": "vendaativas",
    "VENDAATIVACLIENTES": "vendaativaclientes",
    # Cadastros contabeis/fiscais (dao significado ao financeiro e ao fiscal)
    "PLANOCONTAS": "planocontas",
    "CENTROCUSTOS": "centrocustos",
    "FINANCEIRORATEIOS": "financeirorateios",
    # ── Ampliacao T2.7 (docs/gauntlet/DECISAO_TABELAS_SEM_MIGRACAO.md) ──
    # As 5 tabelas com dado de risco fiscal/financeiro que estavam fora do
    # espelho. As demais da lista das 15 foram decididas como ARQUIVAR ou
    # APOSENTAR — a ausencia delas aqui e deliberada e esta documentada.
    "CREDITOPISCOFINS": "creditopiscofins",          # catalogo EFD-Contribuicoes (bloco M)
    "NFRECEBIDAPARCELAS": "nfrecebidaparcelas",      # contas a pagar da NF de entrada
    "CONTAEXTRATOCONFIGS": "contaextratoconfigs",    # regras de classificacao do extrato
    "CLIENTECONVENIOS": "clienteconvenios",          # contrato do convenio (comissao/limite)
    # CLIENTEPRODUTOSCONVENIOS fica FORA de proposito: `clienteprecos` ja cobre
    # preco por (cliente, produto) e ja esta populada — portar criaria uma
    # segunda fonte de verdade para a mesma regra.
    # ── Ampliacao pos-auditoria (AUDITORIA_MIGRACAO_DADOS_LEGADOS.md) ──
    "ESTADOS": "estados",
    # Usuarios do ERP (pedidos referenciam atendente/entregador por user_id)
    "EMPRESA_USER": "empresa_user",
    # RH (nomes de destino = o que os Migrators leem)
    "CARGOS": "cargos",
    "PARENTESCOS": "parentescos",
    "TIPOEXAMES": "tipoexames",
    "COLABORADORS": "colaboradores",
    "COLABORADORFAMILIAS": "colaboradorfamilias",
    "COLABORADOREXAMES": "colaboradorexames",
    "COLABORADORFERIAS": "colaboradorferias",
    "COLABORADORTELEFONES": "colaboradortelefones",
    "COLABORADORCOMISSAOS": "colaboradorcomissoes",
    "COMISSAOEXCECOES": "comissaoexcecoes",
    "SETORCOLABORADORES": "setorcolaboradores",
    # Frota
    "VEICULOTIPOS": "veiculotipos",
    "TIPOCOMBUSTIVELS": "tipocombustivels",
    "VEICULOS": "veiculos",
    "VEICULOABASTECIMENTOS": "veiculoabastecimentos",
    "VEICULOPNEUS": "veiculopneus",
    "VEICULOTROCAOLEOS": "veiculotrocaoleos",
    # Cadastros de apoio (CadastrosApoioMigrator)
    "SEGMENTOS": "segmentos",
    "TIPOPESSOAS": "tipopessoas",
    "TELEFONETIPOS": "telefonetipos",
    "CLIENTECONTATOTIPOS": "clientecontatotipos",
    "CLIENTECONTATOSITUACAOS": "clientecontatosituacaos",
    "BANCOS": "bancos",
    "CONTAMOVIMENTOTIPOS": "contamovimentotipos",
    # Historicos e fechamentos
    "PEDIDOSITUACAOHISTORICOS": "pedidosituacaohistoricos",
    "CONVENIOFECHAMENTOPEDIDOS": "conveniofechamentopedidos",
    "COMODATOITEMS": "comodatoitems",
    "CONTAFECHAMENTOS": "contafechamentos",
    "ESTOQUEFECHAMENTOS": "estoquefechamentos",
    "ESTOQUEFECHAMENTOSETORS": "estoquefechamentosetors",
    # Cheques
    "CHEQUESITUACAOS": "chequesituacaos",
    "CHEQUERECEBIDOS": "chequerecebidos",
    "CHEQUESITUACAOHISTORICOS": "chequesituacaohistoricos",
    "CHEQUERECEBIDOFINANCEIROS": "chequerecebidofinanceiros",
    # Remessa CNAB
    "BOLETOREMESSAS": "boletoremessas",
    "BOLETOREMESSAFINANCEIROS": "boletoremessafinanceiros",
    "OCORRENCIASREMESSAS": "ocorrenciasremessas",
    # Mobile / PIX legados
    "ANDROIDS": "androids",
    "PIXTRANSACTIONS": "pixtransactions",
    "PIXPEDIDOS": "pixpedidos",
    # Preco por cliente
    "CLIENTEPRODUTOS": "clienteprodutos",
    # CRM (pos-venda completo + metas + sorteios + checklists)
    "POSVENDAS": "posvendas",
    "POSVENDAPERGUNTAS": "posvendaperguntas",
    "POSVENDARESPOSTAS": "posvendarespostas",
    "POSVENDAPESQUISAS": "posvendapesquisas",
    "POSVENDAPESQUISARESPOSTAS": "posvendapesquisarespostas",
    "METAVENDAS": "metavendas",
    "SORTEIOS": "sorteios",
    "PROMOCAOS": "promocoes",
    "CHECKLISTS": "checklists",
    "CHECKLISTPESQUISAS": "checklistexecucoes",
    # Gestao (zero linhas hoje, mas silencia avisos e cobre outras instalacoes)
    "CUPONSFISCAIS": "cuponsfiscais",
    "CUPONSFISCAISITENS": "cupomfiscalitens",
    "MCMMS": "mcmms",
    "EMPRESABEMS": "empresabens",
    "DOCUMENTOS": "documentos",
    # Malha fiscal (regras de imposto por produto/UF/lei)
    "NFGRUPOFISCALS": "nfgrupofiscals",
    "NFIMPOSTOS": "nfimpostos",
    "NFIMPOSTOESTADOS": "nfimpostoestados",
    "NFCESTS": "nfcests",
    "NFCSTS": "nfcsts",
    "NFCLASTRIBS": "nfclastribs",
    "NFICMS": "nficms",
    "NFPIS": "nfpis",
    "NFCOFINS": "nfcofins",
    "NFIPIS": "nfipis",
    "NFOPERACAOPRODUTOS": "nfoperacaoprodutos",
    "SPEDTIPOITEMS": "spedtipoitems",
    "PRODUTOLEIIMPOSTOS": "produtoleiimpostos",
    "NFEMITIDACARTACORRECAOS": "nfemitidacartacorrecaos",
    # Notificacoes internas (sem consumidor ainda; preservacao do dado)
    "NOTIFICACOES": "notificacoes",
    "NOTIFICACAOUSERS": "notificacaousers",
}

TIPOS = {
    "NUMBER": "numeric",
    "FLOAT": "double precision",
    "BINARY_FLOAT": "double precision",
    "BINARY_DOUBLE": "double precision",
    "VARCHAR2": "text",
    "NVARCHAR2": "text",
    "CHAR": "text",
    "NCHAR": "text",
    "CLOB": "text",
    "NCLOB": "text",
    "LONG": "text",
    "DATE": "timestamp",
}

IGNORA = {"BLOB", "RAW", "BFILE", "LONG RAW", "ROWID", "UROWID", "XMLTYPE"}

# Colunas que NAO sao migradas e cujo volume inviabiliza a extracao: cada nota
# fiscal carrega varios XMLs completos em CLOB (o XML autorizado, o de retorno,
# o de cancelamento...). Puxa-los multiplicaria por ~20 o tempo do espelho para
# dado que o schema novo nem guarda. O XML original permanece no Oracle.
IGNORA_COLUNAS = {
    "XML", "XMLRETORNO", "XMLASSINADO", "XMLRETORNOCANCELAMENTO",
    "XMLRETORNOCOMPLETO", "XMLRETORNOCOMPLETOPATH", "DPECXMLRETORNO",
    "CANCELAMENTOEVEXMLRETORNO", "XMLRETORNOEVENTOCARTACORRECAO",
    "EPECXML", "PRODUTOSJSON", "INFORMACAOCOMPLEMENTAR",
    "INFORMACAOADICIONALFISCO",
}


def sqlplus(sql):
    """Roda SQL no Oracle e devolve o stdout.

    O script vai por ARQUIVO (heredoc no container), nao por stdin: com stdin o
    sqlplus reexibe comandos longos quebrados em varias linhas, e esse eco se
    mistura aos dados. Via @arquivo nao ha eco.
    """
    script = ("SET HEADING OFF FEEDBACK OFF PAGESIZE 0 LINESIZE 32767 "
              "LONG 8000 TRIMSPOOL ON TRIMOUT ON ECHO OFF VERIFY OFF TERMOUT ON\n"
              + sql)
    remoto = "/tmp/_etl_query.sql"
    subprocess.run(
        ["docker", "exec", "-i", ORA, "bash", "-c", f"cat > {remoto}"],
        input=script, capture_output=True, text=True,
        encoding="utf-8", errors="replace",
    )
    # NLS_LANG com AL32UTF8: sem isso o sqlplus emite no charset do SO e os
    # acentos do legado chegam corrompidos (Jose Palhano -> Jos? Palhano).
    p = subprocess.run(
        ["docker", "exec", "-e", "NLS_LANG=AMERICAN_AMERICA.AL32UTF8", ORA,
         "bash", "-c", f"sqlplus -S {ORA_CONN} @{remoto}"],
        capture_output=True, text=True, encoding="utf-8", errors="replace",
    )
    return p.stdout


def colunas(tabela):
    """[(nome, tipo_pg, tipo_oracle)] na ordem do Oracle."""
    out = sqlplus(
        f"SELECT column_name||'{SEP}'||data_type FROM user_tab_columns "
        f"WHERE table_name='{tabela}' ORDER BY column_id;\nEXIT;\n"
    )
    cols = []
    for linha in out.splitlines():
        linha = linha.strip()
        if SEP not in linha:
            continue
        nome, tipo = linha.split(SEP, 1)
        nome, tipo = nome.strip(), tipo.strip().upper()
        if not nome or " " in nome:
            continue
        base = tipo.split("(")[0].strip()
        if base in IGNORA or nome in IGNORA_COLUNAS:
            continue
        pg = "timestamp" if base.startswith("TIMESTAMP") else TIPOS.get(base)
        if pg:
            cols.append((nome, pg, base))
    return cols


def expr(nome, base):
    """Expressao SQL que rende a coluna como texto seguro para o COPY."""
    nome = f'"{nome}"'  # aspas: ha colunas com nome reservado (PASSWORD, ...)
    if base == "DATE" or base.startswith("TIMESTAMP"):
        txt = f"TO_CHAR({nome},'YYYY-MM-DD HH24:MI:SS')"
    elif base in ("CLOB", "NCLOB", "LONG"):
        # A concatenacao inteira da linha nao pode passar de 4000 chars
        # (ORA-01489), entao cada texto longo entra limitado. Campos assim no
        # legado sao observacao/logo em base64 -- nao alimentam o ETL.
        txt = f"TO_CHAR(SUBSTR({nome},1,200))"
    elif base in ("NUMBER", "FLOAT", "BINARY_FLOAT", "BINARY_DOUBLE"):
        # TM9 evita notacao cientifica e o '.5' sem zero a esquerda
        txt = f"TRIM(TO_CHAR({nome},'TM9'))"
    else:
        # idem ORA-01489: campos texto muito largos (observacoes de 1024+)
        # entram limitados para a linha concatenada caber em 4000 chars.
        txt = f"SUBSTR({nome},1,500)"
    # Tabulacao/nova-linha DENTRO do dado quebrariam o COPY em formato text
    # (ha nomes de rua no legado com TAB no meio). A limpeza envolve o texto ja
    # truncado, e o TRANSLATE cobre os tres caracteres de uma vez.
    espacos = "CHR(32)||CHR(32)||CHR(32)"
    limpo = (f"TRANSLATE(NVL({txt},'<<NULO>>'), "
             f"CHR(9)||CHR(10)||CHR(13), {espacos})")
    return limpo


def conta_oracle(tabela):
    out = sqlplus(f"SELECT COUNT(*) FROM {tabela};\nEXIT;\n")
    for linha in out.splitlines():
        if linha.strip().isdigit():
            return int(linha.strip())
    return -1


def espelha(pg, tabela_ora, destino):
    cols = colunas(tabela_ora)
    if not cols:
        print("   (sem colunas legiveis - pulado)")
        return 0

    cur = pg.cursor()
    defs = ", ".join(f'"{n.lower()}" {t}' for n, t, _b in cols)
    cur.execute(f'DROP TABLE IF EXISTS legado."{destino}"')
    cur.execute(f'CREATE TABLE legado."{destino}" ({defs})')
    pg.commit()

    lista_sel = f"||'{SEP}'||".join(expr(n, b) for n, _t, b in cols)
    colunas_pg = ", ".join(f'"{n.lower()}"' for n, _t, _b in cols)
    esperado = conta_oracle(tabela_ora)
    ncols = len(cols)

    # A expressao de concatenacao e longa demais para uma linha de comando do
    # sqlplus (ele passa a ecoar o comando, e o eco se mistura aos dados).
    # Materializar como VIEW deixa a consulta de leitura curta.
    view = f"V_ETL_{tabela_ora}"[:30]
    # Cada linha do script tem de caber em 2499 chars (limite SP2-0027 do
    # sqlplus), entao a concatenacao vai quebrada em uma linha por coluna.
    # Uma coluna sozinha pode passar dos 2499 chars quando o nome e longo
    # (TRANSLATE + NVL + TO_CHAR aninhados): quebrar so ENTRE colunas nao
    # basta -- o sqlplus corta a linha no meio e o Oracle recebe um nome de
    # coluna truncado (ORA-00904 "VALORU...). Quebrar TAMBEM dentro da
    # expressao, nos operadores de concatenacao, mantem cada linha curta.
    partes = [expr(n, b).replace("||", "||\n") for n, _t, b in cols]
    corpo = f"\n||'{SEP}'||\n".join(partes)
    ddl = (f"CREATE OR REPLACE VIEW {view} AS SELECT ROWNUM rn,\n"
           f"{corpo}\nAS c FROM {tabela_ora};\nEXIT;\n")
    saida_ddl = sqlplus(ddl)
    if "SP2-" in saida_ddl or "ORA-" in saida_ddl:
        print("   ERRO ao criar view:", saida_ddl.strip()[:200])
        return 0

    total = 0
    ini = 0
    descartadas = 0
    while True:
        # paginacao estavel por ROWNUM sobre a ordem fisica
        sql = (f"PROMPT {MARCA}\n"
               f"SELECT c FROM {view} WHERE rn > {ini} AND rn <= {ini + BLOCO};\n"
               f"EXIT;\n")
        saida = sqlplus(sql)

        # O sqlplus ECOA o comando quando a linha excede seu buffer, e o eco
        # contem o separador -- seria lido como dado. Tudo antes da marca
        # PROMPT e descartado.
        if MARCA in saida:
            saida = saida.split(MARCA, 1)[1]

        buf = io.StringIO()
        n = 0
        for linha in saida.splitlines():
            if SEP not in linha:
                continue  # cabecalho/aviso/linha vazia do sqlplus
            campos = linha.rstrip("\r\n").split(SEP)
            if len(campos) != ncols:
                descartadas += 1
                continue  # linha quebrada pelo wrap do sqlplus
            # Escapa no lado Python: e a unica etapa que ve o byte final. O
            # TRANSLATE do Oracle nao cobre tudo (ha TAB gravado no dado que
            # sobrevive), e um TAB solto desalinha o COPY inteiro.
            buf.write("\t".join(
                r"\N" if c.strip() in ("", "<<NULO>>") else (
                    c.strip()
                    .replace("\\", "\\\\")
                    .replace("\t", " ")
                    .replace("\n", " ")
                    .replace("\r", " ")
                )
                for c in campos))
            buf.write("\n")
            n += 1

        if n:
            buf.seek(0)
            cur.copy_expert(
                f'COPY legado."{destino}" ({colunas_pg}) FROM STDIN WITH (FORMAT text)',
                buf,
            )
            pg.commit()
            total += n
            print(f"   ... {total}", end="\r", flush=True)

        ini += BLOCO
        # Para SO quando o bloco vem vazio. Usar `esperado` como limite era
        # fragil: qualquer divergencia entre o COUNT e o que a view devolve
        # (ou um bloco parcialmente descartado) encerrava a copia no meio e
        # deixava linhas para tras sem erro alto -- foi o que aconteceu com
        # financeiroparcelas (450 mil de 475 mil).
        if n == 0:
            break

    sqlplus(f"DROP VIEW {view};\nEXIT;\n")

    if descartadas:
        print(f"   AVISO: {descartadas} linha(s) descartada(s) por formato")
    if esperado >= 0 and total != esperado:
        # Divergencia aqui e FALHA, nao ruido: significa dado que ficou para
        # tras. Marcado como ERRO para nao se perder no meio do log.
        print(f"   ERRO: espelho INCOMPLETO — origem={esperado} destino={total} "
              f"(faltam {esperado - total})")
    return total


def main():
    alvos = [a.lower() for a in sys.argv[1:]]
    pg = psycopg2.connect(PG_DSN)
    pg.cursor().execute("CREATE SCHEMA IF NOT EXISTS legado")
    pg.commit()

    total = 0
    for tabela_ora, destino in MAPA.items():
        if alvos and destino not in alvos:
            continue
        print(f"-> {tabela_ora} -> legado.{destino}")
        try:
            n = espelha(pg, tabela_ora, destino)
            print(f"   {n} linhas          ")
            total += n
        except Exception as e:
            pg.rollback()
            print(f"   ERRO: {str(e)[:300]}")

    print(f"\nTotal espelhado: {total} linhas")


if __name__ == "__main__":
    main()
