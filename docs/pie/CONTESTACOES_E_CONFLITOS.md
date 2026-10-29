# Contestações e conflitos — Auditor-Chefe

**Commit auditado:** `48b29f72` · **Data:** 2026-10-29

Os cinco pareceres chegaram de contextos separados (subagentes independentes, mesmo
modelo — não declaro independência de *modelo*, só de contexto). Abaixo, o que o
Auditor-Chefe reverificou **por conta própria** antes de aceitar, e onde um parecer
foi rebaixado, fundido ou corrigido. Nada foi resolvido por votação.

## 1. Achados rebaixados ou corrigidos

| Achado | Alegação do revisor | O que a verificação mostrou | Decisão |
|---|---|---|---|
| **R2-08** (desconto maior que a parcela) | "Recebimento NEGATIVO" no caixa, CONFIRMADO por teste | O teste do R2 exercitou a **tela** (botão "Receber -R$ 50,00"). No backend, `BaixaService::baixar` já recusava `valorEfetivado < 0`, e a exceção desfazia a transação inteira, inclusive o movimento de caixa. **Prova:** com as correções desta auditoria retiradas (`git stash`), o teste `test_r2_08_*` de `PieDinheiroRegressaoTest` **passou**. Era o único dos 10 que passava sem a correção. | Rebaixado de MÉDIA (dado) para **BAIXA (UX)**: o dinheiro nunca saiu; a mensagem de erro era genérica. Mantive a validação explícita no `CaixaService`, que dá uma mensagem clara. |
| **R3-16** (`tenant_staging_artifacts` sem RLS) | BAIXA, CONFIRMADO | A tabela está classificada como `STAGING` em `config/saas_table_classification.php:102`, de propósito, e só comandos de console a usam. | **Risco aceito**, não defeito. Fica no registro para o dia em que algo HTTP a ler. |
| **R4-01** (typecheck dos apps "não roda") | ALTA | Confirmado (TS5098). Ao corrigir, o typecheck revelou **um** erro real (R4-02); o app do entregador saiu limpo. | ALTA mantida pelo **mecanismo** (portão desligado). O dano encontrado atrás dele foi BAIXO. |

## 2. Achados fundidos por causa raiz

| Causa raiz | Pareceres que a viram por ângulos diferentes |
|---|---|
| SSH root por senha, sem fail2ban | R1-05 (segurança) · R5-09 (infra) |
| Licença SaaS desligada (`SAAS_ENFORCE_LICENCA` ausente) | R1-06 (receita) · R4-07 (teste nunca exercido em ambiente real) |
| Pagamento online do app desligado do título e sem idempotência | R3-08 (dinheiro) · R2-02 (PIX para todo pedido) · R2-13 (pedido do app sem idempotência) · R3-15 (chave órfã do `Idempotente`) |
| Fila offline do entregador | R2-03 (chave nova) · R2-04 (401 apaga) · R2-20 (conclusão sem idempotência — HIPÓTESE) |
| Corridas na máquina de estados do pedido | R3-01 (PUT sem lock) · R3-10 (guarda fora do lock) · R3-07 (cancelar com NF viva) |
| Contagem cara sob RLS | R5-04 (medido: `count` de pedidos 3,5 s como `erp_app`) · armadilha já escrita no `CLAUDE.md` e **não** mitigada nas listagens paginadas |

## 3. Conflitos aparentes investigados

**R1 "login por owner está seguro" × R4-09 "fallback do owner é mudo".**
Não se contradizem. O R1 examinou o que a consulta de owner **devolve** (só ids
presos à empresa, cliente relido pelo runtime sob RLS: sem vazamento). O R4
examinou o que acontece quando a conexão de owner **falha**. Li
`app/Domain/Tenant/ConexaoDeOwner.php:41-45`: o `catch (\Throwable)` volta ao
runtime sem log. Isso repetiria o incidente de 13/10 sem deixar rastro. Os dois
procedem; o R4-09 foi corrigido com log de alerta.

**R1 "webhook PIX é sólido" × R3-11 "webhook pós-expiração some".**
Ângulos diferentes. O R1 verificou autenticidade (HMAC por empresa sobre o corpo
cru), vínculo de valor e idempotência. O R3 verificou regra de negócio: um
pagamento real recusado com 422 porque o relógio local já expirou a cobrança. Os
dois procedem. O R3-11 ficou pendente porque exige decisão de estado
(PAGA_APOS_EXPIRACAO) — ver plano.

**CLAUDE.md "20 pulados só rodam no CI" × R4-03 "6 testes só-Postgres não rodam em portão nenhum".**
Verifiquei `composer.json:62`: o filtro do `test:pgsql-rls` não incluía
`AppLoginSobRlsTest` nem `DedupClientesFksTest`. O R4 tinha razão, e o documento
estava errado. Corrigido: com o filtro novo, **26 passaram, 0 pulados** no Postgres
local (execução registrada no relatório de validação).

**R4-04 "DedupClientesFksTest — FALHA REPRODUZIDA".**
A falha era do **teste** (`RefreshDatabase` sob RLS: `must be owner of table
agencias`), não do código de produção. Sem o `RefreshDatabase`, o guardião passa
no Postgres. A descoberta de FKs por `pg_constraint` funciona. Reclassifiquei como
defeito de teste corrigido, não como defeito de dedup.

**CLAUDE.md "119 testes da SPA" × R4 "169".**
Medi 169 antes das correções e 171 depois. O documento está defasado; não há
regressão.

## 4. Achados que reverifiquei antes de corrigir (não aceitos só pelo parecer)

| Achado | Verificação do Auditor-Chefe |
|---|---|
| R1-01 | Li `ClienteAuthService::garantirUsuario` (`firstOrCreate(['email' => $cliente->email])`) e o `AppRole` (só cobre `app/v1`). Usuário e e-mail vêm do cadastro público. Confirmado, e a PoC do R1 passou (token do admin + `/api/admin/clientes` 200). |
| R1-03 | Consultei a homologação, só leitura: as 7 empresas estão `ativo = true` e há 0 usuários ativos em empresa inativa. A trava nova não tira ninguém do ar. |
| R1-04 | Conferi que o contêiner web só é publicado em `127.0.0.1` (`docker-compose.homolog.yml:56`, `docker-compose.producao.yml:79`), condição para confiar nas faixas privadas. |
| R3-01 | A SPA **não** usa `PUT /pedidos/{id}` (só criação e `/situacao`); o único chamador de `atualizar()` é o `PedidoController:143`. Recusar a mudança de situação ali não quebra fluxo de tela. |
| R3-04 | Reparcelar um AGRUPADOR era permitido e pode ser uso legítimo (fechamento de convênio parcelado). Para não mudar regra comercial, recuso só AGRUPADO, REPARCELADO e cancelado. |
| R5-01 | Confirmei na VPS, só leitura, os nomes reais: volumes `erpnovo-homolog_*`, symlink `releases/homolog-current`. O default `erp-novo_app_storage` do script não existia. |

## 5. Desvios declarados pelos revisores

O R5 declarou desvios da regra "VPS somente leitura" do briefing:
- gravou um `.sql` em `/tmp` por `scp` e o apagou;
- rodou um contêiner efêmero `docker run --rm` (só `ls`/`md5sum`);
- deixou um `find /` órfão, que termina sozinho;
- tentou um `pkill`, **negado** pela permissão.

Nenhum alterou dado ou serviço. Mesmo assim, o briefing proibia isso e o registro
fica aqui. O R1 teve uma chamada HTTP externa à homologação **bloqueada** pela
permissão; os achados dele foram provados por PoC local.
