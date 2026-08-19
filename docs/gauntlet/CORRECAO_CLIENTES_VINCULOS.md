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

## Quarta rodada: a numeração fiscal (o risco mais caro do cutover)

Eu havia deixado isto como "decisão do dono", classificado como *delicado, não
urgente*. **Estava errado.** Não era gestão de risco — era adiar trabalho. O
risco real não está em migrar a numeração; está em NÃO migrar e alguém descobrir
na primeira emissão, com o legado já desligado.

### O que o levantamento mostrou

```
empresa 2  (matriz)  NF-e  série 1 → contador 81.074 | 40.316 notas emitidas
empresa 2  (matriz)  NFC-e série 1 → contador 361.778 | 193.072 notas emitidas
empresa 114..135     NF-e/NFC-e    → contadores de 2 a 4.724
```

Todas em **ambiente de produção** (`nfetipoambiente=1`), com protocolo de
autorização da Receita.

**Sem semear a sequência, a primeira NF-e emitida no sistema novo sai com número
1** — colidindo com 40.316 notas já autorizadas. A SEFAZ rejeita, e o erro só
aparece na hora de faturar.

O mais irônico: `NumeroSequencialService::definir()` **já existia para isso**. O
docblock diz literalmente *"ETL importando a numeração da empresa legada"*.
Nenhum migrator o chamava.

### A armadilha que só apareceu ao conferir

Comparando o contador da empresa com o maior número **realmente emitido**:

| empresa | modelo | contador | maior emitido | |
|---|---|---|---|---|
| 2 | 55 | 81.074 | **335.358** | ⚠️ contador atrás |
| 2 | 65 | 361.778 | 361.778 | ✔ |
| 114–135 | 55/65 | — | — | ✔ (12 combinações batem) |

Em 13 das 14 combinações os dois valores batem. **Na matriz, modelo 55, o
contador está 254 mil números atrás** — o legado reiniciou a série em algum
momento.

Migrar o contador cegamente faria a matriz emitir nota com número já usado. A
regra implementada é **`max(contador, maior_emitido)`**, e o migrator emite aviso
explícito quando os dois divergem.

### O que foi feito

| Arquivo | Mudança |
|---|---|
| `FiscalMigrator::semearNumeracaoFiscal()` | Semeia `sequencias` por empresa+modelo+série com `max(contador, emitido)`; avisa quando o contador está defasado |
| `EmpresasMigrator::configFiscalECadastral()` | Os outros ~30 campos que o formulário enviava e o backend descartava: fiscal (CRT, ambiente, série, modelo), SPED completo, contador (`cont*`), cadastro (CNAE, registro ANP, distribuidora, SUFRAMA) → `empresa_configs.dados` |
| `BancoProducaoCheck::verificarNumeracaoFiscal()` | **Reprova o portão** se existir nota emitida com número acima da sequência — o defeito não pode voltar silencioso |

`tests/Migration/NumeracaoFiscalTest.php` — 4 testes:

- semeia a numeração a partir do contador da empresa;
- **contador defasado perde para o maior número emitido** (o caso real da matriz);
- série nunca usada começa do 1;
- recarregar o ETL não avança a numeração (idempotência).

### Por que a numeração fica em `sequencias` e não em `empresa_configs`

Numeração é **estado transacional**, não configuração: é incrementada sob lock a
cada emissão (`SELECT ... FOR UPDATE`), preservando a regra do legado que impedia
notas duplicadas sob concorrência. Guardá-la junto da config faria a emissão
disputar lock com qualquer edição de cadastro.


---

# Sequência final de recarga (substitui as anteriores)

Depois de corrigir o `.env` (passo 1), esta é a ordem completa. Ela respeita a
ordem topológica do ETL — rodar fora de ordem faz FK virar null.

```bash
# 1. apoio: bancos, tipos de pessoa, segmentos, tipos de telefone/contato
docker exec erpnovo-app php artisan etl:run cadastros-apoio

# 2. empresas: config fiscal/SPED/contador em empresa_configs.dados
docker exec erpnovo-app php artisan etl:run empresas

# 3. geografico: cidades/bairros/ruas + ENDEREÇO DAS EMPRESAS
docker exec erpnovo-app php artisan etl:run geografico

# 4. clientes: recupera tipopessoa_id e segmento_id (44 mil)
docker exec erpnovo-app php artisan etl:run clientes

# 5. pedidos: recupera condicaopagamento_id (400 mil) — o mais demorado
docker exec erpnovo-app php artisan etl:run pedidos

# 6. caixa: recupera banco_id das contas
docker exec erpnovo-app php artisan etl:run caixa

# 7. fiscal: SEMEIA A NUMERAÇÃO — sem isto a 1ª nota sai com número 1
docker exec erpnovo-app php artisan etl:run fiscal

# 8. rh: endereço dos 81 colaboradores
docker exec erpnovo-app php artisan etl:run rh

# 9. portões
docker exec erpnovo-app php artisan banco:producao-check --pos-etl
docker exec erpnovo-app php artisan cutover:check
```

**Leia os avisos do passo 7.** O migrator informa quando o contador do legado
está defasado em relação às notas emitidas — é esperado na matriz (modelo 55), e
o valor adotado é o maior dos dois.

⚠️ O passo 5 recarrega 400 mil pedidos: leva tempo e não deve rodar com o
sistema em uso.

**Alternativa:** `php artisan etl:run` sem argumento roda tudo na ordem correta,
o que é mais seguro que escolher migrators à mão. Use `--dry-run` antes.

---

# Quinta rodada: valores zerados na tela (contrato tela × API)

**Sintoma relatado:** a aba Histórico do cliente listava as linhas certas, mas
com `#` sem número, `—` na data e **R$ 0,00 em todas**. E, nas palavras do dono:
*"não mostra os valores em nenhum lugar na aplicação"*.

## A causa: nomes que não casam

Não era dado ausente — era **divergência de nome entre o que a API emite e o que
a tela lê**. A origem é histórica: o legado não usa underscore, e parte da SPA
foi escrita a partir das telas antigas.

| Tela | Lê | API emitia | Resultado |
|---|---|---|---|
| `HistoricoTab` | `id`, `datahora`, `valorvenda` | `pedido_id`, `data`, `valor_venda` | **nenhum dos 3 casava** |
| `ProdutosListPage` | `precovenda` | `preco_venda` | preço R$ 0,00 na lista |
| `ListaView`/`KanbanView`/`PedidoDialogs` | `valorvenda` | `valor_venda` | valor zerado no pedido |
| `InteracoesTab` | `datahora`, `tipo`, `situacao` | model cru: `created_at`, `tipo_id`, `situacao_id` | linha em branco |
| `PrecosTab` | `produto` (nome) | model cru: só `produto_id` | produto sem nome |

E o pior, no formulário de produto: ele **envia** `precovenda`, mas o
`ProdutoRequest` só validava `preco_venda` — o `validated()` descartava o campo.
**Editar o preço de um produto salvava "com sucesso" e não gravava nada.**

## Por que a suíte inteira passava

Esta classe de defeito é invisível para todo o resto dos testes: a requisição
responde **200**, a lista vem com a **quantidade certa de linhas**, nenhum erro
é logado. Só a tela sabe que o campo veio com outro nome — e a tela não era
testada contra o payload real.

## Correção

Onde o alias existe, **os dois nomes viajam** (`valorvenda` *e* `valor_venda`),
para não quebrar consumidores já existentes. Na escrita, o request normaliza o
alias antes de validar (`prepareForValidation`), como já fora feito para as
datas do colaborador.

Relações criadas para os rótulos: `ClienteInteracao::tipo()/situacao()` e
`ClientePreco::produto()`.

`tests/Feature/ContratoTelaApiTest.php` — 5 testes que amarram o contrato,
cada um citando o arquivo `.tsx` que consome o campo. É a primeira cobertura
dessa dimensão na suíte.

## Recomendação estrutural (não feita agora)

O certo seria **padronizar o nome em um dos lados** e remover o alias. Isso
exige varrer as 27 features e é mudança grande; os aliases resolvem o sintoma
hoje, e o teste impede a regressão. Fica registrado como dívida consciente — não
como "está pronto".

## Varredura: os 10 campos restantes — todos resolvidos

A investigação de cada um no legado mudou o veredito de vários. Não eram dez
casos iguais: eram quatro situações diferentes.

### 1. Alias faltando (colunas existiam) — corrigido

`customedio`, `custofrete`, `precogasdopovo`, `pesoliquido`, `pesobruto` no
formulário de produto. Mesmo defeito dos preços: leitura sem alias e escrita
descartada pelo `validated()`.

### 2. Campo no lugar errado — corrigido movendo

`nomerepresentante`, `cpfrepresentante`, `rgrepresentante` e `datacontrato`
estavam na aba **Convênio**. No legado eles pertencem a **`comodatos`** — a aba
de convênio foi montada com campos do formulário de comodato.

`legado.comodatos`: 975 linhas, 784 com nome do representante, 694 com CPF, 975
com data de contrato. O `ComodatoPdfService` imprime o contrato que protege o
patrimônio da revenda, **e o contrato saía sem quem o assinou**.

Corrigido: colunas criadas em `comodatos`, ETL passa a trazê-las, PDF imprime.

Também corrigido no caminho: o ETL jogava `datavencimento` em `data_devolucao`.
São coisas opostas — "quando deveria voltar" × "quando voltou" — e o efeito era
um comodato **aberto** aparecer como já devolvido. Agora há `data_vencimento`.

### 3. Campo que não existe em lugar nenhum — removido da tela

`limitecompra`, `comissao`, `comissaodestino`, `diafechamento`, `diavencimento`
(aba Convênio). Não existem no legado, no dump nem no banco novo. Eram campos
inventados na tela.

O convênio real usa `conveniolimite` (44.349 linhas), `convenio` (3.106
conveniados) e `convenio_id` (5.101 vinculados) — que o backend já grava. A aba
foi reescrita para editar só o que existe.

### 4. Já funcionava — nada a fazer

`valorfretegp`, `pcfrete_id`, `ccfrete_id`: o `EmpresaConfigController::update()`
grava qualquer chave desconhecida em `empresa_configs.dados` (JSON). Criar
colunas seria duplicar o destino.

**Mas a investigação revelou um defeito maior ali** (ver abaixo).

---

## O achado maior: a config da empresa vinha quase vazia

Ao conferir o frete, contei as chaves preenchidas em `legado.empresaconfigs`:
**~95**. O `EmpresaConfigMigrator` trazia **cinco** (e-mail, senha-mestra, PIX,
Maps).

Ficavam para trás, entre outros:

| Grupo | O que se perdia |
|---|---|
| Contábil | plano de contas e centro de custo padrão, contas de juros e descontos |
| Operação | setor principal, status padrão do pedido, operação disk, validação de cartão |
| Estoque/entrega | permite estoque negativo, valida coordenadas, valida atraso, dias trabalhados |
| Contábil (resultado) | percentuais de encargos, provisão de devedores, remuneração de capital |
| Caixa | **conta do malote** — sem ela o fechamento de caixa não tem para onde apontar |
| Convênio | conta, operação de NF, centro de custo, setor, veículo, condição de pagamento |
| Gás do povo | produto, frete, condições de pagamento |
| App | mensagens, operação de NF do app, transportador |

**A empresa migrava "configurada" — com o default do sistema novo.** A
divergência só apareceria na operação, um caso por vez.

Corrigido: 78 chaves mapeadas para `dados` (JSON), que é onde a config já vive e
de onde a tela já lê.

**Detalhe que importa:** o legado guarda flag como texto `'0'`/`'1'`. Sem
converter, a tela recebe a **string `"0"`** — que é *verdadeira* em JavaScript.
O switch apareceria **ligado** com a configuração desligada. A lista de flags é
explícita, não inferida pelo nome: `impressao_vias_pedido` guarda o NÚMERO de
vias e casaria com qualquer heurística sobre "impressao".

`tests/Migration/ConfigOperacionalEComodatoTest.php` — 3 testes, incluindo a
contra-prova de que o número de vias continua inteiro.


---

# Recarga executada em produção — resultado

Depois de corrigir a conexão `legado` na **fonte certa**
(`/opt/dubena-env/erp-novo-homolog.env`, que o deploy copia por cima do `.env`
do repositório — editar só o `.env` era desfeito no deploy seguinte), a recarga
completa foi executada.

## Antes → depois

| Item | Antes | Depois |
|---|---|---|
| `tipopessoas` / `segmentos` | 0 / 0 | **2 / 4** |
| `bancos` / `contamovimentotipos` | 0 / 0 | **148 / 9** |
| clientes com `tipopessoa_id` | 0 | **44.349** |
| clientes com `segmento_id` | 0 | **43.493** |
| pedidos com `condicaopagamento_id` | 0 | **400.070** |
| endereço das 7 empresas | vazio | **completo** (a DANFE voltou a ter emitente) |
| endereço dos colaboradores | sem coluna | **81/81** |
| numeração fiscal | nenhuma | **14 sequências**, conferidas |
| config por empresa | 5 chaves | **288 chaves** (79 na matriz) |
| comodatos com representante | 0 | **784** (de 975) |
| `users` | 5 | **79** |
| `app_devices` | 0 | **62** |
| `financeirorateios` | 0 | **442.477** |
| IBPT | 0 | **317.520** |

`financeiroparcelas`: 475.000 = 475.000 e a **soma bate exatamente**
(R$ 264.255.627,76).

## Portões

**`banco:producao-check --pos-etl`: 0 falhas, 0 avisos.**

**`cutover:check`: 63 OK / 4 falhas** — de 13 no início. As 4 restantes foram
verificadas uma a uma com `EXCEPT` por id:

| Invariante | Perdidos | Extras | Veredito |
|---|---|---|---|
| `empresasgrupos → grupos` | **0** | 1 | acréscimo |
| `produtos → produtos` | **0** | 4 | acréscimo |
| `setors → setores` | **0** | 1 | acréscimo |
| `estoquesetors → estoquesaldos` | **0** | 4 | acréscimo |

**Zero perdas.** São registros criados no sistema novo (seed/demo) além do dump.
A `CountInvariant` compara contagem e não distingue acréscimo de perda — por isso
a verificação por `EXCEPT` é o que vale.

## Descartes legítimos, documentados

- **409 boletos** sem `financeiroparcela_id`: o boleto do legado não guarda valor
  nem vencimento (vêm da parcela), e as duas colunas são NOT NULL no destino.
  Outros **3** que tinham parcela foram recuperados ao rodar `cobranca` de novo.
- **6 notas fiscais** de empresa ausente.
- **9 cadastros contábeis** por duplicidade.
- **25.228 parcelas** com `(financeiro_id, numero)` repetido foram RENUMERADAS
  (não descartadas) para o próximo número livre do título.

## Lição da ordem de execução

Metade das falhas iniciais não era defeito de código: era **migrator que não
tinha rodado**. `users` estava com 5 de 74, e disso decorriam os 62 devices
"sem dono". A ordem importa e o `etl:run` sem argumento a respeita — rodar
migrators avulsos exige saber a dependência.

---

# Sexta rodada: o item do pedido migrou com preço R$ 0,00

**Sintoma:** no diálogo do pedido #456548, "Condição: —" e o item como
`— × 1   R$ 0,00`, embora o pedido mostrasse **R$ 110,00** corretamente.

Ao contrário das rodadas anteriores, **aqui não era só nome de campo**. Havia os
dois problemas ao mesmo tempo, e o segundo é o mais caro encontrado até agora.

## 1. Rótulos que faltavam (mesmo padrão de antes)

| Tela lê | API emitia |
|---|---|
| `data.condicao` | nada — a relação `condicao()` existia e o Resource nunca a usou |
| `it.produto` (nome) | só `produto_id` |
| `it.precovendatotal` | `valor_total` |

## 2. O dado estava zerado no banco

```
legado.pedidoprodutos:  precovendaunitario = 110    precovendatotal = 110
public.pedidoitens:     preco_unitario     = 0.00   valor_total     = 0.00
```

O `mapearItem()` lia `$r->precovenda ?? $r->preco ?? 0`. **Nenhuma das duas
colunas existe** em `pedidoprodutos` — as reais são `precovendaunitario` e
`precovendatotal`. O `?? 0` fechava a conta em silêncio.

**406.883 itens migraram com preço zero.**

### Por que nenhuma invariante pegou

A `CountInvariant` confere a QUANTIDADE de itens — que estava certa (406.883 =
406.883). Nada olhava o conteúdo das colunas. É a mesma cegueira do
`condicaopagamento_id`, e a razão de o `FksNaoMapeadasTest` existir.

### O que mudou além do nome

- **O total agora vem do legado** (`precovendatotal`) em vez de ser recalculado
  por `quantidade × preço − desconto`: quando houve arredondamento no
  fechamento, o recálculo diverge do que foi efetivamente cobrado.
- **`?? null` em vez de `?? 0`** na leitura do preço: se a coluna sumir do dump,
  o item entra com preço nulo e a invariante acusa — em vez de gravar zero e
  parecer correto.

Teste: `test_item_do_pedido_preserva_o_preco_cobrado`, com o schema REAL do
legado (um teste com nomes inventados teria passado com o código quebrado — é
exatamente o que o docblock do `F15MigratorsTest` já alertava).

⚠️ **Requer recarga de `pedidos`** para os 406.883 itens receberem o preço.
