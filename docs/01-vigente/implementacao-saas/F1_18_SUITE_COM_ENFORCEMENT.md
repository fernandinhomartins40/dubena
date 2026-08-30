# F1-18 — Suíte verde com o enforcement ligado

Data: 2026-08-29 (America/Sao_Paulo)

Fecha a pendência assumida em F1-17: com `SAAS_ENFORCE_TENANT_ENVELOPE=true` a
suíte ia a **566 falhas de 1.346**, e enquanto isso durasse qualquer regressão
nova ficaria escondida no meio das falhas conhecidas.

## Resultado

| Modo | Antes | Depois |
|---|---|---|
| Enforcement ligado | 566 falhas | **1.339 passes, 0 falhas** |
| Enforcement desligado | 1.339 passes | **1.339 passes, 0 falhas** |

A suíte agora cobre **os dois modos**, que é o que devolve a rede de segurança.

## O que a adaptação encontrou (não era só dívida de teste)

A hipótese inicial era "as factories nasceram no modelo legado". Isso era
verdade, mas ao caminhar pelas falhas apareceram **três defeitos reais**, que
teriam ido para produção com o enforcement ligado:

### 1. Fila síncrona derrubava a criação de pedido pelo app

`TenantEnvelopeRuntime::run()` recusa sobrepor envelope no mesmo worker — guarda
correta, existe para não vazar tenant entre jobs. Mas com `QUEUE_CONNECTION=sync`
o job roda **dentro** do request, onde o envelope já está ativo, e a recusa virava
HTTP 403:

```
{"message":"Nao e permitido sobrepor TenantEnvelope no mesmo worker."}
```

Corrigido em `TenantEnvelopeJob::withinTenantEnvelope()`: reusa o envelope
corrente **somente se for o mesmo tenant**. Tenant diferente continua recusado —
ali sobrepor é exatamente o vazamento que a guarda impede.

### 2. Cliente do app tomava 403 depois de logar

`ClienteAuthService` cria o `User` do cliente no primeiro login, e esse usuário
nascia **sem membership**. Com o enforcement, o login funcionava e a requisição
seguinte era negada.

Corrigido na origem: o cliente ganha membership e grant restritos à empresa do
próprio cadastro. Empresa fora da fronteira não ganha membership — ali o 403 é a
resposta certa.

### 3. Job despachado fora de request não podia mais rodar

`capture()` exige usuário autenticado. Service chamado por console, seed ou ETL
nunca teve ator, e passou a estourar. Criado `captureOrNull()`, usado pelos cinco
jobs: sem ator, o job segue sem envelope e cai no caminho legado, que o
enforcement já restringe pela RLS. A barreira real — `requireOperation()` no
`handle()` — continua intacta.

## Ajuste em `IntegracaoTenant`

A guarda de F1-17 negava a chave do Maps quando não havia envelope. Fora de
request isso não protege ninguém de outro tenant (não há tenant a comparar) e só
quebrava trabalho legítimo, jogando o consumo para a key da plataforma. Agora
sem envelope ela permite, e a proteção desse caminho é a **RLS canônica** que
F1-17 instalou em `config_globais`. Com envelope, a comparação vale e usa
`TenantCompany` — a ponte de grupo é opcional e não servia de teste principal.

## As factories

`Database\Factories\Support\FronteiraTenant` monta tenant, vínculo aprovado,
membership e grant. Ligado por `configure()`/`afterCreating`, então **nenhuma
chamada de teste precisou mudar** para o caso comum.

Fica em `database/factories` e não em `tests/`, porque `Tests\` só existe em
`autoload-dev` — referenciá-lo de uma factory quebraria o seed em produção.

Para os testes que exercitam a **ausência** de fronteira existem estados
explícitos: `Empresa::factory()->semFronteiraSaas()` e o equivalente no usuário.
Sem eles, testes como "schema sombra não infere tenant" passariam a encontrar o
que provam não existir.

## Evidência

- Suíte com enforcement **ligado**: 1.339 passes, 4.254 assertions, 8 skips.
- Suíte com enforcement **desligado**: idem.
- 142 migrations do zero em PostgreSQL 16; `RlsCoberturaTest` com role `erp_app`
  e `--fail-on-skipped`: 6 testes / 354 assertions, zero skip.
- Pint aprovado nos arquivos do microlote.

## Observação sobre três testes

`RelatorioTest`, `PushAssincronoTest` e `ImportacaoLogradourosTest` passaram a
fixar `config()->set(...enforcement..., false)`. Eles medem comportamento do modo
legado (retentativa de fila, cron que falha fechado), e sem fixar o modo o
resultado passava a depender da variável de ambiente com que a suíte roda — o
que é pior que a falha, porque esconde a causa.

`erp-novo/perda.sql` segue pré-existente e intocado.
