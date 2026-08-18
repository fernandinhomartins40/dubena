# AUDITORIA — APLICATIVOS MOBILE

> Base: [app-gas-em-casa/](../../../app-gas-em-casa/) (consumidor, ~80 arquivos) e [app-entregador/](../../../app-entregador/) (~35). Expo 53 / React Native, Expo Router, Zustand, TanStack Query, Firebase (auth/messaging), Laravel Echo/Reverb.

## 1. Arquitetura

- **Expo Router** (rotas por arquivo): consumidor com tabs `home/pedidos/perfil/info` sob `(auth)`; entregador com `(app)/(tabs)` + `pedido/[id]`. Estrutura em camadas (`atoms/molecules/organism/templates` no consumidor).
- **Estado**: Zustand persistido em **MMKV cifrado** — a chave de criptografia é gerada uma vez e guardada no keychain/keystore via `expo-secure-store` ([storage.ts](../../../app-gas-em-casa/src/store/storage.ts)). `skipHydration` até o storage cifrado montar. **Forte.**
- **HTTP**: cliente único ([http.ts](../../../app-gas-em-casa/src/helpers/http.ts)) com baseURL por env, injeção de Bearer, retry com backoff em GET/HEAD, 401→logout, normalização do erro Laravel, desembrulho de `{data}`.

## 2. Integração e comunicação com a API

- Serviços por domínio (`order/product/address/store/user` no consumidor; `auth/entrega/jornada/missao` no entregador) sobre o cliente HTTP.
- **Preço server-side**: o consumidor envia só itens; a cotação/total vêm do ERP ([order.service.ts](../../../app-gas-em-casa/src/services/order.service.ts) → `CotacaoMobileService`). Anti-fraude do valor — resolve um achado crítico do legado.
- **Tenant derivado do token**: nenhum app envia `empresa_id` fora do login; o servidor resolve. Consumidor envia `empresa_id` só no login (multi-revenda por build).

## 3. Segurança

- Tokens Sanctum com **expiração** + rotação (`token/refresh`), storage cifrado, sem token-mestre/`app_key` (eliminados do legado).
- Login do consumidor via Firebase phone-auth (2º fator SMS); entregador via e-mail/senha com lockout+2FA server-side.
- **Achado M-1 (P3)**: o consumidor manuseia dados de cartão (`constants/app.ts` com bandeiras/máscaras) e envia `token` de cartão ao ERP (`/pagar`); confirmar que a tokenização é do gateway (o app não deve tocar PAN cru) — o payload `{token}` sugere tokenização, mas a UI de cartão precisa de revisão PCI.

## 4. Sincronização, cache e tempo real

- **TanStack Query** para cache/refetch; hooks `useRefetchOnAppFocus`, `useAcompanharPedido`.
- **Tempo real** via Echo/Reverb com **fallback para polling** se Reverb não configurado ([realtime.ts](../../../app-gas-em-casa/src/helpers/realtime.ts)) — resiliente.
- **Rastreamento do entregador** ([useRastreamento.ts](../../../app-entregador/src/hooks/useRastreamento.ts)): tenta background (task-manager + foreground service) e cai para foreground; nada crasha se a permissão "sempre" for negada. Bom.

## 5. UX / navegação

- Consumidor: tutorial, home com catálogo, carrinho, checkout (PIX/cartão), acompanhamento com mapa (posição ao vivo + traçado). Rico.
- Entregador: jornada (iniciar/encerrar), dashboard, rota, ciclo de entrega (aceitar/recusar/ocorrência/concluir com comprovação), missões de campo. Espelha a estrutura do consumidor (paridade — objetivo do projeto).

## 6. Achados classificados

| ID | Prio | Achado | Evidência | Recomendação |
|---|---|---|---|---|
| M-2 | **P2** | **Duplicação de infra** entre os apps: `http.ts`, `realtime.ts`, `storage.ts`, stores quase idênticos | comparar `app-gas-em-casa/src/helpers` × `app-entregador/src/helpers` | Extrair um pacote compartilhado (monorepo/workspace) — correção de bug hoje é 2× |
| M-1 | **P3** | UI de cartão no consumidor; revisar aderência PCI/tokenização | [constants/app.ts](../../../app-gas-em-casa/src/constants/app.ts), `/pagar` | Garantir que só o token do gateway trafega; nunca persistir PAN |
| M-3 | **P3** | Tempo real inerte sem Reverb em produção (cai para polling) | [realtime.ts](../../../app-gas-em-casa/src/helpers/realtime.ts) + ausência de Reverb no deploy | Subir Reverb (ver Ops) para a experiência "mapa ao vivo" prometida |
| M-4 | **P3** | Fallback de `cliente_id` ainda aceito no backend beneficia apps antigos | `clienteDoUsuario` no ERP | Forçar update de app + remover fallback (S-6) |
| M-5 | **P4** | Sem testes automatizados nos apps | ausência de suíte | Adicionar testes de serviço (mock do http) e de store |
| M-6 | **P4** | `DEFAULT_LOCATION` hardcoded (Guarapuava) | [constants/app.ts](../../../app-gas-em-casa/src/constants/app.ts) | Derivar da revenda/tenant |

## 7. Preparação para produção

- Builds já geram APKs ([apks/](../../../apks/)); receitas de build documentadas (VPS Docker e local Windows).
- Dependem de: Reverb ligado (M-3), Firebase Admin/verifier real no ERP (login do cliente), gateway de pagamento real (M-1).

## 8. Conclusão

Apps **bem construídos e seguros no essencial** (storage cifrado, tokens com expiração, preço server-side, rastreamento resiliente). O maior débito técnico é **duplicação de infra entre os dois apps** (M-2) — candidato a monorepo. Para a experiência completa em produção, subir Reverb e fechar o gateway de pagamento.

→ Plano: [PLANO_MOBILE.md](PLANO_MOBILE.md)
