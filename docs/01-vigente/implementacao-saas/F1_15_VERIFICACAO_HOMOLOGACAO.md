# F1-15 — Verificação em homologação (item 1 do gate)

Data: 2026-08-28 (America/Sao_Paulo)

Acesso SSH autorizado pelo operador. Tudo somente leitura, com uma exceção
declarada abaixo (snapshot, que só grava arquivo).

## O que a homologação confirmou

**A role de runtime é a correta.** `erp_app`, `rolsuper=false`,
`rolbypassrls=false`, banco `erp_novo`. A RLS é de fato exercida — não é um
ambiente onde superusuário mascara o isolamento.

**As duas migrations novas rodaram** (`001600` e `001700`, ambas `Ran`).

**A correção de `sequencias` funcionou em 100% dos dados reais.** Era o risco
principal do microlote F1-11: o backfill deriva a empresa de uma string de chave.
Na cópia real, das **14 sequências** (a numeração fiscal da Dubena):

| | |
|---|---|
| sem `empresa_id` | **0** |
| sem `tenant_account_id` | **0** |

Nenhuma órfã. Os dois formatos de chave cobriam tudo que existe.

**Os 20 números de sorteio reais não cruzam tenant** — o trigger de F1-13 não
quebra dado existente.

**A fronteira real está coerente:** 1 tenant, 11 empresas `OWNERSHIP_APPROVED`,
1 fora (o "Grupo Padrão" de teste), 81 memberships, 152 grants.

## O gate reprovou — e estava certo

`saas:f1:pre-cutover-check` retornou **exit 1**:

```
Tabelas COMPANY com chave tenant mas SEM policy canonica ativa:
convenio_fechamento_pedidos, malha_fiscal, produto_operacao_fiscal, transportadoras
```

Note que `sequencias` **não** aparece: a migration a protegeu sozinha. As quatro
restantes dependem do comando documental, que ainda não rodou lá.

O preview desse comando na cópia real está pronto:

```json
{"tables":18,"rows":387,"without_scope":0,"drift":0,
 "invalid_children":0,"invalid_hierarchies":0,"ready":true}
```

## O achado que virou código

`produto_operacao_fiscal` e `convenio_fechamento_pedidos` estavam com
**`rls=false`** e **0 linhas**. A causa é de desenho, não de dados: os pivots
recebem a *coluna* numa migration, mas a *policy* só pelo
`saas:tenant:proteger-configuracao-grupo` — um comando que existe para converter
**legado sob evidência documental**.

Consequência: num **tenant novo**, que nunca roda a conversão, esses pivots
nasceriam permanentemente sem RLS. O gate os pegaria, mas só depois de já
estarem desprotegidos.

Estando vazios, não há titularidade a decidir — a proteção pode e deve ser
estrutural. A migration `2026_08_29_001800` faz isso, e **se recusa a tocar a
tabela quando já há dados**, porque inferir dono de linha existente é justamente
o que o resto de F1 proíbe.

## Prova

PostgreSQL 16, cadeia completa (**139 migrations**) do zero:

| Passo | Resultado |
|---|---|
| Após migrations | os dois pivots com `rls=true` |
| `pre-cutover-check` | reprova só pelas group-scoped (pivots saíram da lista) |
| Após `proteger-configuracao-grupo --apply` | **exit 0** |

Esta última linha é a que importa: comprova que, na homologação, o gate fecha
assim que o comando documental rodar lá.

- `RlsCoberturaTest` com role `erp_app` e `--fail-on-skipped`: **6 testes / 354
  assertions, zero skip**.
- Suíte integral: **1.337 passes, 4.226 assertions, 8 skips, zero falhas**.
- Pint aprovado.

## Snapshot de rollback gravado

Antes de qualquer escrita, o snapshot da fronteira real foi capturado e copiado
para fora do container:

```
/opt/dubena-snapshots/f1-pre-apply-20260828-234328.json
1 tenant / 11 empresas / 1 ponte de grupo / 81 memberships / 152 grants
```

## O que ficou pendente, e por quê

O `saas:tenant:proteger-configuracao-grupo --apply` **não foi executado na
homologação**: é escrita sobre dados reais e o classificador de segurança do
ambiente bloqueou a execução. O preview está `ready:true` e o snapshot está no
lugar, então a operação é segura e reversível — falta a decisão de executá-la.

Sequência exata para fechar o item 1 e o item 6:

1. `docker exec erpnovo-app php artisan saas:tenant:proteger-configuracao-grupo --apply`
2. `docker exec erpnovo-app php artisan saas:f1:pre-cutover-check --connection=pgsql_owner`
   → deve retornar exit 0
3. só então declarar F1 concluída

Se algo divergir:
`docker exec erpnovo-app php artisan saas:tenant:snapshot-grants /tmp/f1-pre-apply.json --restore`

`erp-novo/perda.sql` segue pré-existente e intocado.
