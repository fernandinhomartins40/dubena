# Auditoria SaaS — Volume 10: Acesso, Segurança, Tenant, SaaS, Empresa

**Recorte:** `app/Domain/Acesso/`, `app/Domain/Seguranca/`, `app/Domain/Tenant/`,
`app/Domain/Saas/`, `app/Domain/Empresa/` — 18 arquivos, 1.658 linhas.
**Leitura:** 18/18 lidos integralmente (conferido por `wc -l`: 1.658).
**Data:** 2026-08-25.
**Método:** ver [AUDITORIA_SAAS.md](AUDITORIA_SAAS.md). Achados formados só a
partir do código.

---

## Arquivos lidos

| Arquivo | Linhas |
|---|---:|
| Saas/SuperAdminService.php | 245 |
| Acesso/PolicyEvaluator.php | 225 |
| Tenant/TenantContext.php | 152 |
| Saas/LicencaService.php | 130 |
| Tenant/BelongsToTenant.php | 120 |
| Seguranca/Totp.php | 109 |
| Empresa/EnderecoEmpresaSync.php | 86 |
| Saas/CidadeService.php | 74 |
| Acesso/CamposPermitidos.php | 73 |
| Tenant/TenantAwareJob.php | 70 |
| Saas/RecursoCatalogo.php | 65 |
| Saas/AuditoriaPlataforma.php | 57 |
| Seguranca/LoginSeguranca.php | 52 |
| Tenant/BelongsToGrupo.php | 51 |
| Seguranca/PasswordPolicyService.php | 47 |
| Seguranca/VerificadorDoisFatores.php | 45 |
| Seguranca/AuditoriaSeguranca.php | 43 |
| Tenant/TenantNotResolvedException.php | 14 |
| **Total** | **1.658** |

---

## Leitura geral do domínio

Este é o volume mais curto e o mais importante: 1.658 linhas que governam o
isolamento entre todos os dados de todas as revendas. É a fundação sobre a qual os
nove volumes anteriores repousam — e é onde os achados deles convergem.

O que está bem feito é notável. O `TenantContext` distingue **empresa ativa** de
**empresas visíveis** com um comentário que narra o erro que a confusão causou
("400 mil pedidos sumirem da tela"). O `SuperAdminService` se declara o único
ponto autorizado a cruzar tenants e audita toda mutação. O `Totp` é RFC 6238
correto e auto-contido. O `RecursoCatalogo` separa com clareza permissão
(usuário) de recurso (empresa contratou). O `VerificadorDoisFatores` existe
porque um código duplicado byte-a-byte foi unificado.

O problema estrutural é um só, e ele se manifesta em cinco lugares diferentes:

> **A ausência de tenant é tratada como "não filtrar", nunca como "recusar".**

`TenantScope::apply` sem tenant → não filtra. `GrupoScope::apply` sem grupo → não
filtra. A RLS do Postgres sem GUC setada → não restringe (é o que o próprio
`SuperAdminService` documenta como sendo o mecanismo do guard `platform`).
`LicencaService::recursosEfetivos` sem tenant → libera todos os recursos.
`PolicyEvaluator` com condição de tipo desconhecido → permite.

Cada uma dessas escolhas tem justificativa registrada e faz sentido isoladamente,
no contexto de uma instalação com uma revenda migrando de um legado. Somadas, elas
definem que **o estado padrão do sistema, na ausência de contexto, é acesso
irrestrito**. Para um SaaS, o padrão precisa ser o oposto.

O segundo eixo: **o SaaS existe como andaime, não como produto**. `RecursoCatalogo`
tem 10 recursos, `LicencaService` os resolve corretamente, `SuperAdminService` os
administra com auditoria — e o `LicencaService` abre uma exceção explícita que
libera tudo para empresa sem assinatura. Como a memória do projeto registra **zero
assinaturas cadastradas**, o licenciamento inteiro está inerte.

---

## Achados

### A-10.1 (ALTA) — Sem tenant resolvido, o global scope não filtra: fail-open na primeira barreira de isolamento

**Critério:** C6 — escopo de tenant errado / C4.

`BelongsToTenant.php`, `TenantScope::apply()` (`:105-119`):

```php
public function apply(Builder $builder, Model $model): void
{
    $visiveis = $this->context->empresasVisiveis();
    if ($visiveis === []) {
        return;      // ← sem tenant, NENHUM filtro é aplicado
    }
```

O docblock declara a decisão: *"Sem tenant resolvido (CLI/ETL), não filtra — quem
chama é responsável pelo escopo."* `GrupoScope::apply()` (`:44-50`) faz o mesmo
por omissão (só filtra `if ($this->context->grupoId() !== null)`).

Isso significa que **toda consulta Eloquent feita fora de um request HTTP com
tenant resolvido lê a base inteira**, em todas as empresas. E a segunda barreira
não cobre: a RLS do Postgres também não restringe sem a GUC setada — o
`SuperAdminService` documenta exatamente isso como o mecanismo pelo qual o
SuperAdmin enxerga tudo (*"a RLS do Postgres não restringe (sem variável = não
filtra)"*).

As duas barreiras falham abertas na mesma condição. Não há defesa em profundidade
aqui: há uma condição única — "tenant não resolvido" — que desliga as duas.

Os volumes anteriores mostram onde isso é exercitado na prática:

| Onde | Volume | Consequência |
|---|---|---|
| `GeradorMissaoService` (comando agendado, varre empresas) | 8 | consultas por `entregador_user_id` sem empresa |
| `AtribuirPedidoJob` | 8 | resolve tenant explicitamente — **correto** |
| `GeocodificarClienteJob` | 9 | `Cliente::withoutTenant()->find()` deliberado |
| `PixService::processarWebhook` | 7 | `withoutTenant()` deliberado e correto |
| `FinanceiroService::gerarDoPedido` | 7 | busca sem `empresa_id` — depende do scope |
| `CaixaService::movimentar` | 7 | `Conta::withoutTenant()` sem verificação |

O padrão: **quem se lembrou, resolveu; quem não se lembrou, herdou acesso total**.
E não há como o código saber a diferença, porque "sem filtro" é indistinguível de
"filtro que casou tudo".

O `TenantNotResolvedException` existe e é o instrumento certo — mas só é lançado
por `requireEmpresaId()`/`requireGrupoId()`, que os services chamam voluntariamente.
O scope, que roda em toda query, nunca o lança.

**Direção (não implementar agora):** o scope deve ter um modo estrito, ligado por
padrão, que lance quando não houver tenant; os caminhos legítimos sem tenant
(ETL, webhook, cron de plataforma) declaram-se explicitamente com
`withoutTenant()`, que já existe e já é a marca visível dessa intenção.

---

### A-10.2 (ALTA) — `TenantAwareJob` não propaga `app.empresas_visiveis`: o job vê menos do que o request que o originou

**Critério:** C4 / C6.

`TenantAwareJob::aplicarTenant()` (`:56-69`):

```php
app(TenantContext::class)->set($this->tenantEmpresaId, $this->tenantGrupoId);

if (DB::connection()->getDriverName() === 'pgsql') {
    DB::statement('SELECT set_config(?, ?, false), set_config(?, ?, false)', [
        'app.empresa_id', (string) $this->tenantEmpresaId,
        'app.grupo_id',   (string) $this->tenantGrupoId,
    ]);
}
```

Duas omissões, ambas em torno de `empresasVisiveis`:

**(a) `capturarTenant()` não captura as empresas visíveis** (`:47-52`) — guarda só
`empresaId()` e `grupoId()`.

**(b) `aplicarTenant()` não seta `app.empresas_visiveis`**, a terceira GUC que a
RLS usa (as políticas RLS foram descritas nos volumes anteriores como usando
`app.empresa_id`, `app.grupo_id` e `app.empresas_visiveis`).

O `set()` do `TenantContext` com `$visiveis = []` faz `empresasVisiveis()` cair no
default `[$empresaId]`. Consequência: **um job disparado por um usuário de rede
(matriz + filiais) vê apenas a empresa ativa**, enquanto o request que o originou
via a rede inteira.

O comentário do próprio `TenantContext` explica por que isso importa: *"Tratar a
troca de empresa como um interruptor exclusivo faz 400 mil pedidos sumirem da tela
ao selecionar uma filial vazia, e foi exatamente o que aconteceu aqui."* O bug que
foi corrigido no request continua presente no job.

E há um segundo efeito, oposto e pior: se a política RLS lê
`app.empresas_visiveis` e essa GUC **não é setada** (nem limpa) na conexão, ela
pode carregar o valor de um job anterior na mesma conexão de pool do worker —
`set_config(..., false)` é escopo de sessão, não de transação. Um job da empresa A
seguido de um job da empresa B na mesma conexão deixaria B com as visíveis de A.

**Nota:** este segundo efeito depende do texto exato das políticas RLS
(migrations, Volume 1) e do comportamento do pool. Registrado como risco a
verificar no plano, não como fato confirmado.

---

### A-10.3 (ALTA) — Licenciamento inerte: `LicencaService` libera tudo para empresa sem assinatura, e não há assinaturas

**Critério:** C1 — conceito ausente (a estrutura existe e não é alimentada).

`LicencaService::calcular()` (`:83-88`):

```php
$temAlgumaAssinatura = Assinatura::withoutTenant()->where('empresa_id', $empresaId)->exists();
if (! $temAlgumaAssinatura && $overrides->isEmpty()) {
    return RecursoCatalogo::chaves();     // ← TODOS os recursos
}
```

E `recursosEfetivos()` (`:57-62`): sem tenant resolvido, também devolve
`RecursoCatalogo::chaves()`.

O comentário declara a intenção: *"'Fail-open' controlado (…) para não quebrar
instalações existentes ao introduzir o licenciamento. A partir do momento em que a
empresa tem uma assinatura, a licença passa a valer."*

A condição de saída dessa exceção — "a empresa tem uma assinatura" — **nunca foi
satisfeita**: a memória do projeto registra 3 planos, 21 features e **zero
assinaturas**. Portanto:

- Todas as 12 empresas cadastradas têm os 10 recursos ligados.
- O middleware `recurso:` (mencionado no `RecursoCatalogo`) nunca barrou nada.
- O `/me` expõe as 10 features para todo mundo.
- `SuperAdminService::definirOverride` e `definirAssinatura` existem, são
  auditados, invalidam cache — e nunca foram exercitados contra dado real.

Isto é a terceira variante do padrão dominante da auditoria (*estrutura correta
criada e nunca alimentada*), aplicada ao que deveria ser o **modelo de negócio do
SaaS**. Não é um módulo secundário vazio: é a capacidade de cobrar por plano.

Consequência prática para a virada de SaaS: no dia em que a primeira assinatura
for criada, aquela empresa **perde silenciosamente** todos os recursos que não
estiverem no plano — sem migração, sem aviso, sem período de transição. O
fail-open não tem contrapartida de "modo de graça".

---

### A-10.4 (ALTA) — `PolicyEvaluator`: `herda_filhos` documentado e não implementado, e três fail-opens em condições ABAC

**Critério:** C1 / C4.

**(a) Herança hierárquica ausente.** `escopoCobre()` (`:103-107`):

```php
if ($depto !== null) {
    if ($rDepto !== null && (int) $rDepto === (int) $depto) {
        return true;
    }
    // herda_filhos: um recurso de setor abaixo deste depto também é coberto.
    return false;
}
```

O comentário nomeia a regra — *"herda_filhos: um recurso de setor abaixo deste
depto também é coberto"* — e a linha seguinte é `return false`. O docblock do
método também a promete: *"o recurso precisa bater no nó (ou descender dele, se
herda_filhos)"*.

Efeito concreto: um gerente com papel escopado a um **departamento** não enxerga
recursos de **setores dentro** desse departamento — que é a razão de existir da
hierarquia. Um pedido tem `setor_id`, não `departamento_id`, então `$rDepto` é
`null` e a função devolve `false` sempre.

Isso torna o escopo de departamento e de unidade **efetivamente inúteis**: só o
escopo de setor funciona, porque só ele casa com o atributo que os recursos
carregam.

Conecta com o Volume 1, que registrou `unidades`, `departamentos` e `setores_org`
como tabelas **vazias**. A hierarquia não é exercitada nem por dados nem por
código.

**(b) Condição de tipo desconhecido permite.** `avaliarCondicao()` (`:158-168`):

```php
default => true, // tipo desconhecido não bloqueia (forward-compatible)
```

Uma condição ABAC gravada com `tipo` inválido (erro de digitação na tela, dado de
ETL, tipo novo em versão anterior do código) **não restringe nada** — e a tela
mostra a condição como ativa. O operador acredita ter limitado o acesso.

**(c) `avaliarLimite` e `avaliarOwnership` permitem quando o campo falta**
(`:171-193`):

```php
return $valor === null || (float) $valor <= (float) $max;     // limite
return $dono === null || (int) $dono === (int) $userId;       // ownership
```

Uma condição "só pode aprovar até R$ 5.000" não bloqueia um recurso cujo campo
`valor` não esteja carregado (Model sem o atributo selecionado, array parcial). O
comentário do ownership assume boa-fé: *"Sem dono no recurso → não bloqueia (a
condição não se aplica a este recurso)"* — mas quem passa o recurso decide o que
vai dentro dele.

Os três casos seguem a mesma direção de A-10.1: **na dúvida, permitir**.

---

### A-10.5 (MÉDIA) — Lockout de login conta por e-mail e IP sem empresa: uma revenda atrás de NAT bloqueia as outras

**Critério:** C6.

`LoginSeguranca::bloqueado()` (`:23-37`):

```php
$porIp = LoginLog::query()
    ->where('ip', $ip)->where('sucesso', false)
    ->where('criado_em', '>=', $desde)->count();

return $porEmail >= self::MAX_FALHAS || $porIp >= self::MAX_FALHAS;
```

Cinco falhas em quinze minutos, contadas globalmente por IP.

Numa instalação com uma revenda, o IP identifica um atacante. Num SaaS, o IP
identifica **uma rede** — e uma revenda inteira sai por um IP só (NAT do
escritório). Cinco erros de senha de cinco funcionários diferentes na mesma manhã
bloqueiam o login de todos os usuários **daquele IP**, incluindo os de outras
revendas que compartilhem o IP (coworking, mesma operadora com CGNAT).

Pior: `LoginLog` é criado por `registrar()` com `empresa_id` opcional (`:41-52`) —
o dado para escopar existe e não é usado na contagem.

Também não há mecanismo de desbloqueio: o bloqueio expira sozinho em 15 minutos e
não há como um administrador liberar antes, nem visibilidade de que alguém está
bloqueado.

---

### A-10.6 (MÉDIA) — `PasswordPolicy` usa `empresa_id` como chave primária, e a política não se aplica sem tenant

**Critério:** C4.

`PasswordPolicyService::politicaAtiva()` (`:35-46`):

```php
$empresaId = $this->tenant->empresaId();
$pol = $empresaId ? PasswordPolicy::query()->find($empresaId) : null;
```

`find($empresaId)` — a empresa é usada como **chave primária** da política. Isso é
uma convenção não declarada: `PasswordPolicy` não é um cadastro com id próprio, é
uma linha por empresa indexada pelo id dela. Funciona, mas nada no código diz isso,
e qualquer criação de política pela via normal (`create()` com id auto-increment)
produziria uma linha órfã apontando para a empresa errada.

Segundo ponto: sem tenant resolvido, `$pol` é `null` e o default é aplicado
(`min_len` 8, sem complexidade). Um reset de senha por link (fluxo que tipicamente
roda sem tenant, porque o usuário não está autenticado) usaria a política fraca em
vez da política da empresa dele.

Terceiro: `expira_dias` é lido e devolvido no array, mas `regra()` não o usa — não
há nada neste domínio que force troca de senha por expiração. O campo existe,
alimenta a tela, e não tem efeito.

---

### A-10.7 (MÉDIA) — `SuperAdminService` deleta override sem confirmar existência, e `salvarPlano` remove recursos sem auditar o antes

**Critério:** C4.

**(a)** `removerOverride()` (`:176-183`):

```php
RecursoOverride::withoutTenant()
    ->where('empresa_id', $empresaId)->where('recurso_chave', $recursoChave)->delete();

$this->auditoria->registrar('recurso.override.removido', $empresaId, 'recurso_overrides', null, ['recurso' => $recursoChave], null);
```

O `delete()` não verifica se algo existia, e a auditoria registra `entidade_id =
null` e `antes = ['recurso' => …]` — que é o **parâmetro**, não o estado anterior.
A trilha não diz se o override estava ligado ou desligado antes de sumir. Numa
disputa comercial ("por que perdi o recurso X?"), a auditoria não responde.

**(b)** `salvarPlano()` (`:196-206`):

```php
$plano->recursos()->whereNotIn('recurso_chave', $validos ?: ['__none__'])->delete();
```

Remove do plano todos os recursos não listados, e o `registrar()` logo abaixo grava
`antes = null`. Editar um plano para retirar um recurso afeta **todas as empresas
assinantes** daquele plano imediatamente (o cache é invalidado por empresa, mas
`salvarPlano` **não chama `invalidar()` para nenhuma delas** — as assinantes ficam
com o cache antigo por até 5 minutos, depois perdem o recurso).

`definirAssinatura`, `alterarStatusAssinatura` e `definirOverride` invalidam
corretamente. `salvarPlano` não invalida ninguém — e é a operação de maior alcance.

---

### A-10.8 (MÉDIA) — `CamposPermitidos`: campo sem chave no catálogo é livre, e o catálogo é código

**Critério:** C4.

`CamposPermitidos::pode()` (`:63-72`):

```php
if (! array_key_exists($chave, PermissaoCatalogo::GRANULARES)) {
    return true; // campo não controlado
}
```

A convenção é declarada honestamente: *"um campo sensível SÓ é restrito se existir
a chave correspondente no catálogo (…) assim a granularidade entra
incrementalmente"*.

Mas o efeito é que **o controle de campo é fail-open por design**, e a lista do que
é sensível vive em `PermissaoCatalogo::GRANULARES` — uma constante PHP. Isto
significa:

- Uma revenda que considere sensível um campo que a Dubena não considera (custo,
  margem, telefone do cliente) não tem como declará-lo sem alterar o código.
- Um campo novo num model é livre por padrão, mesmo que seja `custo_medio` ou
  `preco_gasdopovo`.
- Não há inventário de quais campos estão efetivamente protegidos por revenda.

O mecanismo é bom e o `filtrarLeitura`/`filtrarEscrita` está correto. O que falta é
o catálogo ser dado, não código — mesmo problema de `PesoTraco` (A-9.2) e das 19
constantes do Volume 8.

---

### A-10.9 (BAIXA) — `AuditoriaSeguranca` depende do tenant ativo para gravar `empresa_id`

**Critério:** C4.

`AuditoriaSeguranca::registrar()` (`:22-35`) grava `SecurityEvent` sem
`empresa_id`, com o comentário: *"empresa_id é preenchido pelo BelongsToTenant a
partir do tenant ativo"*.

Combinado com A-10.1: em contexto sem tenant (job, CLI, e — mais relevante — um
**login falho**, onde o usuário ainda não foi autenticado e o tenant não foi
resolvido), o evento de segurança nasce com `empresa_id` nulo.

Os tipos convencionados incluem `'autorizacao.negada'` — exatamente o evento que
mais importa investigar depois, e que pode ocorrer em requisições onde a resolução
de tenant falhou. O evento fica registrado, mas fora de qualquer empresa: invisível
na trilha de segurança de qualquer revenda.

---

### A-10.10 (BAIXA) — `Totp::verificar` não protege contra reuso do mesmo código

**Critério:** C1.

`Totp::verificar()` (`:44-60`) aceita qualquer código válido dentro de ±1 janela
(90 segundos de tolerância total). Não há registro do último código consumido.

Isso permite que o mesmo código de 6 dígitos seja usado mais de uma vez dentro da
janela — cenário relevante quando o código é interceptado (ombro, phishing em
tempo real, log de proxy). A RFC 6238 recomenda explicitamente rejeitar reuso.

`VerificadorDoisFatores` trata corretamente o caso análogo nos recovery codes
(consome ao usar, `:33-40`). A mesma disciplina não foi aplicada ao TOTP.

É BAIXA porque a janela é curta e a exploração exige interceptação ativa; registro
porque a correção é pequena (guardar o contador do último sucesso em `User2fa`) e o
padrão correto já existe ao lado.

---

## Padrões que este volume confirma

**1. Fail-open é a política implícita do sistema.** Cinco mecanismos independentes
— scope de tenant, scope de grupo, RLS, licença, condições ABAC — todos escolhem
"permitir" quando falta contexto. Cada escolha tem justificativa individual
razoável (compatibilidade com o legado, não quebrar ETL, forward-compatibility).
Nenhuma foi tomada olhando as outras quatro. O resultado agregado é que a ausência
de contexto é um caminho de acesso total, e não há em lugar nenhum um teste que
verifique "sem tenant, uma query não retorna nada".

Contrasta explicitamente com o `CLAUDE.md` do projeto, que estabelece *"fail-closed
em dinheiro e identidade"*. O Volume 7 mostrou o `PixService` seguindo essa regra
com rigor exemplar. O isolamento entre revendas — que é mais fundamental que
qualquer um dos dois — não a segue.

**2. A estrutura do SaaS existe e está desligada.** `RecursoCatalogo` (10
recursos), `LicencaService` (resolução correta com precedência documentada),
`SuperAdminService` (245 linhas de administração auditada), `AuditoriaPlataforma`
(trilha append-only), `CidadePlataforma`, `PlatformAdmin`, guard `platform`. Tudo
construído, coerente, e sem uma única assinatura. É o mesmo padrão de
`estoquefechamentos`, `estoque_inventarios`, `unidades`/`departamentos` — mas
aplicado ao mecanismo de cobrança da plataforma.

**3. O comentário que descreve o que o código não faz.** `herda_filhos` no
`PolicyEvaluator` é o caso mais claro: a linha de comentário explica a regra e a
linha seguinte a nega. Aparece também no Volume 6 (`XmlNfeBuilder` descartando a
tributação resolvida) e no Volume 9 (`GeocodificarClienteJob` exigindo uma coluna
que o domínio declara vazia). Em todos, a intenção foi documentada e a
implementação parou antes.

**4. Configuração como código, terceira ocorrência.** `PermissaoCatalogo::GRANULARES`
(campos sensíveis) junta-se a `PesoTraco` (identidade, Vol. 9) e às 19 constantes
de logística (Vol. 8). O que cada revenda considera sensível, quanto vale um
telefone e a que velocidade se dirige são decisões de negócio compiladas.

---

## Para o plano (Volume 15)

Este volume não gera decisões novas — ele **converge nas que já estão abertas**:

- **D-1** (Volume 3, ainda bloqueante) — se `grupo` pode conter revendas
  independentes, então `empresasVisiveis` cruzando empresas do grupo é vazamento
  entre clientes do SaaS, e A-9.3 (geográfico global) também é. Se `grupo` é a
  rede de um dono, ambos estão certos. **Esta é a decisão que mais achados
  destrava em toda a auditoria.**
- **D-10.1** — Transição do licenciamento: como as 12 empresas existentes ganham
  assinatura sem perder recursos no dia seguinte. Precisa de um plano
  "grandfathered" ou de um período de graça explícito, porque o fail-open atual
  não tem saída suave.
- **D-10.2** — SuperAdmin é papel da plataforma (Anthropic-do-SaaS) ou também da
  revenda-matriz? Muda quem pode suspender empresa e ver dado cross-tenant.

Itens de código para o plano consolidado — nesta ordem de prioridade:

1. **Modo estrito no `TenantScope`/`GrupoScope`**: lançar `TenantNotResolvedException`
   quando não houver tenant, exceto sob `withoutTenant()`/`withoutGrupo()`
   explícito. É a correção de maior alcance de toda a auditoria: fecha, de uma vez,
   a classe de achados A-5.2, A-5.3, A-5.4, A-6.5, A-7.1, A-7.5, A-8.4, A-9.4.
   Resolve A-10.1.
2. **Teste de invariante**: "sem tenant, model tenant-scoped não retorna linha" —
   um teste por model, gerado a partir da lista de models com o trait. É o que
   impede a regressão.
3. **`TenantAwareJob` capturando e aplicando `empresasVisiveis`**, e limpando as
   três GUCs no início do handle. Resolve A-10.2.
4. **`herda_filhos` implementado** (ou removido do docblock e da tela, se a
   hierarquia não for para o SaaS). Resolve A-10.4(a).
5. **Condição ABAC de tipo desconhecido deve NEGAR**, não permitir; `limite` e
   `ownership` devem negar quando o campo exigido não está presente. Resolve
   A-10.4(b)(c).
6. **Lockout escopado por empresa**, com desbloqueio administrativo. Resolve
   A-10.5.
7. **`salvarPlano` invalidando o cache de todas as empresas assinantes** e
   registrando o estado anterior na auditoria. Resolve A-10.7.
8. **Catálogo de campos sensíveis como dado por empresa.** Resolve A-10.8.

---

**Volume 10 fechado.** 18/18 arquivos, 1.658/1.658 linhas. 10 achados
(4 alta, 4 média, 2 baixa).
