# App do Entregador (P7)

App Expo/React Native do **entregador**, consumindo exclusivamente a API do
ERP-NOVO (`app/v1/entregador/*`). Backend único, regras no servidor — o app só
monta requisições e desenha o estado.

## O que faz

- **Login** por e-mail/senha do colaborador (`POST app/v1/login`), com suporte a
  2FA (TOTP) — o tenant (empresa) vem do token Sanctum no servidor.
- **Lista de entregas** atribuídas (`GET app/v1/entregador/pedidos`), com
  toggle **Em serviço** que liga o envio de posição por GPS.
- **Detalhe + mapa** do destino, com atalho de navegação (Google/Apple Maps).
- **Ciclo da entrega**: aceitar / recusar / registrar ocorrência (com foto) /
  concluir com **comprovação** (foto e/ou assinatura no canvas).
- **Rastreamento** (P6): enquanto "em serviço", envia `POST app/v1/entregador/posicao`
  a cada 15s; o servidor publica nos pedidos ativos (tempo real para o cliente).
- **Tempo real** opcional via Laravel Echo/Reverb (`src/helpers/realtime.ts`);
  sem Reverb configurado, cai para polling automaticamente.

## Estrutura

```
src/
  app/                 # rotas (expo-router)
    _layout.tsx        # boot: storage cifrado + hidratação + react-query
    index.tsx          # gate por token
    login.tsx
    (app)/             # grupo autenticado (monta o hook de GPS)
      pedidos.tsx
      pedido/[id].tsx              # detalhe + mapa + ações
      pedido/[id]/ocorrencia.tsx
      pedido/[id]/concluir.tsx     # comprovação (foto/assinatura)
  components/ui.tsx    # primitivas (Botão, Campo, Cartão…)
  constants/app.ts     # config de runtime (expo-constants) + paleta
  helpers/             # http (axios único), realtime (Echo), camera
  hooks/useRastreamento.ts
  services/            # auth.service, entrega.service
  store/               # zustand + storage MMKV cifrado
  types/
```

## Rodar localmente

```bash
cp .env.example .env   # preencha API_URL e GOOGLE_MAPS_API_KEY
npm install
npm run start
```

> **Assets**: `assets/icon.png`, `splash.png`, `favicon.png` e
> `notification-icon.png` são placeholders herdados do app do cliente —
> substitua pela identidade do app do entregador antes de publicar.
>
> **Firebase/push**: para push, adicione `google-services.json` (Android) e
> `GoogleService-Info.plist` (iOS) e registre o device em `app/v1/devices`.
