# Correção: clientes sem tipo, segmento e rótulos no formulário

**Sintoma relatado.** Ao abrir um cliente em `/novo/app/clientes/[id]`, o
formulário não mostrava os dados completos: "Tipo de Pessoa" e "Segmento"
apareciam como *Selecione…*, e os dropdowns não listavam opção alguma.

**Não era um bug só.** Eram cinco defeitos encadeados, e o mais grave é que
**nenhum deles gerava erro** — a carga reportava sucesso.

---

## Antes: o que é a conexão `legado` (não é o ctrl-web)

O nome confunde, e vale fixar — **o erp-novo tem banco próprio e não lê o banco
do sistema legado.**

| Nome | O que é | Onde vive |
|---|---|---|
| `ctrl-web` | O ERP legado em produção | Container `ctrl-web-db`, **banco separado — o erp-novo nunca toca nele** |
| schema `legado` | Uma *cópia congelada* do dump Oracle | **Dentro** do banco `erp_novo`, 121 tabelas |

```
banco erp_novo
├── schema public   → 190 tabelas  ← o sistema novo roda aqui
└── schema legado   → 121 tabelas  ← cópia do dump, SÓ LEITURA, só para o ETL
```

O schema `legado` é a **matéria-prima do ETL**: o comando `etl:run` lê de
`legado.clientes` e escreve em `public.clientes`. Por isso a conexão chamada
`legado` no `.env` aponta para o **mesmo banco** (`LEGADO_DB_DATABASE=erp_novo`)
com `LEGADO_DB_SCHEMA=legado`.

**Por que dentro do mesmo banco:** o ETL compara origem e destino para validar as
invariantes (contagens, Σ movimentos = saldo). Na mesma conexão isso é uma query;
entre bancos separados seria frágil e lento.

**Depois do cutover:** o schema `legado` pode ser descartado — já cumpriu o papel.
O `CUTOVER_RUNBOOK.md` mantém o legado de pé, sem escrita, por 30 dias como rede
de segurança; só depois disso faz sentido `DROP SCHEMA legado CASCADE`.

---

## A cadeia de falhas

```
.env de produção com a conexão `legado` errada
        │  LEGADO_DB_HOST vazio, DATABASE=ctrl (banco que não existe),
        │  faltando LEGADO_DB_SCHEMA=legado
        ▼
CadastrosApoioMigrator não consegue ler  →  catch (\Throwable) { return []; }
        │  engoliu o erro de conexão em silêncio
        ▼
0 linhas lidas, 0 gravadas  →  CountInvariant compara 0 = 0  →  PASSA ✅
        │  a invariante confirmou uma carga que não aconteceu
        ▼
tipopessoas / segmentos ficam VAZIAS
        ▼
ClientesMigrator.anularFksInvalidas() faz o que foi mandado:
a FK aponta para tabela vazia  →  vira NULL
        ▼
44.349 clientes perdem tipopessoa_id e 43.493 perdem segmento_id
        ▼
Formulário exibe cliente sem vínculo — que é a verdade do banco
```

Havia ainda um defeito **independente** desse encadeamento: mesmo com os dados
corretos, o formulário continuaria mostrando *Selecione…*, porque o endpoint
`GET /clientes/{id}` nunca devolveu os campos `*_label` que a tela lê.

---

## O que foi corrigido no código

| # | Arquivo | Defeito | Correção |
|---|---|---|---|
| 1 | `app/Etl/Migrators/CadastrosApoioMigrator.php` | `updateOrCreate` chaveava por `(grupo_id, descricao)` e a linha **não continha `id`** — geraria ids novos, quebrando as FKs dos clientes | Chaveia por `id` e grava com `forceFill` (o `id` não está no `$fillable` de `CadastroApoio`, então `fill` o descartaria em silêncio) |
| 2 | `app/Etl/Migrators/CadastrosApoioMigrator.php` | `catch (\Throwable) { return []; }` transformava falha de conexão em "0 linhas, tudo certo" | Passa a usar o trait `RegistraFalhaDeLeitura`, que só engole "tabela ausente" e **registra aviso** para qualquer outra falha |
| 3 | `app/Http/Resources/ClienteResource.php` + `ClienteController.php` | O `show` não devolvia `tipopessoa_label`, `segmento_label`, `cidade_label`, `bairro_label`, `rua_label` — o front lia cinco campos inexistentes | Resource emite os rótulos; controller faz eager-load das relações (no `show` **e** no `update`) |
| 4 | `app/Models/Cliente/Cliente.php` | Faltavam as relações `tipopessoa()` e `segmento()` | Adicionadas |
| 5 | `frontend/.../ClienteFormPage.tsx` | Campo "Cód. Contábil" (`consisa_id`) não existe na tabela, nem no request, nem no Resource — digitar ali não gravava nada | Campo removido |
| 6 | `frontend/.../ClienteFormPage.tsx` | A aba Endereço não tinha o campo **Rua**, embora `rua_id` exista e o ETL preencha 44.336 delas | Campo adicionado |
| 7 | `app/Console/Commands/BancoProducaoCheck.php` | Nada detectava "apoio vazio + FKs anuladas" | Novo `verificarCadastrosDeApoio()` (só com `--pos-etl`, read-only) |

Testes de regressão em `tests/Feature/ClienteTest.php`
(`test_show_devolve_os_rotulos_das_fks`).

---

## ⚠️ O passo que ficou pendente para você

O código está corrigido, mas **a produção só volta ao normal depois de dois
passos na VPS** — e o primeiro eu não consegui executar: a edição do `.env` de
produção foi bloqueada pelo mecanismo de segurança do agente (o script
precisava copiar a senha do banco entre variáveis).

### Passo 1 — corrigir a conexão `legado` no `.env`

Arquivo: `/opt/actions-runner-dubena/_work/dubena/dubena/erp-novo/.env`

As chaves `LEGADO_DB_*` devem ter **os mesmos valores** das `DB_*`
correspondentes, porque o espelho do Oracle vive no **mesmo banco `erp_novo`**,
num schema chamado `legado` — não num banco separado.

```bash
ssh root@gasemcasa.com
cd /opt/actions-runner-dubena/_work/dubena/dubena/erp-novo
cp .env .env.bak-$(date +%Y%m%d%H%M%S)     # backup antes de editar
nano .env
```

Deixe assim (copiando os valores das `DB_*` que já estão no arquivo):

```env
LEGADO_DB_HOST=db              # hoje está VAZIO  ← a causa da falha
LEGADO_DB_PORT=5432
LEGADO_DB_DATABASE=erp_novo    # hoje está `ctrl` (banco inexistente)
LEGADO_DB_USERNAME=<o mesmo de DB_USERNAME>
LEGADO_DB_PASSWORD=<o mesmo de DB_PASSWORD>
LEGADO_DB_SCHEMA=legado        # hoje está AUSENTE ← sem isto lê o schema errado
```

A referência comentada está em `erp-novo/.env.production.example` (linhas 79-85).

#### Alternativa: um comando só (copia os valores das `DB_*` automaticamente)

Se preferir não editar à mão, este comando faz backup e alinha as seis chaves
sozinho — ele **lê** os valores de `DB_*` que já estão no arquivo e os replica:

```bash
ssh root@gasemcasa.com
cd /opt/actions-runner-dubena/_work/dubena/dubena/erp-novo

cp .env .env.bak-$(date +%Y%m%d%H%M%S)

python3 - <<'PY'
import io, re
p = ".env"
linhas = io.open(p, encoding="utf-8").read().splitlines()
def ler(k):
    for l in linhas:
        if l.startswith(k + "="):
            return l.split("=", 1)[1]
    return ""
alvo = {
    "LEGADO_DB_HOST": ler("DB_HOST") or "db",
    "LEGADO_DB_PORT": ler("DB_PORT") or "5432",
    "LEGADO_DB_DATABASE": ler("DB_DATABASE") or "erp_novo",
    "LEGADO_DB_USERNAME": ler("DB_USERNAME"),
    "LEGADO_DB_PASSWORD": ler("DB_PASSWORD"),
    "LEGADO_DB_SCHEMA": "legado",
}
vistos, saida = set(), []
for l in linhas:
    m = re.match(r"^(LEGADO_DB_[A-Z]+)=", l)
    if m and m.group(1) in alvo:
        saida.append(m.group(1) + "=" + alvo[m.group(1)]); vistos.add(m.group(1))
    else:
        saida.append(l)
for k, v in alvo.items():
    if k not in vistos:
        saida.append(k + "=" + v)
io.open(p, "w", encoding="utf-8", newline="
").write("
".join(saida) + "
")
print("chaves LEGADO_DB_* alinhadas com DB_*")
PY
```

Depois:

```bash
docker exec erpnovo-app php artisan config:clear
docker exec erpnovo-app php artisan banco:producao-check --pos-etl
```

O portão deve dizer **PASS** em *"App consegue LER o espelho (conexão `legado`)"*.
Se disser FAIL com "permission denied", falta o GRANT:

```sql
GRANT USAGE ON SCHEMA legado TO erp_app;
GRANT SELECT ON ALL TABLES IN SCHEMA legado TO erp_app;
```

### Passo 2 — recarregar apoio e clientes

⚠️ **Só depois do passo 1 passar.** Rodar antes recarrega zero de novo.

```bash
# 1. simula primeiro — não grava nada
docker exec erpnovo-app php artisan etl:run cadastros-apoio --dry-run

# 2. carrega os cadastros de apoio (deve ler 4 segmentos, 2 tipos de pessoa,
#    3 tipos de telefone, 3 tipos de contato, 148 bancos, 9 tipos de movimento)
docker exec erpnovo-app php artisan etl:run cadastros-apoio

# 3. recarrega os clientes, agora que as FKs têm destino válido
docker exec erpnovo-app php artisan etl:run clientes

# 4. confirma
docker exec erpnovo-app php artisan banco:producao-check --pos-etl
```

**Por que recarregar clientes também:** o `tipopessoa_id` deles já foi anulado
no banco. Carregar só o apoio popula os dropdowns, mas os 44 mil clientes
continuam sem vínculo — o dado precisa vir da origem de novo.

O ETL é idempotente por `upsert` de `id`, então recarregar não duplica nada.

### Resultado esperado

| Verificação | Antes | Depois |
|---|---|---|
| `tipopessoas` | 0 | 2 |
| `segmentos` | 0 | 4 |
| `telefonetipos` | 0 | 3 |
| `clientes` com `tipopessoa_id` | 0 | 44.349 |
| `clientes` com `segmento_id` | 0 | 43.493 |

E no formulário: Tipo de Pessoa e Segmento preenchidos, dropdowns com opções.

---

## Por que isso passou despercebido até agora

A `CountInvariant` compara *contagem na origem × contagem no destino*. Quando a
origem está inacessível, ela lê `0` dos dois lados e conclui que a carga foi
fiel. **Uma invariante que compara duas leituras da mesma fonte quebrada não
verifica nada** — ela confirma o próprio erro.

É o mesmo padrão do achado do dry-run em produção (`information_schema`
reportando "0 tabelas referenciam clientes.id" com 40 mil linhas filhas): a
resposta vazia parecia um resultado, mas era a ausência de permissão para ver.

A correção #2 ataca a raiz — falha de leitura agora vira **aviso visível** no
relatório do `etl:run`, em vez de silêncio.

---

## O mesmo defeito em outras telas (corrigido junto)

A busca por `*_label` mostrou que **nenhum** endpoint do sistema emitia esses
campos — só o front os lia. Quatro telas além de clientes:

| Tela | Rótulos | Situação |
|---|---|---|
| Produtos | `classe_label`, `unidade_label`, `vasilhame_label` | ✅ corrigido (relação `retornavel()` criada) |
| Frota / Veículos | `tipo_label`, `combustivel_label` | ✅ corrigido (relações `tipo()` e `combustivel()` criadas) |
| RH / Colaboradores | `cargo_label` | ✅ corrigido |
| Empresas | `cidade_label`, `bairro_label`, `regiao_label` | ⚠️ ver abaixo |

`grupofiscal_label` (Produtos) não foi tratado: `/lookups/nf-grupos-fiscais` é
uma lista **estática** com ids sintéticos (posição no array), sem tabela por trás
— não há relação de onde tirar o rótulo. Funciona, mas o id não é estável.

---

## ⚠️ Duas divergências que encontrei e **não** corrigi

Estão fora do que foi pedido e a decisão é sua — corrigir o formulário ou criar
as colunas muda o comportamento do cadastro.

### 1. Formulário de Colaborador pede endereço que não é gravado

`frontend/src/features/rh/ColaboradoresPage.tsx` exige **Cidade e Bairro como
obrigatórios** (`required`), além de Número e CEP. A tabela `colaboradores`
(`database/migrations/2026_06_22_000200_create_rh_tables.php`) **não tem nenhuma
dessas colunas** — nem o controller as valida.

O usuário preenche, o campo é enviado, e o backend descarta em silêncio.

**Duas saídas:** remover os campos do formulário, ou criar as colunas via
migration se o endereço do colaborador for requisito real do negócio.

### 2. Formulário de Empresa envia FK, mas a tabela guarda texto

`EmpresaFormPage.tsx` envia `cidade_id`, `bairro_id`, `rua_id` e `regiao_id`
(via `AsyncSelect`). O model `Empresa` tem `cidade` e `bairro` como **string
livre**, e `regiao_id`/`rua_id` não existem.

Mesmo efeito: o endereço da empresa não persiste pelo formulário.

**Recomendação:** padronizar em FK como no cliente — cidade e bairro digitados
livremente não casam com os cadastros usados no roteirizador e na logística.

---

# Segunda rodada: a varredura de TODAS as páginas

Depois de corrigir clientes, varri as 27 features da SPA e todas as tabelas de
lookup em produção, procurando o mesmo padrão. **A suspeita estava certa: o
problema não era exclusivo de clientes.** Mas não era um defeito só — eram três.

## Defeito A — cadastros de apoio vazios (6 tabelas)

Todas do `CadastrosApoioMigrator`, o migrator que falhava em silêncio:

| Tabela | Origem | Destino | Telas afetadas |
|---|---|---|---|
| `bancos` | 148 | **0** | Financeiro, Contas |
| `contamovimentotipos` | 9 | **0** | Lançamentos, Malote |
| `segmentos` | 4 | **0** | Clientes |
| `telefonetipos` | 3 | **0** | Clientes (aba Contatos) |
| `clientecontatotipos` | 3 | **0** | Clientes (aba Interações) |
| `tipopessoas` | 2 | **0** | Clientes |

Corrigido na primeira rodada. **Requer recarga** (passo 2 do procedimento).

## Defeito B — FKs que o ETL lia mas não gravava

| Tabela | Coluna | Origem | Destino | Causa |
|---|---|---|---|---|
| `pedidos` | `condicaopagamento_id` | **400.070** | 0 | nunca mapeada no migrator |
| `contas` | `banco_id` | 7 | 0 | lida p/ derivar `tipo`, descartada em seguida |
| `clientes` | `tipopessoa_id` | 44.349 | 0 | efeito cascata do defeito A |
| `clientes` | `segmento_id` | 43.493 | 0 | efeito cascata do defeito A |

### Por que o de pedidos é o mais grave

`condicaopagamento_id` não é um campo decorativo:

- `FinanceiroService` decide o lançamento por ele (à vista × a prazo);
- `MaloteService` confere o fechamento de caixa pelo mesmo campo.

Com 400 mil pedidos migrados sem ele, **o histórico financeiro perdeu a forma de
pagamento** — e a conferência de malote não teria como fechar.

### Por que a correção não foi trivial

`condicaopagamentos` é carregada pelo `ComplementosMigrator`, que roda em **23º**
na ordem topológica. `pedidos` roda em **11º**. E a FK
`pedidos.condicaopagamento_id` é *imediata* (não deferrable), então gravar o
pedido antes da condição existir viola a constraint.

Inverter a dependência criaria ciclo:
`complementos → caixa → financeiro → pedidos → complementos`.

**Solução:** o `PedidosMigrator` passou a carregar as condições ele mesmo, quando
a tabela ainda está vazia, usando **o mesmo critério de deduplicação** do
`ComplementosMigrator` (o legado repete descrições e o destino tem
`UNIQUE(grupo_id, descricao)`; a primeira ocorrência vence, as demais são
remapeadas para ela). Os dois precisam concordar — se divergissem, o segundo a
rodar apontaria para outro id. O `ComplementosMigrator` já tinha o guard
`if (count() === 0)`, então ele apenas calcula o remap para as parcelas.

## Defeito C — rótulos não emitidos pelo backend

Varri os 36 arquivos que usam `AsyncSelect`: **todos** passam `valueLabel`
corretamente. O defeito estava só no backend, que nunca emitiu `*_label`.
Corrigido em Clientes, Produtos, Frota e RH.

Restam dois, ligados a divergências estruturais (ver seção anterior):
`regiao_label` e `grupofiscal_label`.

---

## O que a varredura descartou

Para registro — foram verificados e estão **corretos**:

- **Outros migrators com `catch (\Throwable)` silencioso:** nenhum. O
  `CadastrosApoioMigrator` era o último; os demais já usam
  `RegistraFalhaDeLeitura`.
- **Tabelas do espelho ausentes:** 6 de 130 mapeadas
  (`clienteconvenios`, `contaextratoconfigs`, `creditopiscofins`,
  `motivonaovendas`, `nfrecebidaparcelas`, `pedidomotivoatrasos`) — nenhuma é
  usada por migrator algum; não existem no dump deste cliente.
- **`AsyncSelect` sem `valueLabel`:** nenhum caso.
- **FKs de cidade/bairro/rua/convênio dos clientes, cargo dos colaboradores,
  tipo/combustível dos veículos:** todas preservadas (origem = destino).

## Testes de regressão

`tests/Migration/FksNaoMapeadasTest.php` — 4 testes que falham sem a correção:

- pedido preserva a condição de pagamento;
- condição duplicada cai na canônica (e a duplicada não é gravada);
- conta preserva o banco;
- conta com banco inexistente não quebra a carga.

**Por que faltava um teste assim:** a `CountInvariant` compara a *quantidade* de
linhas (400.070 = 400.070, passa) e nada olhava se as *colunas* chegaram
preenchidas. Uma FK esquecida no `mapearPedido()` não aparecia em lugar nenhum do
relatório do `etl:run`.

---

## Procedimento atualizado de recarga

Depois de corrigir o `.env` (passo 1, inalterado), a recarga precisa incluir os
migrators afetados pelos defeitos A e B:

```bash
# apoio primeiro: é dele que vêm bancos, tipos de pessoa, segmentos...
docker exec erpnovo-app php artisan etl:run cadastros-apoio

# clientes: recupera tipopessoa_id e segmento_id
docker exec erpnovo-app php artisan etl:run clientes

# pedidos: recupera condicaopagamento_id dos 400 mil
docker exec erpnovo-app php artisan etl:run pedidos

# caixa: recupera banco_id das contas
docker exec erpnovo-app php artisan etl:run caixa

# confere
docker exec erpnovo-app php artisan banco:producao-check --pos-etl
docker exec erpnovo-app php artisan cutover:check
```

⚠️ `etl:run pedidos` recarrega 400 mil linhas — leva tempo. É idempotente
(upsert por id), mas **não rode durante uso ativo do sistema**.

---

# Terceira rodada: os dois defeitos estruturais, cobertos

As duas divergências que eu havia deixado para decisão foram corrigidas. A
investigação mudou o veredito: **não era o formulário que estava errado — era o
schema novo que ficou para trás.**

## O que o legado sempre teve

| | `cidade_id` | `bairro_id` | `rua_id` | `regiao_id` |
|---|---|---|---|---|
| `legado.colaboradores` | 81/81 | 81/81 | ✔ | — |
| `legado.empresas` | 7/7 | 7/7 | 7/7 | 7/7 |

Endereço por FK, 100% preenchido, nos dois. O formulário da SPA estava certo o
tempo todo; faltavam as colunas no destino. A migration original de `empresas`
até registrava a intenção — `"Endereço (normalizado virá em N1)"` — mas o N1
normalizou o cliente e nunca voltou ali.

## A consequência que ninguém tinha visto

As **7 empresas migraram sem cidade, bairro e endereço**. Não é cosmético:

```php
// DanfePdfService.php:159 — o emitente da nota fiscal
$empresa->endereco ?? '', $empresa->numero ?? '', $empresa->bairro ?? '',
$empresa->cidade ?? '', $empresa->uf ?? '', $empresa->cep ?? '',
```

**A DANFE estava saindo sem o endereço do emitente.** O mesmo vale para os PDFs
de comodato e vale-gás.

Causa: o `EmpresasMigrator` lia `$r->cidade` e `$r->bairro` como texto — colunas
que **não existem** no legado, que guarda só as FKs. Lia null, gravava null,
ninguém reclamou.

## Como foi corrigido, sem quebrar o que funciona

**A empresa passa a ter as duas representações, com papéis distintos:**

- **FK** (`cidade_id`/`bairro_id`/`rua_id`/`regiao_id`) — a fonte da verdade. É o
  que o formulário edita, o que casa com o cadastro geográfico e o que o
  roteirizador consegue usar.
- **texto** (`cidade`/`bairro`/`endereco`) — **derivado** da FK por
  `App\Domain\Empresa\EnderecoEmpresaSync`. Continua existindo porque os PDFs
  fiscais imprimem a string, e uma nota emitida não deve mudar de endereço se a
  cidade for renomeada no cadastro depois.

Trocar texto por FK teria quebrado os três PDFs. Manter só o texto teria deixado
o endereço fora do roteirizador. As duas, sincronizadas, resolvem ambos.

| Arquivo | Mudança |
|---|---|
| `2026_08_18_000200_endereco_normalizado_*.php` | FKs em `empresas` e `colaboradores` (+ `cep`/`uf`/`numero`/`complemento` no colaborador). Todas nullable; retrofit liga a FK onde o texto já casa com o cadastro |
| `EnderecoEmpresaSync.php` | Deriva o texto das FKs; a UF vem da cidade. **Nunca apaga** texto quando a FK vem vazia |
| `EmpresaController` | Aplica o sync no `store` e no `update`; carrega as relações para os rótulos |
| `EmpresaRequest` | Valida as 4 FKs — sem isso o `validated()` as descartava |
| `ColaboradorController` | Valida e devolve o endereço; **aceita `datanascimento`/`dataadmissao`** (grafia da SPA) |
| `GeograficoMigrator` | Preenche o endereço das empresas **aqui**, não no `EmpresasMigrator` |
| `RhMigrator` | Traz o endereço dos 81 colaboradores; passa a depender de `geografico` |

### Por que o endereço da empresa é carregado no `GeograficoMigrator`

`geografico` depende de `empresas` (precisa dos grupos), então as cidades só
existem **depois** que as empresas foram gravadas — inverter criaria ciclo. A
carga usa o **mesmo remap de cidade duplicada** que o migrator já calcula: sem
ele, a empresa apontaria para a linha descartada na deduplicação.

## Terceiro defeito, achado no caminho

`ColaboradorController::validar()` só conhecia `data_nascimento`/`data_admissao`,
mas a SPA envia `datanascimento`/`dataadmissao` (grafia do legado). **As datas de
nascimento e admissão nunca eram gravadas** — nem na criação, nem na edição. O
`validate()` as descartava antes de chegar ao service.

## Testes

`tests/Feature/EnderecoEmpresaColaboradorTest.php` — 6 testes, todos falham sem
a correção:

- empresa grava o endereço por FK;
- **o texto é derivado da FK** (o que mantém a DANFE correta);
- empresa devolve os rótulos;
- colaborador grava o endereço;
- colaborador aceita as datas na grafia da SPA;
- colaborador devolve endereço e rótulos na edição.

---

## ⚠️ Achado que NÃO corrigi (precisa da sua decisão)

O formulário de Empresa envia **22 campos que não existem no backend**:

```
inscricao_estadual_st, suframa, cnae, registro_anp, email,
contnome, contcpf, contcnpj, contcrc, conttelefone, contemail,
nfeserie, nfenumero, nfetipoambiente, nfecrt, nfceserie, nfcenumero,
nfcetipoambiente, nfeemite, nfceemite, spedemite, distribuidora
```

Destes, **existem no legado e estão preenchidos nas 7 empresas**: `cnae` (6/7),
`email` (7/7), os 6 campos do contador (`cont*`, 1/7) e **todos os 11 fiscais**
(`nfeserie`, `nfenumero`, `nfecrt`, `nfetipoambiente`… 7/7).

**Por que deixei para você decidir:** `nfenumero` é a numeração sequencial da
NF-e. Migrá-la errado é pior que não migrar — emitir nota com número já usado é
problema fiscal sério. E a emissão ainda depende do certificado A1, que está
pendente no `GUIA_DO_DONO.md`, então nada disso está em uso hoje.

**Recomendação:** tratar junto com a habilitação da NF-e, quando o certificado
estiver instalado e for possível conferir a numeração contra o legado antes de
emitir a primeira nota. Não é urgente; é delicado.
