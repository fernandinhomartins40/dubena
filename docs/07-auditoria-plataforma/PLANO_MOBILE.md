# PLANO DE EVOLUÇÃO — APPS MOBILE

> Corresponde a [AUDITORIA_MOBILE.md](AUDITORIA_MOBILE.md).

## Contexto
Apps sólidos e seguros; maior débito é duplicação de infra entre os dois apps e dependências de produção (Reverb, gateway, Firebase real).

## Objetivo
Unificar a infra compartilhada, garantir o tempo real e fechar a aderência de pagamento.

## Benefícios
Correção única de bugs; experiência "mapa ao vivo" real; conformidade de pagamento.

## Riscos
Extrair pacote compartilhado exige refactor cuidadoso dos imports. Médio.

## Estratégia e fases

**Fase 1 — Pacote compartilhado (M-2)**
- Monorepo/workspace com módulo comum (`http`, `realtime`, `storage`, tipos base); os dois apps consomem.

**Fase 2 — Tempo real e backends (M-3)** (depende de Ops)
- Subir Reverb (ver PLANO_PERFORMANCE); validar `broadcasting/auth` com Bearer nos dois apps.

**Fase 3 — Pagamento (M-1)**
- Revisar a UI de cartão: garantir tokenização pelo gateway, nunca persistir/enviar PAN cru; validar fluxo `/pagar`.

**Fase 4 — Higiene (M-4, M-5, M-6)**
- Remover fallback `cliente_id` (coordenar backend S-6) + forçar update; adicionar testes de serviço/store; `DEFAULT_LOCATION` da revenda.

## Dependências
- Fase 2 depende de Reverb em produção. Fase 4 depende do backend remover o fallback.

## Checklist técnico
- [ ] Pacote compartilhado consumido pelos dois apps
- [ ] Reverb validado nos apps
- [ ] Tokenização de cartão revisada
- [ ] Fallback cliente_id removido + update forçado
- [ ] Testes de serviço/store
- [ ] Localização default por tenant

## Critérios de aceite
- Bug de HTTP corrigido uma vez reflete nos dois apps.
- Acompanhamento mostra posição ao vivo via WebSocket (não polling) em produção.
- Nenhum PAN cru trafega/persiste.

## Estratégia de testes
- Testes unitários dos serviços com http mockado; teste do store cifrado; teste manual do acompanhamento em ambiente com Reverb.
