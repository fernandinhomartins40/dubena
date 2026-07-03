# Auditoria Técnica da Plataforma — Índice

> Engenharia reversa e auditoria técnica do ERP-NOVO (backend + SPA), apps mobile (consumidor + entregador), Monitora e demais módulos. **Fonte da verdade: o código-fonte.** Julho/2026.
> Legado (`ctrl-web`) fora do escopo. Divergências documentação × implementação registradas em cada auditoria.

## Como foi feita
Leitura do código (393 PHP em `app/`, 74 migrations, 167 TS/TSX na SPA, ~115 nos apps), execução da suíte backend (**568 testes / 1859 assertions verdes**, sqlite in-memory) e inspeção do deploy (compose/CI). Cada conclusão tem evidência (arquivo/classe/método).

## Documentos

| Área | Auditoria | Plano |
|---|---|---|
| Arquitetura | [AUDITORIA_ARQUITETURA](AUDITORIA_ARQUITETURA.md) | [PLANO_ARQUITETURA](PLANO_ARQUITETURA.md) |
| Segurança | [AUDITORIA_SEGURANCA](AUDITORIA_SEGURANCA.md) | [PLANO_SEGURANCA](PLANO_SEGURANCA.md) |
| Banco de Dados | [AUDITORIA_BANCO](AUDITORIA_BANCO.md) | [PLANO_BANCO](PLANO_BANCO.md) |
| Multi-Tenancy | [AUDITORIA_MULTI_TENANT](AUDITORIA_MULTI_TENANT.md) | [PLANO_MULTI_TENANT](PLANO_MULTI_TENANT.md) |
| Backend | [AUDITORIA_BACKEND](AUDITORIA_BACKEND.md) | [PLANO_BACKEND](PLANO_BACKEND.md) |
| API | [AUDITORIA_API](AUDITORIA_API.md) | [PLANO_API](PLANO_API.md) |
| Frontend SPA | [AUDITORIA_FRONTEND](AUDITORIA_FRONTEND.md) | [PLANO_FRONTEND](PLANO_FRONTEND.md) |
| Apps Mobile | [AUDITORIA_MOBILE](AUDITORIA_MOBILE.md) | [PLANO_MOBILE](PLANO_MOBILE.md) |
| Performance | [AUDITORIA_PERFORMANCE](AUDITORIA_PERFORMANCE.md) | [PLANO_PERFORMANCE](PLANO_PERFORMANCE.md) |
| Qualidade | [AUDITORIA_QUALIDADE](AUDITORIA_QUALIDADE.md) | [PLANO_QUALIDADE](PLANO_QUALIDADE.md) |

## Veredito geral

Plataforma **madura e bem construída**: monólito modular Laravel 12 com fronteiras de domínio claras, núcleo financeiro/estoque/fiscal robusto (transações, locks, idempotência, invariantes), multi-tenancy à prova de vazamento no desenho (3 barreiras), RBAC+ABAC com field-level, SPA e apps modernos e seguros. Débito técnico concentrado em **formalização de contrato**, **duplicação** e, sobretudo, **infra de execução em produção**.

## Achados críticos consolidados (ordenados por prioridade)

### P1 — resolver antes do go-live
| ID | Achado | Documento |
|---|---|---|
| PF-5/PF-6/PF-8 | Sem **worker de fila**, **scheduler (cron)** e **Reverb** no deploy → jobs, tarefas agendadas e tempo real inertes | [Performance](AUDITORIA_PERFORMANCE.md) |
| MT-1/MT-2 | RLS só protege se o runtime conectar como `erp_app` (sem BYPASSRLS); risco de configuração silenciosa | [Multi-Tenant](AUDITORIA_MULTI_TENANT.md) |
| DB-1 | PK de `role_user` com `empresa_id` nullable → papel global falha no Postgres | [Banco](AUDITORIA_BANCO.md) |
| Q-6 | Suíte roda em **sqlite**; RLS e `ilike` (produção Postgres) não são testados de verdade | [Qualidade](AUDITORIA_QUALIDADE.md) |
| S-1 | Webhook PIX sem assinatura HMAC/mTLS do PSP (bloqueante só para go-live transacional real) | [Segurança](AUDITORIA_SEGURANCA.md) |

### P2 — antes ou logo após a homologação
- **API-1/API-2**: shape de resposta inconsistente + aliases duplicados ([API](AUDITORIA_API.md)).
- **PF-1/PF-2**: matching geoloc/telefone e Kanban O(N) em PHP ([Performance](AUDITORIA_PERFORMANCE.md)).
- **S-2/S-3/S-4**: reescopo de tenant em downloads, validação de MIME, auditoria financeira ([Segurança](AUDITORIA_SEGURANCA.md)).
- **FE-1/FE-3**: Error Boundary + testes de frontend ([Frontend](AUDITORIA_FRONTEND.md)).
- **M-2/Q-1**: duplicação de infra entre os apps mobile ([Mobile](AUDITORIA_MOBILE.md)).
- **MT-3/DB-4**: `NOT NULL` nas filhas com `empresa_id` ([Multi-Tenant](AUDITORIA_MULTI_TENANT.md)/[Banco](AUDITORIA_BANCO.md)).

## Sequência recomendada para a homologação

1. **CI/testes em Postgres** (Q-6) — pré-requisito de tudo (revela DB-1, DB-2, RLS).
2. **Infra de execução** (PF-5/6/8) — worker + cron + Reverb + Redis.
3. **Blindar RLS no go-live** (MT-1/2) via `GoliveCheck`.
4. **Corrigir PK do role_user** (DB-1) + `NOT NULL` nas filhas (MT-3/DB-4).
5. **Assinatura do webhook PIX** (S-1) antes de transacionar com PSP real.
6. Carregar o **dump** via ETL (`etl:run --check`) validando invariantes.
7. Contrato/API/Frontend/Mobile (P2) em seguida.

## Pontos fortes a preservar
- Máquina de estados explícita do pedido; invariantes de estoque/caixa/financeiro.
- Ports & adapters para todo externo (SEFAZ, PSP, Google, Firebase, SGCasa) com gate.
- 3 barreiras de tenant + role restrita + cache namespaced + broadcast por posse.
- ETL com invariantes como portão de cutover.
- Cobertura de testes backend ampla e verde.
