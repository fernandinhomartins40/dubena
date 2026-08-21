# 01 — VIGENTE (guia a implementação atual)

Plano e PRDs ATUAIS da modernização (SPA React + Vite + Laravel API/Sanctum).

- **PLANO_SPA_REACT.md** — plano macro/ADR (fases S1–S8); exige spec/paridade + reorganização por módulo.
- **PLANO_IMPLEMENTACAO_SPA.md** — plano OPERACIONAL por fases (F1 Produto … F10 Limpeza): entregáveis,
  dependências, gate e checklist de DoD por módulo. **É o passo-a-passo de execução** (confere o IMPL no momento).
- **MAPA_NAVEGACAO_ALVO.md** — contrato de navegação: reagrupa telas dispersas do legado em páginas
  completas, com de-para (nenhuma função eliminada).
- **IMPL_00_INDICE.md** — índice dos PRDs de implementação (auditados do código), na ordem de implementação.
- **IMPL_<modulo>.md** — contrato por módulo: colunas, métodos (arquivo:linha), validações, regras,
  sub-recursos, Reorganização/UX (de-para) e Definição de Pronto (DoD).
- **SPEC_CLIENTE.md** — spec do Cliente (1º módulo, já implementado).
- **APPS_DE_CAMPO_E_CENTRAL_DE_VENDAS.md** — os dois apps legados (NFWEB, MovelApp),
  suas regras de negócio e contrato de comunicação extraídos do código, e o plano da
  Central de Vendas (F0–F9). Documento ÚNICO: auditoria e plano juntos, porque separados
  divergiram.
- **openapi-api-admin.yaml** — contrato OpenAPI navegável da API admin (/api/admin), por módulo (F1–F9).

> "Pronto" de um módulo = DoD do seu IMPL 100% coberto (paridade + reorganização + testes).
