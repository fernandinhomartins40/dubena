# F1-11 — Recertificação da cobertura RLS (portão deixa de ser cego)

Data: 2026-08-28 (America/Sao_Paulo)

## O que estava errado

O item 2 do gate F1 pedia "recertificar todas as tabelas classificadas e suas
policies, não só os agregados já tratados". Ao medir em PostgreSQL real, a
recertificação não confirmou o esperado — encontrou um buraco.

`saas:f1:pre-cutover-check` verificava se cada tabela COMPANY **possuía a coluna**
`tenant_account_id`. Ter a coluna não prova isolamento: a migration de conversão
(`2026_08_29_000800`) só alcança tabelas COMPANY que também possuem `empresa_id`,
e **pula as demais em silêncio**.

Medição no banco descartável (PostgreSQL 16, cadeia completa de migrations):

| | |
|---|---|
| Tabelas com `tenant_account_id` | 150 |
| Tabelas com policy canônica | 117 |

Das 33 de diferença, 19 são a ponte documental de grupo (convertidas só pelo
comando explícito, por design), 6 são fronteira/plataforma com policy própria,
2 são exceção declarada (`audit_logs`/`login_logs`) e 1 é adiada para F6
(`config_globais`). **Cinco não tinham dono nenhum na conversão.**

E o portão retornava **exit 0** nesse banco.

## As cinco tabelas

| Tabela | Estado real | Por que escapou |
|---|---|---|
| `sequencias` | **`rls=false`** — sem policy alguma | sem `empresa_id` e sem `grupo_id` |
| `produto_operacao_fiscal` | **`rls=false`** — sem policy alguma | pivot, sem coluna de escopo |
| `convenio_fechamento_pedidos` | **`rls=false`** — sem policy alguma | pivot, sem coluna de escopo |
| `transportadoras` | policy legada por `grupo_id` | sem `empresa_id` |
| `malha_fiscal` | policy legada por `grupo_id` | sem `empresa_id` |

### `sequencias` é o caso grave

Ela guarda a numeração fiscal (NF-e/NFC-e) e o nosso-número do CNAB. A empresa
sempre esteve **dentro da string** da chave — `nf:{empresa}:{modelo}:{serie}` e
`boleto:empresa:{id}:banco:...` — nunca numa coluna. É o padrão C4 da auditoria:
convenção não declarada, correção dependendo de todo chamador lembrar de embutir
a empresa numa string livre, com o banco não garantindo nada.

Prova executada com a role de runtime `erp_app` (`rolsuper=false`,
`rolbypassrls=false`), **sem nenhum contexto de tenant**:

```
leitura: nfe:empresa:777:serie:1 = 4210
UPDATE 1
apos update alheio: 999999
```

Leu e sobrescreveu o contador fiscal de outra empresa. Num SaaS isso permite a um
tenant forçar número fiscal repetido ou saltado nos demais — a SEFAZ rejeita e o
erro só aparece na hora de faturar. É responsabilidade fiscal, não só vazamento.

Depois da correção, a mesma role no mesmo banco:

```
0
UPDATE 0
```

## O que foi feito

1. **O portão passou a exigir policy canônica ativa**, não a coluna:
   `companyTablesWithoutCanonicalPolicy()` confere `pg_policies` com
   `app_tenant_can_read*` **e** `relrowsecurity`/`relforcerowsecurity`. Exceções
   declaradas com motivo no código: `audit_logs`, `login_logs`, `config_globais`,
   `grupos` e `empresas` (espinha do tenancy — filtrá-las quebra a resolução,
   mesma justificativa da allowlist da migration `2026_06_26_000300`).
2. **`transportadoras` e `malha_fiscal`** entraram na ponte documental de grupo
   (são configuração group-scoped, mesma forma das 16 já tratadas). Os models
   declararam `tenant_account_id` no `$fillable` — é assim que `BelongsToGrupo`
   passa a preencher a chave a partir do envelope ativo.
3. **Os dois pivots** ganharam proteção por pai escopado por empresa
   (`protectCompanyChildTable`), usando as funções COMPANY. `operacoes_fiscais`
   **não** serve de pai: é PLATFORM e não tem chave de tenant — o pai confiável
   de `produto_operacao_fiscal` é `produtos`.
4. **`sequencias` ganhou `empresa_id` real** (migration `2026_08_29_001600`),
   derivado dos dois únicos formatos de chave que o código produz, mais a policy
   canônica. Chave fora desses formatos fica com `empresa_id` nulo e é negada —
   não se inventa dono.
5. **`NumeroSequencialService` deixou de falhar em silêncio.** Sob RLS o
   `updateOrInsert` casava zero linhas e seguia adiante; agora confere o valor
   gravado e levanta exceção. O `proximo()` também ganhou guarda para a linha
   invisível, que antes daria erro fatal ao acessar `->valor` de `null`.

## Evidência

- Cadeia completa de migrations aplicada do zero em PostgreSQL 16.
- `RlsCoberturaTest` com role `erp_app` e `--fail-on-skipped`:
  **6 testes / 354 assertions, zero skip** (eram 352 — as duas a mais são a
  cobertura nova).
- `saas:tenant:proteger-configuracao-grupo`: preview `ready:true`, apply cobrindo
  **18 tabelas** (eram 16).
- As cinco tabelas conferidas depois do apply: todas `force=true` com
  `tenant_isolation`, cada uma com a função canônica correta para sua forma.
- `saas:f1:pre-cutover-check`: **falha** (exit 1) no banco sem as policies e
  **aprova** (exit 0) depois — agora tendo de fato verificado cobertura.
- Testes focais: `NumeroSequencialTest`, `SaasF1PreCutoverCheckTest`,
  `LegacyGroupConfigurationMigrationTest` — 9 testes / 60 assertions.
- Fiscal/cobrança/CNAB/SPED: 76 testes / 302 assertions.

## Achado registrado, não corrigido

`operacoes_fiscais` está classificada **PLATFORM** mas possui `grupo_id` — uma
coluna por grupo numa tabela declarada da plataforma. Mudar a classe de uma
tabela é decisão de desenho, não conserto; fica para o recorte de classificação.

## O que isto NÃO conclui

Continua pendente do gate F1: a execução em homologação com a role de runtime,
a verificação de jobs/eventos/WebSockets sem envelope, os demais grafos pai-filho
fora dos já protegidos, e o registro de rollback/snapshot de grants. Este
microlote fecha o item 2 (recertificação) e corrige o que ele revelou.

`erp-novo/perda.sql` segue pré-existente e intocado.
