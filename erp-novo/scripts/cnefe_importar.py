#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Importa logradouros e bairros oficiais do CNEFE (IBGE, Censo 2022).

FONTE
    ftp.ibge.gov.br — CSV por municipio, um ZIP por codigo IBGE.
    106,8 milhoes de enderecos, 5.570 municipios, com NUMERO e COORDENADA.
    Dado publico federal: sem chave, sem cota, sem restricao de uso.

POR QUE NAO O GOOGLE MAPS
    A Google Maps Platform nao tem operacao que ENUMERE os logradouros de um
    municipio -- Geocoding responde sobre um endereco por vez, Places busca por
    proximidade. E os Termos de Servico proibem armazenar o conteudo fora de um
    mapa Google. O CNEFE nao so permite como e a fonte oficial do mesmo dado.

POR QUE POR MUNICIPIO, E NAO O BRASIL INTEIRO
    Guarapuava = 2 MB / 90 mil enderecos. Curitiba = 24 MB. O pais inteiro passa
    de dezenas de GB, e a operacao usa um punhado de cidades. O script baixa sob
    demanda; `--uf PR` traz um estado inteiro quando for o caso.

GRANULARIDADE
    O CSV tem uma linha por ENDERECO. Agregamos por (logradouro, bairro),
    guardando a faixa de numeracao e o centroide. O numero exato do cliente
    continua digitado e validado por geocodificacao -- guardar 90 mil linhas por
    cidade nao teria uso.

USO
    python cnefe_importar.py --municipio 4109401
    python cnefe_importar.py --uf PR --limite 20
    python cnefe_importar.py --municipio 4109401 --saida guarapuava.csv

    Sem --saida, gera o CSV que o comando `cnefe:importar` do Laravel consome.
"""

import argparse
import csv
import io
import os
import re
import sys
import unicodedata
import urllib.request
import zipfile
from collections import defaultdict

BASE = ('https://ftp.ibge.gov.br/Cadastro_Nacional_de_Enderecos_para_Fins_Estatisticos'
        '/Censo_Demografico_2022/Arquivos_CNEFE/CSV/Municipio')

# UF -> codigo, para montar o diretorio "41_PR" do FTP.
UFS = {
    'RO': 11, 'AC': 12, 'AM': 13, 'RR': 14, 'PA': 15, 'AP': 16, 'TO': 17,
    'MA': 21, 'PI': 22, 'CE': 23, 'RN': 24, 'PB': 25, 'PE': 26, 'AL': 27,
    'SE': 28, 'BA': 29, 'MG': 31, 'ES': 32, 'RJ': 33, 'SP': 35, 'PR': 41,
    'SC': 42, 'RS': 43, 'MS': 50, 'MT': 51, 'GO': 52, 'DF': 53,
}

# Tipos de logradouro removidos da chave de busca: sao o que MAIS varia na
# digitacao ("R.", "RUA", "Av", "AVENIDA") e nao distinguem uma via da outra.
TIPOS = (
    'rua', 'avenida', 'travessa', 'alameda', 'praca', 'rodovia', 'estrada',
    'largo', 'viela', 'via', 'passagem', 'ladeira', 'servidao', 'quadra',
    'conjunto', 'vila', 'jardim', 'parque', 'nucleo', 'chacara', 'colonia',
    'linha', 'ramal', 'trevo', 'anel', 'eixo', 'marginal', 'complexo',
    # Abreviaturas: o operador digita "R. das Flores" tanto quanto por extenso.
    # A pontuacao ja virou espaco, entao o que sobra e a letra solta.
    'r', 'av', 'tv', 'al', 'pc', 'rod', 'est', 'pca', 'trav', 'lgo',
)

# Romanos usuais em nome de via -> digito. "XV de Novembro" e "Quinze de
# Novembro" sao a MESMA via, e na base real as duas grafias coexistem.
# "i" fica de fora: viraria "1" e destruiria nomes que o contenham.
ROMANOS = {
    'ii': '2', 'iii': '3', 'iv': '4', 'v': '5', 'vi': '6', 'vii': '7',
    'viii': '8', 'ix': '9', 'x': '10', 'xi': '11', 'xii': '12', 'xiii': '13',
    'xiv': '14', 'xv': '15', 'xvi': '16', 'xvii': '17', 'xviii': '18',
    'xix': '19', 'xx': '20', 'xxi': '21', 'xxv': '25', 'xxx': '30',
}


# Numerais por extenso -> digito. Cobre dia (1-31) e os meses/anos usuais em
# nome de via ("Quinze de Novembro", "Vinte e Um de Abril").
EXTENSO = {
    'um': '1', 'primeiro': '1', 'dois': '2', 'tres': '3', 'quatro': '4',
    'cinco': '5', 'seis': '6', 'sete': '7', 'oito': '8', 'nove': '9',
    'dez': '10', 'onze': '11', 'doze': '12', 'treze': '13', 'quatorze': '14',
    'catorze': '14', 'quinze': '15', 'dezesseis': '16', 'dezessete': '17',
    'dezoito': '18', 'dezenove': '19', 'vinte': '20', 'trinta': '30',
}


def sem_acento(texto):
    """Acentuados -> ASCII. Espelha NormalizadorTexto::basico() do PHP."""
    nfkd = unicodedata.normalize('NFKD', texto)
    return ''.join(c for c in nfkd if not unicodedata.combining(c))


def normalizar(texto):
    """
    Chave de casamento: minusculas, sem acento, sem pontuacao, sem o tipo,
    com numerais por extenso convertidos em digito.

    Precisa produzir EXATAMENTE o mesmo resultado que o PHP
    (NormalizadorTexto::logradouro), senao a normalizacao no ERP nunca
    encontraria o que este script gravou.
    """
    if not texto:
        return ''
    t = sem_acento(texto.lower().strip())
    t = re.sub(r'[^a-z0-9]+', ' ', t)
    t = re.sub(r'\s+', ' ', t).strip()
    # Remove o tipo APENAS quando ele abre o nome.
    for tipo in TIPOS:
        if t.startswith(tipo + ' '):
            t = t[len(tipo) + 1:]
            break
    # Ruas de data sao das mais comuns e aparecem das duas formas: o CNEFE
    # grava "7 DE SETEMBRO", o operador digita "Sete de Setembro". Sem
    # unificar, justamente essas nunca casariam.
    t = ' '.join(EXTENSO.get(p, ROMANOS.get(p, p)) for p in t.split())
    return t.strip()


def baixar(url, destino):
    """Baixa com User-Agent: o FTP do IBGE recusa cliente sem identificacao."""
    req = urllib.request.Request(url, headers={'User-Agent': 'erp-novo/cnefe-importer'})
    with urllib.request.urlopen(req, timeout=600) as r, open(destino, 'wb') as f:
        while True:
            bloco = r.read(1 << 16)
            if not bloco:
                break
            f.write(bloco)


def listar_municipios(uf):
    """Lista (codigo, nome_arquivo) dos municipios de uma UF no FTP."""
    url = '%s/%02d_%s/' % (BASE, UFS[uf], uf)
    req = urllib.request.Request(url, headers={'User-Agent': 'erp-novo/cnefe-importer'})
    with urllib.request.urlopen(req, timeout=120) as r:
        html = r.read().decode('utf-8', 'replace')
    achados = re.findall(r'href="(\d{7}_[^"]+\.zip)"', html)
    return [(int(a[:7]), a) for a in sorted(set(achados))]


def agregar(caminho_zip):
    """
    Le o CSV do municipio e agrega por (logradouro, bairro).

    O CNEFE traz uma linha por endereco; o que guardamos e o conjunto de vias,
    a faixa de numeracao e o centroide.
    """
    z = zipfile.ZipFile(caminho_zip)
    nome_csv = z.namelist()[0]
    # latin-1: o CNEFE nao e UTF-8, e decodificar errado corrompe todo acento.
    fluxo = io.TextIOWrapper(z.open(nome_csv), encoding='latin-1', newline='')
    leitor = csv.DictReader(fluxo, delimiter=';')

    grupos = defaultdict(lambda: {
        'tipo': '', 'nome': '', 'bairro': '', 'cep': '',
        'nums': [], 'lats': [], 'lngs': [], 'n': 0,
    })

    for linha in leitor:
        nome = (linha.get('NOM_SEGLOGR') or '').strip()
        if not nome:
            continue

        tipo = (linha.get('NOM_TIPO_SEGLOGR') or '').strip()
        titulo = (linha.get('NOM_TITULO_SEGLOGR') or '').strip()
        # O titulo ("PRESIDENTE", "DOUTOR") faz parte do nome da via.
        completo = ' '.join(p for p in [titulo, nome] if p)
        bairro = (linha.get('DSC_LOCALIDADE') or '').strip()

        chave = (normalizar(' '.join(p for p in [tipo, completo] if p)), bairro.upper())
        g = grupos[chave]
        g['tipo'] = g['tipo'] or tipo
        g['nome'] = g['nome'] or completo
        g['bairro'] = g['bairro'] or bairro
        g['n'] += 1

        cep = re.sub(r'\D', '', linha.get('CEP') or '')
        if len(cep) == 8 and not g['cep']:
            g['cep'] = cep

        num = (linha.get('NUM_ENDERECO') or '').strip()
        if num.isdigit():
            n = int(num)
            # Zero e o "sem numero" do CNEFE, nao um endereco no inicio da via.
            if 0 < n < 1000000:
                g['nums'].append(n)

        try:
            lat = float(linha.get('LATITUDE') or '')
            lng = float(linha.get('LONGITUDE') or '')
            g['lats'].append(lat)
            g['lngs'].append(lng)
        except ValueError:
            pass

    return grupos


def linhas_de_saida(cod_ibge, grupos):
    """Converte os grupos em linhas do CSV que o Laravel importa."""
    saida = []
    for (nome_busca, _), g in grupos.items():
        if not nome_busca:
            continue
        nums = g['nums']
        lats = g['lats']
        saida.append({
            'cod_ibge': cod_ibge,
            'tipo': g['tipo'][:30],
            'nome': g['nome'][:255],
            'bairro': g['bairro'][:255],
            'cep': g['cep'],
            'nome_busca': nome_busca[:255],
            'numero_min': min(nums) if nums else '',
            'numero_max': max(nums) if nums else '',
            'enderecos': g['n'],
            'latitude': round(sum(lats) / len(lats), 7) if lats else '',
            'longitude': round(sum(g['lngs']) / len(g['lngs']), 7) if g['lngs'] else '',
        })
    return saida


CAMPOS = ['cod_ibge', 'tipo', 'nome', 'bairro', 'cep', 'nome_busca',
          'numero_min', 'numero_max', 'enderecos', 'latitude', 'longitude']


def processar(cod_ibge, arquivo, uf, tmp, escritor):
    url = '%s/%02d_%s/%s' % (BASE, UFS[uf], uf, arquivo)
    destino = os.path.join(tmp, arquivo)

    if not os.path.exists(destino):
        baixar(url, destino)

    grupos = agregar(destino)
    linhas = linhas_de_saida(cod_ibge, grupos)
    for l in linhas:
        escritor.writerow(l)

    bairros = len({g['bairro'].upper() for g in grupos.values() if g['bairro']})
    enderecos = sum(g['n'] for g in grupos.values())

    # Nao acumula GB de ZIP em disco.
    os.remove(destino)

    return len(linhas), bairros, enderecos


def main():
    p = argparse.ArgumentParser(description='Importa logradouros oficiais do CNEFE (IBGE).')
    p.add_argument('--municipio', type=int, help='Codigo IBGE de 7 digitos.')
    p.add_argument('--uf', help='UF inteira (ex.: PR).')
    p.add_argument('--limite', type=int, default=0, help='Maximo de municipios (0 = todos).')
    p.add_argument('--saida', default='cnefe.csv', help='CSV de saida.')
    p.add_argument('--tmp', default='.cnefe-tmp', help='Diretorio de download temporario.')
    args = p.parse_args()

    if not args.municipio and not args.uf:
        p.error('informe --municipio ou --uf')

    os.makedirs(args.tmp, exist_ok=True)

    if args.municipio:
        uf = None
        for sigla, cod in UFS.items():
            if args.municipio // 100000 == cod:
                uf = sigla
                break
        if uf is None:
            print('Codigo IBGE invalido: %s' % args.municipio, file=sys.stderr)
            return 1
        alvos = [(c, a) for c, a in listar_municipios(uf) if c == args.municipio]
        if not alvos:
            print('Municipio %s nao encontrado no FTP.' % args.municipio, file=sys.stderr)
            return 1
    else:
        uf = args.uf.upper()
        if uf not in UFS:
            print('UF invalida: %s' % uf, file=sys.stderr)
            return 1
        alvos = listar_municipios(uf)
        if args.limite:
            alvos = alvos[:args.limite]

    print('%d municipio(s) a processar. Saida: %s' % (len(alvos), args.saida))

    total_l = total_b = total_e = 0
    falhas = []

    with open(args.saida, 'w', encoding='utf-8', newline='') as f:
        w = csv.DictWriter(f, fieldnames=CAMPOS)
        w.writeheader()

        for i, (cod, arquivo) in enumerate(alvos, 1):
            nome = arquivo[8:-4].replace('_', ' ')
            try:
                l, b, e = processar(cod, arquivo, uf, args.tmp, w)
                total_l += l
                total_b += b
                total_e += e
                print('  [%d/%d] %-32s %6d logradouros  %4d bairros  %8d enderecos'
                      % (i, len(alvos), nome[:32], l, b, e))
            except Exception as exc:
                # Uma cidade que falha nao pode derrubar a importacao das outras.
                falhas.append((cod, nome, str(exc)[:80]))
                print('  [%d/%d] %-32s FALHOU: %s' % (i, len(alvos), nome[:32], str(exc)[:60]),
                      file=sys.stderr)

    print()
    print('Logradouros: %d | Bairros: %d | Enderecos representados: %d'
          % (total_l, total_b, total_e))

    if falhas:
        print('%d municipio(s) falharam:' % len(falhas), file=sys.stderr)
        for cod, nome, erro in falhas:
            print('  %s %s -- %s' % (cod, nome, erro), file=sys.stderr)

    print()
    print('Agora carregue no ERP:')
    print('  php artisan cnefe:importar %s --aplicar' % args.saida)

    return 0


if __name__ == '__main__':
    sys.exit(main())
