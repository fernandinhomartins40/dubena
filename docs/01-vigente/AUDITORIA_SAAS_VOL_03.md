# Volume 3 — Tenancy e escopo

> Recorte: as 4 migrations de RLS, os traits de escopo (`BelongsToTenant`,
> `BelongsToGrupo`), o middleware `ResolveTenant`, e o **estado efetivo do banco
> de produção** — policies, FORCE, roles e privilégios.
>
> **Status: FECHADO.**

---

## O que funciona (verificado, não presumido)

Antes dos achados, o que foi testado e está correto — porque auditoria que só
lista defeito não permite decidir onde mexer.

**A RLS isola de fato.** Teste direto em produção, conectando como a role do
runtime (`erp_app`):

| Contexto | `SELECT count(*) FROM clientes` |
|---|---|
| sem `app.empresa_id` | 55.453 |
| `SET app.empresa_id = '114'` | **1** |
| `SET app.empresa_id = '2'` | **55.436** |

**A role do runtime não burla.** `erp_app` é `NOSUPERUSER`, `NOBYPASSRLS`, e
**não é membro** de `erp` (o dono). Confirmado em `pg_roles` e `pg_auth_members`.
Este era o furo crítico que a auditoria anterior encontrou — o app conectava como
superuser, e o PostgreSQL ignora RLS silenciosamente nesse caso. Está fechado.

**168 policies `tenant_isolation` ativas**, aplicadas por descoberta automática
das colunas (`empresa_id`, senão `grupo_id`), não por lista manual.

**O cast é seguro.** `nullif(current_setting(...), '')::int` — sem o `nullif`
antes do `::int`, uma GUC vazia estoura "invalid input syntax for integer",
porque o planner avalia o ramo direito do `OR` mesmo com o guard. A migration
`rls_cobertura_tabelas_novas` normalizou todas.

---

## Achados

### A-3.1 — Sem `app.empresa_id`, a policy libera tudo

**Critério:** C4 (convenção não declarada) · **Severidade: ALTA**

**O que é.** Todas as 168 policies têm a mesma forma:

```sql
USING (
    nullif(current_setting('app.empresa_id', true), '') IS NULL
    OR empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
)
```

O primeiro ramo do `OR` significa: **variável não setada → nenhuma restrição**.
Verificado acima: sem tenant, `erp_app` lê os 55.453 clientes de todas as
empresas.

**Evidência.** `2026_06_24_000200_f02_habilitar_rls_postgres.php:41-52`,
`2026_06_26_000300_rls_tenant_completa.php:137-148`, e a normalização em
`2026_07_03_000300`. A escolha é documentada: *"espelha o comportamento do global
scope, que não filtra sem tenant. Isso mantém os crons globais funcionando"*.

**Por que impede o SaaS.** O isolamento passa a depender de **toda** via de
acesso setar a variável. `ResolveTenant` faz isso no HTTP, mas qualquer caminho
fora dele — comando artisan, job em fila, seeder, console `tinker`, uma conexão
de relatório — enxerga a base inteira. Num ERP de empresa única isso é
conveniência; num SaaS, é a diferença entre "isolado por construção" e "isolado
se ninguém esquecer".

O risco não é teórico: o próprio comentário lista os crons como beneficiários do
comportamento. Um cron que hoje varre "todas as empresas" é correto sob a
premissa de dono único e vira vazamento sob a premissa nova.

**Direção de correção.** Fail-closed por padrão (sem tenant → nenhuma linha), com
uma variável explícita de escape (`app.bypass_tenant = 'on'`) que os processos
administrativos setam conscientemente e que fica registrada. Inverte o default de
"aberto salvo prova em contrário" para "fechado salvo autorização explícita".

---

### A-3.2 — 33 tabelas com policy e sem `FORCE ROW LEVEL SECURITY`

**Critério:** C6 (escopo de tenant errado) · **Severidade: MÉDIA**

**O que é.** Das 201 tabelas com policy, **33 não têm FORCE** — entre elas as
mais sensíveis: `clientes`, `pedidos`, `produtos`, `financeiros`,
`financeiroparcelas`, `contas`, `contamovimentos`, `comodatos`, `colaboradores`,
`boletos`, `empresas`.

**Evidência.** Consulta a `pg_class.relforcerowsecurity`: 168 com FORCE, 33 sem.
As migrations **aplicam** `FORCE` (`aplicarPolicy()` faz ENABLE + FORCE + CREATE
POLICY), então o estado atual divergiu do pretendido — provavelmente por ordem de
execução das migrations intermediárias que faziam `NO FORCE` no `down()`.

**Gravidade real, medida.** Menor do que aparenta. `FORCE` só importa para o
**dono** da tabela: sem ele, o owner ignora as policies. O dono aqui é `erp`
(superuser) — que **já ignora RLS de qualquer forma**, com ou sem FORCE. O
runtime usa `erp_app`, que não é dono nem membro do dono, e para quem a policy
vale independentemente de FORCE. O teste de isolamento acima confirma.

Portanto: **não há vazamento hoje**. O achado é de *defesa em profundidade* — se
amanhã a aplicação passar a conectar com o owner (por engano de configuração, ou
num script de manutenção), essas 33 tabelas ficam sem proteção alguma enquanto as
outras 168 continuam protegidas.

**Direção de correção.** `FORCE` em todas, e um teste de invariante que falhe se
alguma tabela com policy estiver sem — o mesmo padrão do `RlsCoberturaTest` que
já existe para cobertura.

---

### A-3.3 — Tabela de backup com dados de tenant, fora de toda proteção

**Critério:** C6 (escopo de tenant errado) · **Severidade: ALTA**

**O que é.** `_bkp_autocadastro_20260820` — 277 linhas, com `empresa_id` **e**
`grupo_id`, **sem policy de RLS**.

**Evidência.** Consulta de cobertura: aparece na lista de tabelas com coluna de
tenant e sem `tenant_isolation`. Contagem: 277 linhas.

**Por que impede o SaaS.** É dado real de cliente numa tabela que a descoberta
automática de RLS não alcançou (foi criada fora de migration, por script de
manutenção em 2026-08-20). Qualquer consulta a ela cruza tenants. Pior: é
invisível ao processo — nenhum teste, nenhuma invariante e nenhuma migration
sabe que existe.

**O padrão que isto revela:** a RLS por descoberta automática só protege o que
passa por migration. Objetos criados à mão em produção ficam fora — e num SaaS,
"tabela temporária de manutenção com dados de cliente" é exatamente o que não
pode existir sem controle.

**Direção de correção.** Remover a tabela (é backup de operação concluída) e
criar invariante que falhe quando exista tabela com `empresa_id`/`grupo_id` sem
policy — transformando o achado numa checagem contínua, como o `golive:check`
já faz com outras invariantes.

---

### A-3.4 — A allowlist de RLS é correta, mas empurra o isolamento para o app

**Critério:** C4 (convenção não declarada) · **Severidade: MÉDIA**

**O que é.** Nove tabelas estão deliberadamente fora da RLS:

| Tabela | Razão documentada | Avaliação |
|---|---|---|
| `grupos` | raiz do tenancy | correto |
| `users` | login antes de haver tenant | correto |
| `role_user`, `permission_role`, `empresa_user`, `roles` | pivots de RBAC | correto |
| `empresa_configs` | "resolvido por empresa_id explícito no controller" | **frágil** |
| `audit_logs`, `login_logs`, `platform_audit_logs` | recebem `empresa_id` NULL por design | correto, mas ver abaixo |

**Evidência.** `2026_06_26_000300_rls_tenant_completa.php:40-57` e a repetição
em `2026_07_03_000300`. A razão das tabelas de auditoria é concreta e boa: com
RLS FORCE e tenant ativo, o `WITH CHECK` rejeitava o INSERT de `empresa_id` NULL
— quebrando a criação de Empresa com erro 500.

**Por que impede o SaaS.** Duas consequências distintas:

1. **`empresa_configs`** guarda certificado A1, senha de e-mail, CSC da NFC-e —
   os segredos fiscais da revenda. O isolamento depende de o controller lembrar
   de filtrar. É a mesma classe de risco de A-2.1.
2. **`audit_logs`** (confirma A-2.1): sem RLS **e** sem `BelongsToTenant` no
   model. A trilha guarda valores antes/depois em JSON — dado de negócio de cada
   revenda — protegida apenas por `AuditoriaController` lembrar do `where`.

**Direção de correção.** Para as de auditoria, policy que aceite `empresa_id IS
NULL` na escrita e filtre na leitura — resolve o 500 sem abrir mão da barreira.
Para `empresa_configs`, RLS normal (é 1:1 com empresa, não recebe NULL).

---

### A-3.5 — `empresas` visível por grupo: a fronteira do SaaS é o grupo, não a empresa

**Critério:** C6 (escopo de tenant errado) · **Severidade: ALTA**

**O que é.** A tabela `empresas` recebe policy por `grupo_id`
(`rls_empresas_visiveis`), e `User::empresasVisiveis()` retorna as empresas do
grupo. A fronteira dura do sistema é **a rede**, não a revenda.

**Evidência.**
- `2026_08_14_000300_rls_empresas_visiveis.php`
- `app/Models/User.php` — *"a rede é a fronteira dura, um vínculo cruzando redes
  nunca amplia a visão"*
- Produção: 12 empresas em 2 grupos (11 no grupo 2, 1 no grupo 3)

**Por que impede o SaaS.** Esta é a **consequência arquitetural** de D-1, e o
motivo de a decisão comercial bloquear tantos achados. Todo o desenho — RLS,
`empresasVisiveis`, `support`, cadastros por grupo — assume que **um grupo tem um
dono**. Se o SaaS vender para revendas independentes, ou cada uma vira um grupo
próprio (e `grupos` fica redundante com `empresas`), ou a fronteira precisa
descer para a empresa em todas as camadas ao mesmo tempo.

Não é um defeito a corrigir: é a decisão que define o produto.

**Direção de correção.** Ver D-1. Este achado é o que torna a pergunta urgente.

---

### A-3.6 — Duas gerações de policy convivem: 109 por rede, 59 por empresa

**Critério:** C5 (conceitos misturados) · **Severidade: ALTA**

**O que é.** Existem **três** GUCs de tenant, não duas: `app.empresa_id`
(empresa ativa), `app.grupo_id`, e `app.empresas_visiveis` (CSV da rede
visível). A migration `2026_08_14_000300_rls_empresas_visiveis` reescreveu as
policies para aceitar a lista — mas só as tabelas que **já existiam** naquela
data.

**Evidência.** Consulta a `pg_policies` em produção:

| Forma da policy | Tabelas |
|---|---|
| aceita `app.empresas_visiveis` | **109** |
| compara só com `app.empresa_id` | **59** |

As 18 tabelas com `empresa_id` na forma antiga são **todas** posteriores a
14/08: `alertas`, `comodato_avaliacoes`, `comodato_config`,
`comodato_contratos`, `comodato_movimentos`, `cliente_identidades`,
`cliente_revisoes`, `cliente_vinculos`, `alcada_descontos`,
`conta_extrato_regras`, `nf_impostos`, `nf_imposto_estados`,
`pedido_solicitacoes`, `taxas_entrega`, `telefonia_chamadas`,
`telefonia_ligacoes`, `requisicoes_idempotentes`, `monitora_viagens_cache`.

**Por que impede o SaaS.** O comportamento fica **inconsistente por tabela**. Um
usuário de rede com duas empresas visíveis abre uma listagem de clientes e vê as
duas (policy nova); abre a central de alertas ou o extrato de comodato e vê só a
ativa (policy antiga). Não dá erro — só falta dado, silenciosamente. É o mesmo
sintoma que o comentário do `TenantContext` descreve como "custou caro": *"400
mil pedidos sumirem da tela ao selecionar uma filial vazia"*.

E é uma armadilha estrutural para o futuro: **toda tabela nova nasce na forma
antiga**, porque as migrations de domínio copiam o bloco de RLS de umas às
outras. A vigilância de comodato que subiu esta semana já nasceu assim.

**Direção de correção.** Uma função SQL única (`app_tenant_visivel(empresa_id)`)
usada por todas as policies, de modo que mudar a regra seja mudar um lugar. Mais
invariante que falhe quando alguma policy divergir do padrão.

---

### A-3.7 — O global scope filtra por rede; a policy de 59 tabelas, por empresa

**Critério:** C5 (conceitos misturados) · **Severidade: ALTA**

**O que é.** As duas barreiras de isolamento respondem a perguntas diferentes.

**Barreira 1 (aplicação)** — `TenantScope::apply()` filtra por
`empresa_id IN (empresasVisiveis)`:
```php
count($visiveis) === 1
    ? $builder->where($coluna, $visiveis[0])
    : $builder->whereIn($coluna, $visiveis);
```

**Barreira 2 (banco)** — em 59 tabelas, `empresa_id = app.empresa_id`.

**Evidência.** `app/Domain/Tenant/BelongsToTenant.php` (classe `TenantScope`) e
a consulta de policies acima. O `ResolveTenant` seta as três GUCs juntas, e o
comentário dele antecipa o problema: *"As duas precisam andar juntas: se a policy
continuasse comparando só com `app.empresa_id`, o banco barraria exatamente o que
a aplicação passou a liberar — as filiais sumiriam de novo, agora sem erro
visível"*.

**É exatamente o que acontece nas 59.** A intenção está documentada; a execução
ficou pela metade.

**Por que impede o SaaS.** Quando as duas barreiras discordam, a mais restritiva
vence em silêncio. O desenvolvedor lê o código da aplicação, conclui que a
listagem mostra a rede, e o banco entrega menos — sem exceção, sem log. Diagnosticar
isso exige comparar `pg_policies` com o trait, que é onde este achado só apareceu.

**Direção de correção.** Mesma de A-3.6: uma definição única de "empresa
visível", aplicada nas duas camadas.

---

### A-3.8 — `BelongsToTenant` herda `empresa_id` do pai por consulta ao banco

**Critério:** C4 (convenção não declarada) · **Severidade: MÉDIA**

**O que é.** Sem tenant resolvido (ETL, jobs, seeds, testes), o trait descobre o
`empresa_id` consultando a tabela do pai, guiado pelo mapa `$tenantParent`.

**Evidência.** `app/Domain/Tenant/BelongsToTenant.php`, método
`empresaIdDoPai()` — `DB::table($tabelaPai)->where('id', $valorFk)->value('empresa_id')`.

**Por que impede o SaaS.** Três consequências:
1. **Depende de o model declarar `$tenantParent`.** Quem esquecer, cria filha com
   `empresa_id` NULL — invisível a qualquer tenant. É o que o comentário previne,
   mas nada verifica. `SorteioNumero` e `ChecklistPergunta` (A-2.11) não
   declaram.
2. **Uma consulta extra por linha criada** nesse caminho. No ETL, que insere
   centenas de milhares de linhas, é custo real.
3. **Silencioso quando falha:** `return null` e o registro nasce órfão.

**Nota:** o mecanismo é a resposta correta a um problema real (`Event::fake()`
global mata model events e deixa filhas órfãs — armadilha já documentada no
`CLAUDE.md`). A observação é sobre a falta de verificação, não sobre o desenho.

**Direção de correção.** Invariante que falhe se algum model com
`BelongsToTenant` tiver tabela com `empresa_id` NOT NULL e não declarar
`$tenantParent` nem receber o valor por outro caminho.

---

### A-3.9 — 4 dos 5 jobs de negócio rodam sem tenant, e sem tenant a RLS não filtra

**Critério:** C6 (escopo de tenant errado) · **Severidade: ALTA**

**O que é.** `TenantAwareJob` existe exatamente para o problema certo — o
comentário descreve com precisão que jobs rodam fora do ciclo HTTP, o
`ResolveTenant` nunca executa, e **as duas barreiras ficam desligadas**. Mas o
uso é opt-in, e quase ninguém optou.

**Evidência.**

| Job | Usa `TenantAwareJob`? |
|---|---|
| `EnviarPushJob` | sim |
| `GeocodificarClienteJob` | **não** |
| `ImportarLogradourosJob` | **não** |
| `AtribuirPedidoJob` | **não** |
| `NotificarEstoqueBaixoJob` | **não** |
| `ExecutarMigracaoJob` | não (correto — é ferramenta de plataforma, cross-tenant por natureza) |

**Por que impede o SaaS.** Combinado com A-3.1 (sem GUC, a policy libera tudo), um
job sem tenant **enxerga e grava na base inteira**. Os casos concretos:

- `AtribuirPedidoJob` distribui pedidos a entregadores. Sem escopo, a consulta de
  candidatos não está limitada à empresa do pedido — o filtro depende inteiramente
  de o código passar `empresa_id` em cada consulta.
- `NotificarEstoqueBaixoJob` varre saldos; sem tenant, varre os de todas as
  empresas.
- `GeocodificarClienteJob` recebe um cliente por id e o grava — se o id vier
  errado, nada no banco impede escrever em outro tenant.

O próprio comentário do trait enuncia o risco: *"Um job que mira a empresa X pode
ler/gravar dados de outra empresa, ou nascer com empresa_id errado."* Está
escrito, e mesmo assim 4 jobs não o usam.

**Nota de justiça:** não verifiquei se esses jobs filtram por `empresa_id`
explicitamente no código — vários provavelmente filtram, e aí não há vazamento
hoje. O achado é que **a barreira estrutural está desligada** e a proteção
depende de cada consulta lembrar. Os Volumes 8 e 9 vão auditar o corpo desses
jobs.

**Direção de correção.** Inverter o padrão: aplicar tenant por default em todo
job (via `Queue::before`, lendo os ids serializados), com opt-**out** explícito
para os poucos que são de plataforma — como `ExecutarMigracaoJob`.

---

### A-3.10 — `TenantAwareJob` captura empresa e grupo, mas não a rede visível

**Critério:** C5 (conceitos misturados) · **Severidade: MÉDIA**

**O que é.** O trait serializa `tenantEmpresaId` e `tenantGrupoId`. Não serializa
`empresasVisiveis`.

**Evidência.** `app/Domain/Tenant/TenantAwareJob.php` — as duas propriedades
públicas e `capturarTenant()`.

**Por que impede o SaaS.** Um job disparado por usuário que enxerga a rede volta
restrito à empresa ativa. É a mesma divergência de A-3.6/A-3.7, agora na fila: a
requisição vê N empresas, o job derivado dela vê 1. Para `EnviarPushJob` —
o único que usa o trait — o efeito é uma notificação que não alcança quem deveria.

**Direção de correção.** Capturar e reaplicar as três GUCs, não duas.

---

## Cobertura

**Lido integralmente:**
- `2026_06_24_000200_f02_habilitar_rls_postgres.php`
- `2026_06_26_000300_rls_tenant_completa.php`
- `2026_06_26_000400_rls_role_app_sem_bypass.php`
- `2026_07_03_000300_rls_cobertura_tabelas_novas.php`
- `2026_08_14_000300_rls_empresas_visiveis.php`
- `2026_06_24_000100_f02_empresa_id_em_tabelas_filhas.php` (o IDOR encaminhado
  do Volume 1 — 20+ tabelas filhas que herdavam tenant só do pai; **corrigido**,
  todas ganharam `empresa_id` com backfill e índice)

**Verificações no banco de produção:** 9 consultas, leitura, role `erp_app` —
cobertura de policies, FORCE por tabela, atributos de role, membros de role,
dono das tabelas, e **teste funcional de isolamento** com três contextos de
tenant.

**Encaminhamentos dos volumes anteriores, resolvidos aqui:**
- A-2.1 (`audit_logs` sem escopo no model) → **confirmado e agravado**: também
  está fora da RLS, por allowlist. Ver A-3.4.
- "as tabelas por grupo têm policy por `grupo_id`?" → **sim**, a descoberta
  automática aplica policy de grupo onde não há `empresa_id`.

**Lido integralmente (2ª passada — ver nota abaixo):** todo o
`app/Domain/Tenant/` — `BelongsToTenant.php` (com a classe `TenantScope`),
`BelongsToGrupo.php` (com `GrupoScope`), `TenantContext.php`,
`TenantAwareJob.php`, `TenantNotResolvedException.php` — mais
`app/Http/Middleware/ResolveTenant.php` e a verificação de adoção do
`TenantAwareJob` nos 6 jobs `ShouldQueue` do sistema.

**Nota sobre o fechamento deste volume.** A primeira versão foi declarada fechada
com apenas as migrations de RLS lidas, empurrando os traits e o middleware para o
Volume 10 sob a justificativa de serem "código de domínio". Era uma exclusão
indevida: o recorte deste volume é *tenancy e escopo*, e o `TenantContext`, os
global scopes e o `ResolveTenant` **são** o núcleo dele — as migrations são só a
segunda barreira.

A leitura desses 407 + 90 linhas rendeu **5 achados adicionais** (A-3.6 a
A-3.10), entre eles os dois mais graves do volume: a divergência entre as duas
gerações de policy (109 × 59) e os jobs rodando sem tenant. Nenhum deles era
detectável lendo apenas as migrations.

É o **terceiro volume consecutivo** em que a parte não lida continha achado
estruturante.

---

## Resumo

| Critério | Achados |
|---|---|
| C4 — convenção não declarada | 3 (A-3.1, A-3.4, A-3.8) |
| C5 — conceitos misturados | 3 (A-3.6, A-3.7, A-3.10) |
| C6 — escopo de tenant errado | 4 (A-3.2, A-3.3, A-3.5, A-3.9) |

**10 achados · 6 ALTA · 4 MÉDIA · 0 BAIXA.**

### O que este volume mostra

A tenancy é a **camada mais bem construída** do sistema. Descoberta automática de
tabelas, role sem bypass, cast NULL-safe, allowlist com razão escrita para cada
item, teste de cobertura, e correção documentada de um IDOR real. Foi feita por
quem entendeu o problema.

Boa parte dos achados tem uma origem comum, e não é descuido: **o modelo assume
um dono por grupo**. Sob essa premissa, "sem tenant vê tudo" é conveniência para
crons, `support` ver a rede é regra de negócio, `empresas` filtrada por grupo é
o isolamento correto, e a allowlist é pragmatismo justificado.

**Mas quatro achados são de outra natureza — são desvios da própria intenção do
sistema**, e valem independentemente de D-1:

- A-3.6 e A-3.7: a regra de "empresa visível" existe em duas versões
  incompatíveis, e 59 policies ficaram na antiga. O comentário do `ResolveTenant`
  descreve exatamente o defeito que sobrou nelas.
- A-3.9: o trait que resolve tenant em jobs foi escrito, documentado — e 4 dos 5
  jobs de negócio não o usam.
- A-3.3: uma tabela de backup com 277 linhas de dado real, fora de toda proteção.

Estes têm correção definível hoje, sem esperar decisão comercial.

Sob a premissa de revendas independentes, cada uma dessas decisões vira uma via
de vazamento — não por estarem erradas, mas por responderem a outra pergunta.

**Isto torna D-1 a decisão bloqueante do projeto inteiro.** Não é preferência de
modelagem: sem ela, não há como dizer se a RLS atual está certa ou errada.
