# PRD — Integrações / Notificações / Misc  ·  D15

- **Status:** ✅ pronto
- **Criticidade:** 🟡 (apoio/integração; nada fiscal)
- **Decisão:** **REESCREVER** (limpo) · avaliar descarte do que for obsoleto

---

## 1. Escopo
- **Controllers:** `Appnotification` (399), `Notificacoes` (238), `Agencia` (235),
  `Appgiro` (211), `Appvideo` (190), `Android` (176), `Logcerca` (158).
- Tabelas: `notificacoes`, `androidmensagems`, `appgiros`, `appvideos`, `agencias`,
  logs de cerca.

## 2. O que o módulo FAZ
- **Notificações**: push/app (FCM), notificações internas, alertas.
- **Appgiro / Appvideo / Android**: recursos de apoio aos apps (giro de comodato,
  vídeos institucionais, mensagens Android).
- **Agencia**: cadastro de agências bancárias (apoio ao financeiro).
- **Logcerca**: log de eventos de cerca eletrônica (apoio ao monitoramento).

## 3. Como FAZ hoje
- CRUDs/serviços de apoio; integração FCM para push.
- `Notificacoes` monta HTML de alerta (similar à gambiarra do menu — verificar).

## 4. Gambiarras / dívida técnica
- [ ] Possível HTML montado em PHP nas notificações (verificar no detalhe).
- [ ] Funções espalhadas; alguns podem ser código pouco usado/obsoleto.
- [ ] Logcerca poderia pertencer ao módulo Monitora (D14).

## 5. Riscos de tocar
- **Baixo.** Nada fiscal/financeiro. Cuidado só com push (não spammar) e com o que
  os apps publicados consomem (Appgiro/Appvideo/Android — verificar contrato).

## 6. Estado de compatibilidade Postgres
- ✅ Validado na varredura (os que têm tela). FCM independe de banco.

## 7. Visão REESCRITA (Laravel 12)
- Notificações via canais do Laravel (database/broadcast/FCM) — padronizado.
- Recursos de apoio aos apps revistos junto da API (D13): manter só o que os apps
  usam; descartar obsoleto.
- Logcerca → mover para o módulo Monitora (D14).

## 8. DECISÃO e justificativa
- **Decisão: REESCREVER** o que permanecer · **DESCARTAR** o obsoleto após
  confirmar que nenhum app/integração usa.
- **Por quê:** apoio de baixo risco; bom para limpar superfície e padronizar
  notificações. Triagem de uso antes de descartar.
- **Pré-requisitos:** confirmar consumo pelos apps (cruzar com D13).
- **Esforço:** baixo.
- **Ordem:** oportunístico; junto da modernização da API/monitora.
