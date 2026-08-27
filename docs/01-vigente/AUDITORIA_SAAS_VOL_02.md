# Volume 2 — Models (os 179)

> Recorte: `erp-novo/app/Models/` — relações, casts, `$fillable`, escopos de
> tenant, constantes de domínio. Fonte: código e banco. Documentação não
> consultada.
>
> **Status: FECHADO** — 179/179 lidos. 7.032 linhas.

---

## Achados

### A-2.1 — `audit_logs` tem `empresa_id` e o model não escopa

**Critério:** C6 (escopo de tenant errado) · **Severidade: ALTA**

**O que é.** `AuditLog` não usa `BelongsToTenant` nem `BelongsToGrupo`. A tabela
**tem** `empresa_id`. O próprio comentário do model declara a estratégia:
*"Escopo por empresa via coluna empresa_id (**filtrado no relatório**)"*.

**Evidência.**
- `app/Models/AuditLog.php:10` — o comentário
- Consulta: `audit_logs` possui coluna `empresa_id`
- Comparação: 103 models usam `BelongsToTenant`; este não

**Por que impede o SaaS.** A trilha de auditoria registra "quem mudou o quê" —
inclui valores antes/depois em JSON, ou seja, dados de negócio de cada revenda. O
isolamento depende de **cada consulta lembrar de filtrar**. Uma consulta nova que
esqueça o `where` expõe a trilha de outro tenant. É exatamente a classe de defeito
que a migration `f02_empresa_id_em_tabelas_filhas` corrigiu para 20+ tabelas —
esta ficou de fora.

**Nota:** `login_logs` também tem `empresa_id` sem escopo, mas ali é
**deliberado e correto** — o log de tentativa de login ocorre antes de resolver o
tenant e precisa registrar e-mails que não existem. A migration documenta isso.

**Direção de correção.** `BelongsToTenant` no model, ou RLS na tabela (verificar
no Volume 3 se a policy existe).

---

### A-2.2 — `support` dá bypass total de RBAC e vê todas as empresas do grupo

**Critério:** C4 (convenção não declarada) · **Severidade: ALTA**

**O que é.** O flag `users.support` provoca três bypasses:

```php
public function podeAcessarEmpresa(int $empresaId): bool
{
    if ($this->support) { return true; }  // acesso a QUALQUER empresa
```
```php
public function temPermissao(string $chave, ?int $empresaId = null): bool
{
    if ($this->support) { return true; }  // ignora RBAC inteiro
```
```php
public function empresasVisiveis(?int $grupoId = null): array
{
    if ($this->support) {
        return Empresa::query()->where('grupo_id', $grupoId)->pluck('id')...
```

**Evidência.** `app/Models/User.php` — os três métodos. O `$fillable` protege
corretamente contra mass-assign (comentário T1.8 explica), e o `Gate::before` no
`AuthServiceProvider` completa o bypass.

**Por que impede o SaaS.** No ERP de uma empresa, "suporte vê tudo" é regra
herdada do legado e defensável. Num SaaS, o grupo pode conter revendas de
**donos diferentes** — e um `support` de uma delas passa a ver as outras. A
plataforma já tem a camada correta para isso (`platform_admins`, guard próprio,
2FA obrigatório, trilha `platform_audit_logs` append-only), deliberadamente
separada do tenant. O `support` é um segundo caminho de acesso cross-empresa que
**não** passa por essa trilha.

**Direção de correção.** Depende de D-1. Se grupo = rede do mesmo dono, mantém-se
com auditoria. Se grupo pode misturar donos, `support` precisa virar
`platform_admin` ou ser restrito à empresa de origem.

---

### A-2.3 — Plano de contas e centro de custo são por grupo

**Critério:** C6 (escopo de tenant errado) · **Severidade: ALTA**

**O que é.** `PlanoConta` e `CentroCusto` usam `BelongsToGrupo`. A contabilidade
gerencial — a árvore de classificação de receita e despesa — é compartilhada por
todas as empresas do grupo.

**Evidência.**
- `app/Models/Financeiro/PlanoConta.php` — `use BelongsToGrupo`
- `app/Models/Financeiro/CentroCusto.php` — idem
- `Financeiro` (o título) é `BelongsToTenant`, mas aponta para `planoconta_id` e
  `centrocusto_id` que são do grupo

**Por que impede o SaaS.** Duas revendas independentes teriam o mesmo plano de
contas obrigatoriamente. Uma não pode criar a conta "Frete de transferência" sem
que apareça para a outra. Some-se `condicaopagamentos` (também por grupo) e a
autonomia financeira do tenant desaparece.

**Direção de correção.** Ligada a D-1. Padrão comum em SaaS: plano de contas
**modelo** na plataforma, copiado para o tenant no onboarding e editável dali.

---

### A-2.4 — A régua da vigilância nasce de defaults no código, não de configuração

**Critério:** C4 (convenção não declarada) · **Severidade: MÉDIA**

**O que é.** `ComodatoConfig::daEmpresa()` devolve, quando não há linha no banco,
um objeto **não persistido** com nove valores fixos no PHP.

**Evidência.** `app/Models/Satelite/ComodatoConfig.php`:
```php
return new self([
    'dias_janela' => 180, 'giro_minimo' => 4, 'giro_critico' => 1,
    'queda_atencao' => 40, 'queda_critica' => 70, ...
]);
```

**Por que impede o SaaS.** Os números foram calibrados contra a base da Dubena
(49 clientes vigiados → 24 na fila). Uma revenda com outro perfil — só
industrial, ou só doméstico — herda a régua de outra empresa sem saber, e a tela
de configuração mostra valores que não estão salvos em lugar nenhum. O padrão
correto seria semear a configuração no onboarding.

**Direção de correção.** Default da plataforma (tabela), semeado por empresa no
onboarding, editável. Nunca literal no código.

---

### A-2.5 — `PedidoItem` não congela a natureza nem o custo do item vendido

**Critério:** C1 (conceito ausente) · **Severidade: ALTA**

**O que é.** `PedidoItem` guarda `produto_id`, `quantidade`, `preco_unitario`,
`desconto`, `valor_total`. Não guarda **o que o item era** no momento da venda.

**Evidência.** `app/Models/Pedido/PedidoItem.php` — `$fillable` completo acima.
A natureza (`produto`/`servico`/`taxa`) e o custo médio vivem apenas em
`produtos`, que é mutável.

**Por que impede o SaaS.** Três consequências:
1. Mudar a `natureza` de um produto hoje **reescreve o passado** — um item
   vendido como produto passa a ser lido como serviço em qualquer relatório
   histórico.
2. Não há margem por venda: o `custo_medio` de hoje não é o de quando se vendeu.
3. A conversão de dados não tem como validar retroativamente se a baixa de
   estoque daquele item foi correta.

O sistema já sabe fazer isso onde importou: `ComodatoContrato` congela as
quantidades da versão, e o comentário explica exatamente por quê — *"os números
aqui são congelados na emissão e nunca lidos do comodato depois"*. A mesma
disciplina não chegou ao item de pedido.

**Direção de correção.** Congelar em `pedidoitens`: natureza, custo unitário e
descrição no momento da venda.

---

### A-2.6 — Dois modelos de endereço de cliente, com fontes de verdade diferentes

**Critério:** C5 (conceitos misturados) · **Severidade: MÉDIA**

**O que é.** O endereço do cliente existe em dois lugares com estruturas
incompatíveis:

| Local | Como guarda |
|---|---|
| colunas em `clientes` | FK: `cidade_id`, `bairro_id`, `rua_id` + texto `endereco`, `numero` |
| `cliente_enderecos` | **texto livre**: `bairro`, `cidade` como string |

**Evidência.** `app/Models/Cliente/Cliente.php` (`$fillable` com as FKs) e
`app/Models/Cliente/ClienteEndereco.php` (`$fillable` com `'bairro', 'cidade'`
como texto).

**Por que impede o SaaS.** O endereço de entrega alternativo perde o vínculo com
o cadastro geográfico — logo, perde o código IBGE (que vai para a NF-e), a
normalização de logradouro (CNEFE) e o vínculo com a taxa de entrega por bairro,
que é FK para `bairros`. Uma entrega no endereço secundário não consegue calcular
a taxa por bairro.

**Direção de correção.** `cliente_enderecos` como fonte única, com as mesmas FKs
geográficas; o endereço principal vira uma linha marcada como favorita.

---

### A-2.7 — `Empresa` mantém endereço em texto e em FK simultaneamente

**Critério:** C5 (conceitos misturados) · **Severidade: BAIXA**

**O que é.** `empresas` tem `cidade`/`bairro`/`endereco` como string **e**
`cidade_id`/`bairro_id`/`rua_id` como FK. O model documenta a duplicação e a
razão dela.

**Evidência.** `app/Models/Empresa.php` — o comentário no `$fillable`:
*"FKs são a fonte da verdade do endereço; `cidade`/`bairro`/`endereco` acima são
o texto DERIVADO delas (ver EnderecoEmpresaSync), mantido porque a DANFE e os
PDFs imprimem a string"*. E os relacionamentos ganharam sufixo (`cidadeCadastro`,
`bairroCadastro`) para não colidir com as colunas de texto.

**Por que anotar.** A duplicação é **consciente, documentada e sincronizada** por
um serviço dedicado — não é defeito. Fica registrada porque o conversor precisa
saber que há dois caminhos e qual é o autoritativo, e porque o mesmo padrão em
`clientes` (A-2.6) **não** tem serviço de sincronização.

**Direção de correção.** Nenhuma imediata. Reavaliar se os PDFs puderem ler da
relação.

---

### A-2.8 — A ferramenta de conversão já existe em esqueleto

**Critério:** C1 (conceito ausente na prática) · **Severidade: ALTA**
(oportunidade, não defeito)

**O que é.** `Migracao` e `MigracaoDescarte` implementam exatamente a ferramenta
que o SaaS precisa para receber cópias atualizadas de dados reais.

**Evidência.** `app/Models/Migracao/Migracao.php`:
- máquina de estados própria: `pendente` → `diagnosticando` →
  `aguardando_mapeamento` → `migrando` → `concluida` / `falhou`
- `config` com cast `encrypted:array` (credenciais da origem, protegidas)
- `diagnostico`, `mapa_empresas`, `resultado` como JSON
- `progresso` e `etapa_atual` para acompanhamento
- `platform_admin_id` — operada pela plataforma, não pelo tenant

E `MigracaoDescarte` registra `migrador`, `entidade`, `motivo`, `chave_origem`,
`dados` — ou seja, **o que não entrou e por quê**. É a resposta direta ao defeito
histórico de 2026-08-14 (`catch (\Throwable) { return []; }` reportando zero
linhas como sucesso).

**Por que importa.** Nenhum dos dois models tem escopo de tenant — corretamente:
é ferramenta de plataforma. O `aguardando_mapeamento` indica que já foi pensado
para exigir decisão humana sobre correspondência de empresas, que é exatamente o
que a conversão de convenções vai precisar.

**Direção de correção.** Nenhuma. Registrar como **base a reaproveitar** no plano
(Volume 15) em vez de construir do zero. Verificar no Volume 14 o quanto está
implementado além do model.

---

### A-2.9 — `NfImposto` duplica cada campo tributário em versão PJ e PF

**Critério:** C3 (flag como proxy) · **Severidade: MÉDIA**

**O que é.** A matriz tributária espelha ~25 campos com prefixo `pf_`:
`cst_icms`/`pf_cst_icms`, `aliq_icms`/`pf_aliq_icms`, `mva`/`pf_mva`, e assim por
diante. `NfImpostoEstado` repete o mesmo padrão.

**Evidência.** `app/Models/Fiscal/NfImposto.php` — `$fillable` com 50+ campos,
metade prefixada. O mesmo em `NfImpostoEstado`.

**Por que impede o SaaS.** É o mesmo padrão de A-1.19 (comissão com
`percentual_app`): uma dimensão do negócio — o tipo de destinatário — virou
prefixo de coluna. Um terceiro caso (produtor rural, órgão público, exportação)
exige mais 25 colunas em duas tabelas.

**Nota de contexto:** este é um porte fiel do `ImpostoDB` do legado, feito
deliberadamente para não alterar regra fiscal. A observação vale para o redesenho,
não como crítica à migração.

**Direção de correção.** Linha por (operação × grupo fiscal × **tipo de
destinatário**), em vez de coluna por tipo.

---

### A-2.10 — Tipos de missão são constante PHP, não cadastro

**Critério:** C4 (convenção não declarada) · **Severidade: MÉDIA**

**O que é.** `Missao::TIPOS` fixa seis tipos no código: `panfletagem`,
`visita_comercial`, `divulgacao_valegas`, `prospeccao`, `acao_promocional`,
`campanha_bairro`.

**Evidência.** `app/Models/Missao/Missao.php` — `public const TIPOS = [...]`.

**Por que impede o SaaS.** Uma revenda que queira "recolhimento de vasilhame" ou
"cobrança em campo" como missão precisa de deploy. O mesmo padrão aparece em
`PermissionCondition::TIPOS` (`limite`, `ownership`, `horario`) — ali é
defensável, porque cada tipo tem código de avaliação próprio; em missão, não há
comportamento distinto por tipo.

**Direção de correção.** Cadastro de tipos por empresa, com os seis atuais como
semente do onboarding.

---

### A-2.11 — `SorteioNumero` e `ChecklistPergunta` sem escopo de tenant

**Critério:** C6 (escopo de tenant errado) · **Severidade: MÉDIA**

**O que é.** Dois models filhos sem `BelongsToTenant`, ao contrário de seus
irmãos. `SorteioNumero` guarda `cliente_id` — dado de tenant.

**Evidência.**
- `app/Models/Crm/SorteioNumero.php` — sem trait; `$fillable` inclui `cliente_id`
- `app/Models/Crm/ChecklistPergunta.php` — sem trait
- Contraste: `ChecklistResposta` (irmã) **tem**
  `$tenantParent = ['checklist_execucao_id' => 'checklist_execucoes']`

O escopo indireto existe (ambas dependem de um pai escopado por grupo), mas a
consulta direta ao model não filtra nada. Confirmado no banco: nenhuma das duas
tabelas tem `empresa_id`.

**Por que impede o SaaS.** `Sorteio` é por **grupo**; se duas revendas
independentes compartilham grupo, os números de sorteio — com o `cliente_id` de
cada uma — ficam visíveis entre elas. Ligado a D-1.

**Direção de correção.** `$tenantParent` apontando ao pai, como já faz
`ChecklistResposta`.

---

## Cobertura

**179 de 179 models lidos.** Por pasta: raiz (18), Apoio (19), Caixa (4),
Cliente (9), Cobranca (4), Crm (9), Estoque (7), Financeiro (7), Fiscal (11),
Frota (8), Geografico (7), Gestao (5), Logistica (6), Migracao (2), Missao (5),
Mobile (3), Monitora (9), Organizacao (3), Pagamento (2), Pedido (8), Produto (5),
Rh (9), Saas (7), Satelite (8), Telefonia (2), Venda (2).

Lido também `app/Domain/Produto/NaturezaItem.php` (o enum por trás do cast de
`produtos.natureza`), por ser inseparável do model.

**Consultas ao banco:** 2, leitura, role `erp_app`.

**Nota sobre o fechamento deste volume.** A primeira versão foi declarada
fechada com ~80 models lidos (raiz, Produto, Pedido, parte de Cliente, Satelite,
Financeiro, Estoque). O usuário questionou a cobertura e a verificação confirmou
a lacuna: Fiscal, Frota, Monitora, Crm, Rh, Logistica, Missao, Geografico,
Gestao, Mobile, Saas, Organizacao, Cobranca, Venda, Telefonia, Pagamento e
Migracao — cerca de 100 arquivos — não tinham sido lidos.

A leitura dos 100 restantes rendeu **4 achados adicionais**, incluindo A-2.8, que
muda o plano: a ferramenta de conversão já existe em esqueleto. É o segundo caso
consecutivo (Volume 1 teve o mesmo) em que a parte não lida continha achado
estruturante.

**Correção de método registrada.** A primeira contagem de "models sem escopo de
tenant" deu **42**, por buscar `use BelongsToTenant` no arquivo. Estava errada:
os 17 cadastros de `Apoio/` **herdam** o escopo da classe abstrata
`CadastroApoio`. A contagem correta, considerando herança, é **24** — e destes,
22 são legitimamente globais (catálogos, plataforma, autenticação). Fica
registrado porque o mesmo erro de método afetaria qualquer varredura futura por
trait.

**Encaminhado ao Volume 3:** verificar se `audit_logs` tem policy de RLS (o que
mitigaria A-2.1), e se as tabelas com escopo por grupo têm policy por `grupo_id`.

---

## Resumo

| Critério | Achados |
|---|---|
| C1 — conceito ausente | 2 (A-2.5, A-2.8) |
| C3 — flag como proxy | 1 (A-2.9) |
| C4 — convenção não declarada | 3 (A-2.2, A-2.4, A-2.10) |
| C5 — conceitos misturados | 2 (A-2.6, A-2.7) |
| C6 — escopo de tenant errado | 3 (A-2.1, A-2.3, A-2.11) |

**11 achados · 5 ALTA · 5 MÉDIA · 1 BAIXA.**

### O que este volume acrescenta ao padrão

O Volume 1 mostrou estrutura correta abandonada. Os models mostram o **oposto**:
a camada está bem construída — 103 models com `BelongsToTenant`, casts tipados em
toda parte, segredos com `encrypted`, enums de domínio (`NaturezaItem`,
`AgrupamentoStatus`, `ContaExtratoAcao`), `$hidden` nos campos sensíveis, e
`$tenantParent` resolvendo o escopo de tabelas filhas.

Os defeitos aqui não são de descuido, e sim **de premissa**: quase todos vêm de
o sistema ter sido desenhado para **uma empresa com filiais** e não para
**revendas independentes**. `support` vendo o grupo inteiro, plano de contas
compartilhado, régua de vigilância com default calibrado numa base específica —
cada um é correto sob a premissa antiga e errado sob a nova.

Isso reforça D-1 como a decisão que destrava o resto: sem ela, boa parte deste
volume não tem correção definível.
