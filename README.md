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

Toda a documentação de planejamento/PRD vive em **[`docs/`](docs/)** (organizada por finalidade).
Comece pelo que está VIGENTE:

- **[docs/01-vigente/](docs/01-vigente/)** — plano e PRDs ATUAIS (SPA React + Laravel API):
  - `PLANO_SPA_REACT.md` — plano vigente (fases S1–S8) + plano de implementação por módulo.
  - `MAPA_NAVEGACAO_ALVO.md` — de-para legado→novo (reorganizar ≠ eliminar).
  - `IMPL_00_INDICE.md` + `IMPL_*.md` — PRDs de implementação por módulo (auditados do código); cada um é o CONTRATO (paridade + DoD).
- `docs/02-auditoria-legado/` — PRDs fiéis (linha-a-linha) do legado (referência).
- `docs/03-modernizacao-filament/` — auditoria da fase Filament (descartada — histórico).
- `docs/04-historico/` — planos antigos supersedidos (histórico).
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
