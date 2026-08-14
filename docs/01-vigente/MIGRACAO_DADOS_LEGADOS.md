# Migração dos dados legados → ERP-NOVO (multi-tenant)

Registro do que foi feito com o dump de 13/08/2026 e do que ele revelou sobre os
sistemas antigos. Serve a dois propósitos: (1) deixar o ambiente de teste
carregado com dados reais e (2) ser a base da **ferramenta de auto-migração no
painel**, para que um revendedor migre sozinho.

---

## 1. As três fontes

| Fonte | Tecnologia | Volume | O que é |
|---|---|---|---|
| `ctrl2qti.DO.2026-08-12.dmp` | Oracle 11.2 (Data Pump, 8 GB) | 222 tabelas | ERP legado `ctrl-web` — **fonte-mestre** |
| `sgcm_api.sql` | MySQL 5.7 (78 MB) | 38 tabelas | App "Gás em Casa" (consumidor) |
| `monitora-001.sql` | MySQL 5.7 (3,3 GB) | 24 tabelas | Rastreamento de frota |

Números do ERP legado: **44.349 clientes**, **400.070 pedidos** (2020→2026),
**406.883 itens**, 26 produtos, 7 empresas (filiais da Distribuidora Dubena).

O app: 20.632 usuários, 61.502 pedidos, 23.487 endereços, 21.906 avaliações.
O monitora: 32 veículos, 18 cercas, **16.113.791 posições GPS** (2019→2026).

### Como as três se ligam

Descoberta central: **o app não é uma base independente, é um canal do ERP.**

- `sgcm_api.pedidos.erp_id` → preenchido em **100%** dos 61.502 pedidos.
- Do lado Oracle, `CLIENTES.API_ID` e `PEDIDOS.APIPEDIDO_ID` são as pontes de volta.

Por isso o migrador do app **não recria** cliente nem pedido — só traz o que
existe apenas nele (endereços de entrega e avaliações). Recriar duplicaria a base.

O monitora, ao contrário, é mesmo independente: tem numeração de empresas
própria (1=Distribuidora Dubena, 2=Central Gás, 3=Dubena Particular, 4=QTI) que
**não** corresponde à do ERP (2, 114–117, 134, 135). O casamento é por nome.

---

## 2. Resultado da carga

| Entidade no destino | Registros |
|---|---|
| `monitora_posicoes` | 16.113.791 |
| `pedidoitens` | 406.883 |
| `pedidos` | 401.273 |
| `clientetelefones` | 68.571 |
| `clientes` | 66.557 |
| `cliente_enderecos` | 31.878 |
| `pedido_avaliacoes` | 21.905 |
| `monitora_veiculos` | 37 |
| `empresas` | 10 |

**Nenhum registro descartado.** A primeira execução deixava ~2,05 milhões de
linhas de fora; cada caso foi tratado (ver 2.1).

Integridade verificada no destino: **zero órfãos** em pedido→cliente,
item→pedido, item→produto, telefone→cliente, endereço→cliente, avaliação→pedido;
e `pedidoitens.empresa_id` sempre igual ao `pedidos.empresa_id` (isolamento
multi-tenant íntegro).

### 2.1 Como cada descarte foi eliminado

A primeira execução descartava ~2,05 milhões de linhas. Todas eram dados reais;
nenhuma tinha razão para sumir. O que passou a ser feito:

| Antes descartado | Qtd | Tratamento |
|---|---|---|
| Usuários do app sem `API_ID` | 11.104 | criados como cliente (com telefones), com o id de origem na observação |
| Endereços desses usuários | 10.145 | acompanham os clientes criados |
| Pedidos do app após o corte do dump ERP | 1.203 | migrados com seus itens |
| Avaliações presas a esses pedidos | 204 → 0 | idem |
| Veículos de Central Gás/QTI/Particular | 9 | as 3 empresas viraram tenant próprio |
| Cercas dessas empresas | 4 | idem |
| Cercas sem polígono | 4 | migradas inativas, área a definir |
| Posições de rastreador sem veículo | ~1,12 M | veículo-marcador inativo por device |
| Posições dos veículos não migrados | ~0,9 M | resolvidas com as empresas criadas |

Dois achados de negócio que sobreviveram (não são defeito, são o retrato do
legado): ~10,6 mil pessoas baixaram o app e nunca viraram cliente do ERP; e
5 rastreadores emitiam posição sem nunca terem sido cadastrados como veículo —
um deles com 1 milhão de posições.

Anomalia de dado (do legado, não da migração): 38 posições datadas de 2010–2011,
de rastreador com relógio dessincronizado. O resto cobre 2019→2026.

---

## 3. Defeitos encontrados (e corrigidos)

Achados que valem para qualquer migração futura — inclusive a do painel.

### 3.1 Ids do legado não eram preservados (crítico)

`Model::updateOrCreate(['id' => $x], ...)` **descarta** o id: `id` não está no
`$fillable`, então o auto-increment escolhe outro. Curitiba entrava como id 3 em
vez de 4106902, e toda FK do dump passava a apontar para a linha errada.

Corrigido com o trait `App\Etl\Support\PreservaIdsDoLegado`, que grava pelo
Query Builder com `upsert` e ressincroniza a sequence. **Este era o bug que
inviabilizava a migração inteira.**

### 3.2 Erros silenciosos

Os migradores tinham `catch (\Throwable) { return []; }`. Quando a tabela
`cidades` do Oracle não tinha a coluna `ativo`, a leitura falhava e o migrador
reportava **sucesso com zero linhas**. Agora seleciona apenas colunas existentes.

### 3.3 `cidade_id` guarda código IBGE, não a PK

Em `bairros`/`ruas`, `cidade_id` é o código IBGE (4109401 = Guarapuava). O
schema novo espera a PK. Sem tradução, violação de FK. Complicador: distritos
(Palmeirinha, Colônia Vitória) compartilham o IBGE da sede — desempata quem tem
`id == cod_ibge`.

### 3.4 Documentos com máscara

CNPJ vinha como `04.190.715/0001-05` (18 chars) contra `varchar(14)`. Normalizado
para dígitos em empresas e clientes (CPF/CNPJ/CEP).

### 3.5 `empresa_id` nas tabelas filhas

O multi-tenant exige `empresa_id` NOT NULL em telefones, itens de pedido,
endereços, posições. O legado (pré-multi-tenant) não tem essa coluna: é herdada
do pai (cliente/pedido/veículo).

### 3.6 Sujeira de cadastro no legado

Real, encontrado nos dados: "CORENEL VIVIDA" (typo de Coronel Vivida), "Rua
Palhoça" cadastrada como cidade, `cod_ibge` = 0 e 999999999, "Tunas do Paraná"
duplicada em dois ids, "Jaraguá do Siul". A migração unifica duplicatas e
reporta; **não** corrige typos silenciosamente.

---

## 4. Como reproduzir

```bash
# 1. Subir as fontes
docker run -d --name dubena-pg -e POSTGRES_PASSWORD=dubena -e POSTGRES_DB=erp_novo -p 55432:5432 postgres:16
docker run -d --name dubena-mysql -e MYSQL_ROOT_PASSWORD=dubena -p 53306:3306 -v "<dumps>:/dumps" mysql:5.7
docker run -d --name dubena-ora --shm-size=2g -e ORACLE_PASSWORD=dubena -p 51521:1521 -v "<dumps>:/dumps" gvenzl/oracle-xe:11

# 2. Restaurar (Oracle exige a imagem COMPLETA: a `-slim` não tem XDB e o impdp falha com ORA-39213)
docker exec dubena-mysql sh -c 'mysql -uroot -pdubena --max_allowed_packet=1G < /dumps/sgcm_api.sql'
docker exec dubena-mysql sh -c 'mysql -uroot -pdubena --max_allowed_packet=1G < /dumps/monitora-001.sql'
docker exec dubena-ora bash -c "impdp system/dubena@localhost/XE DIRECTORY=DUMPDIR \
  DUMPFILE=ctrl2qti.DO.2026-08-12.dmp SCHEMAS=CTRL2QTI REMAP_TABLESPACE=%:CTRL_TS \
  TRANSFORM=SEGMENT_ATTRIBUTES:N EXCLUDE=STATISTICS"

# 3. Schema novo + espelho do Oracle em Postgres
php artisan migrate --database=pgsql_owner --force
python database/etl/espelhar_oracle.py

# 4. ETL (ordem de dependência resolvida pelo registry)
php artisan etl:run estados
php artisan etl:run empresas
php artisan etl:run geografico
php artisan etl:run clientes
php artisan etl:run produtos
php artisan etl:run pedidos
php artisan etl:run monitora-legado
php artisan etl:run app-gasemcasa
python database/etl/migrar_posicoes.py          # 16M posições (--dias N para amostra)
```

`database/etl/espelhar_oracle.py` existe porque os migradores leem da conexão
`legado` (Postgres) com os nomes de tabela do legado, mas o dump real é Oracle
11g — que o `python-oracledb` em modo thin não suporta e para o qual não há
Instant Client no host. O script lê via `sqlplus` e grava com `COPY`.

Armadilhas do `sqlplus` que o script contorna: `SPOOL` trunca em 2499 chars;
linhas de comando longas são **ecoadas** e se misturam aos dados (daí a VIEW
intermediária); `ORA-01489` limita a concatenação a 4000 chars (textos longos
entram truncados); sem `NLS_LANG=AMERICAN_AMERICA.AL32UTF8` os acentos chegam
corrompidos; e há **TAB dentro do dado** ("Rua primeiro⇥de maio") que quebraria
o `COPY`.

---

## 5. A ferramenta no painel (implementada)

`SuperAdmin → Migração` (`/superadmin/migracoes`). Assistente de 5 passos:

1. **Nova migração** — tipo de origem (ERP PostgreSQL, app MySQL, Monitora
   MySQL) + credenciais. Ficam **cifradas** (`encrypted:array`) e nunca voltam
   pela API: são segredo do banco de um terceiro.
2. **Conectar** — testa o acesso e lista as tabelas encontradas.
3. **Diagnosticar** — conta as entidades e levanta alertas **sem gravar nada**.
4. **Mapear empresas** — o único passo que exige humano. Cada empresa da origem
   recebe uma sugestão (casada por CNPJ, depois por nome normalizado) e três
   opções: usar empresa existente, criar nova, ignorar. O padrão para quem não
   tem correspondente é **criar** — nunca descartar.
5. **Migrar e conferir** — a carga vai para a fila (`ExecutarMigracaoJob`,
   timeout 6h), com progresso por etapa na tela. No fim: tabela de
   lidos/migrados/não-migrados, botão para conferir contagens (invariantes
   origem×destino) e download CSV do que não entrou.

Há também **Simular**, que roda o ETL em `--dry-run` e mostra o que aconteceria.

Peças: `MigracaoService`, `ExecutarMigracaoJob`, `MigracaoController`,
tabelas `migracoes` e `migracao_descartes`, tela `SaMigracaoPage.tsx`.
Testes em `tests/Feature/MigracaoFerramentaTest.php` (9 casos), incluindo a
garantia de que a senha do banco de origem não vaza nem na API nem no banco.

## 5b. Princípios de desenho (o que a migração real ensinou)

O objetivo é o revendedor migrar sozinho. O que a experiência acima ensina:

### 5.1 O fluxo tem de ser um assistente com portões

1. **Conectar / enviar dump** — Oracle, MySQL ou arquivo. Detectar a origem
   automaticamente pelas tabelas presentes.
2. **Diagnóstico (antes de gravar nada)** — contagens por entidade, e a lista de
   problemas que já sabemos existir: documentos com máscara, cidades duplicadas,
   FKs órfãs, registros sem empresa. É aqui que o `--dry-run` do `etl:run`
   entrega valor.
3. **Mapeamento assistido** — a parte que **não** dá para automatizar: dizer qual
   empresa do legado corresponde a qual tenant. No monitora, 3 das 4 empresas não
   tinham correspondente. A tela precisa mostrar os nomes lado a lado e deixar o
   usuário casar, criar nova, ou ignorar.
4. **Execução com progresso** — 16M de linhas não cabem numa requisição HTTP.
   Job em fila + barra de progresso + retomada.
5. **Relatório final** — gravados, pulados e **por quê**, com CSV dos descartados
   para conferência.

### 5.2 Princípios que a migração provou serem necessários

- **Nunca inventar dado.** FK obrigatória ausente → descarta a linha e reporta.
  FK opcional ausente → grava null. Nunca um id aleatório.
- **Preservar ids do legado.** Já explicado em 3.1; é a diferença entre migrar e
  corromper.
- **Falha nunca vira sucesso vazio.** Um migrador que retorna 0 sem dizer por quê
  é pior que um que estoura.
- **Idempotência.** `upsert` por id: rodar de novo não duplica, permitindo
  retomar uma carga interrompida.
- **Herança de tenant explícita.** Toda filha herda `empresa_id` do pai.

### 5.3 O que já está pronto para reuso

- `App\Etl\MigratorRegistry` — ordem por dependência, resolvida topologicamente.
- `App\Etl\Support\PreservaIdsDoLegado` — carga com id + FK sanitizada.
- `App\Etl\Invariants\*` — contagem, integridade, soma, saldo.
- `php artisan etl:run --dry-run --check` — simulação e portão de validação.
- Migradores de empresas, geográfico, clientes, produtos, pedidos, monitora e app.

### 5.4 O que ainda falta

- **Migradores de financeiro, estoque, caixa, fiscal e cobrança**: continuam no
  esqueleto otimista — foram escritos antes de existir um dump real e assumem
  nomes/colunas que não conferem com o Oracle. Precisam do mesmo trabalho de
  conferência que empresas, clientes e pedidos receberam. É a maior pendência.
- **Origem por arquivo**: hoje a ferramenta conecta num banco de pé. Aceitar o
  upload de um `.sql`/`.dmp` exigiria restaurá-lo no servidor primeiro.
- **Origem Oracle direta**: o `espelhar_oracle.py` faz a ponte fora do painel,
  porque o PHP do container não tem driver Oracle. Para o revendedor com ERP
  Oracle, esse passo ainda é operado por nós.
- **Reprocessar descartes pela tela**: os registros já ficam em
  `migracao_descartes` com o dado original; falta o botão que os reenvia.
