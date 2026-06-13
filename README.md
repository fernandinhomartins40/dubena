# Dubena — Ecossistema "Gás em Casa" / ctrl+ (Modernização)

Monorepo **privado** da modernização do ecossistema, versionado de forma **independente
dos repositórios originais** (não os altera — trabalho feito sobre clones).

## Sistemas

| Pasta | Sistema | Stack (após modernização) |
| --- | --- | --- |
| `ctrl-web/` | ERP (fiscal/financeiro/NF-e/SPED/PIX) + módulo `App\Api` (unificado) | Laravel 5.8 · PHP 7.4 · **PostgreSQL** |
| `api-app-gc/` | API do app (standalone — em vias de aposentar pós-unificação) | Laravel 5.8 · PHP 7.4 · MySQL |
| `monitoramento-veiculos/` | Rastreamento de frota | Laravel 5.8 · MySQL |
| `app-gas-em-casa/` | App mobile (consumidor) | React Native / Expo 53 |

## Documentação do projeto

- `PLANO_MODERNIZACAO_ECOSSISTEMA.md` — plano mestre (fases 0–7).
- `FASES_1_2_EXECUTADAS.md` — segurança + testes de caracterização.
- `FASE_3_EXECUTADA.md` — migração Oracle → PostgreSQL.
- `FASE_4_EXECUTADA.md` — upgrade de framework (Laravel 5.8) + limpezas.
- `FASE_5_EXECUTADA.md` — unificação web + API.
- `*/ANALISE_TECNICA_*.md` — auditoria técnica de cada sistema.
- `*/README.docker.md` — como subir cada sistema em Docker.

## Progresso do roadmap

- ✅ Fase 0 — Dockerização (staging)
- ✅ Fase 1 — Blindagem de segurança
- ✅ Fase 2 — Testes de caracterização
- ✅ Fase 3 — Oracle → PostgreSQL
- ✅ Fase 4 — Upgrade de framework + limpezas
- ✅ Fase 5 — Unificação web + API
- ⏳ Fase 6 — Multi-tenant (tenant_id + RLS)
- ⏳ Fase 7 — Virada para produção

## Como rodar (cada sistema)

```bash
cd <sistema>
docker compose up -d --build
docker compose exec app php artisan migrate     # quando aplicável
docker compose exec app vendor/bin/phpunit       # testes
```

## ⚠️ Segredos

Este repositório **não contém segredos reais** (`.gitignore` os exclui). Os segredos
do ambiente original ficam em `SEGREDOS_LOCAIS.md` **apenas localmente** (fora do git) e
**devem ser rotacionados** antes de qualquer ambiente exposto. Os `.env.docker` versionados
contêm apenas valores **fake de desenvolvimento**.
