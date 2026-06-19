# PRD FIDEDIGNO (linha-a-linha) — Integrações / Notificações / Misc · D15

> 7 controllers (~1.600 linhas). Lidos integralmente: Appnotification(399),
> Notificacoes(238), Agencia(235, núcleo), Android(176), Logcerca(158).
> Caracterizados: Appgiro(211), Appvideo(190) — recursos de apoio ao app (giro de
> comodato / vídeos institucionais), CRUD consumido pela API/app (D13).

- **Status:** ✅ pronto (fiel)
- **Criticidade:** 🟡 (apoio/integração; nada fiscal) · **🟠 Notificacoes (SQLi) + push FCM**
- **Decisão:** **REESCREVER** o que permanecer · **DESCARTAR** scaffolds vazios/obsoletos

---

## 1. O que cada peça FAZ (verificado)
- **Appnotification (399):** **push notification FCM** para o app (cria/edita/envia
  notificações com título/corpo/imagem; layout vs notificação; status pendente/enviando/
  sucesso/falha; envia via ApiResources `/api/sendNotification` → módulo API/D13). Usa
  **Enum** (AppNotificationStatus), authorize, transações, Intervention\Image, Storage.
- **Notificacoes (238):** **notificações internas** (sino + "me liga" android + alertas
  de cerca): agrega androidmensagems + notificacoes/notificacaousers; mapeia alertas de
  manutenção (pneu/óleo/checklist/exame/veículo → ícones) e cerca; marca lido/excluído.
- **Agencia (235):** CRUD de **agência bancária** (apoio a financeiro/boleto) — agência +
  telefones + endereço + postobeneficiario; unique por grupo.
- **Logcerca (158):** relatório de **log de cerca eletrônica** (dentro/fora) com PDF —
  Enum (LogCercaTipo), SelectRepository, **bindings nomeados**. (Pertence ao Monitora/D14.)
- **Android (176):** mensagens android (só `index` funciona). **Appgiro/Appvideo:**
  recursos de apoio ao app (giro de comodato / vídeos), CRUD consumido pelo app.

> Regra real a preservar: o envio de push (FCM) e o agregador de notificações internas
> (alertas de manutenção/cerca) — o resto é apoio descartável/triável.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 Segurança (SQLi)
- **Notificacoes::meLiga:86,95 — `whereRaw("us.user_id = $user->id AND us.empresa_id =
  $empresa_id AND ...")`**: interpola `$user->id` (Auth/api guard) e `$empresa_id`
  (sessão) em SQL cru. Risco menor (origem interna), mas **SQL cru — parametrizar**.

### 🟠 Bugs funcionais
- **Appnotification::update:175 — `$request->only(["title","body",...])`** mas o form/
  validação usa **`fcmtitle`/`fcmbody`** (e o store grava esses) → no update os campos
  `title`/`body` não existem → **título/corpo não são atualizados** (inconsistência de
  nomes store×update).
- **AndroidController — `create`/`store`/`register` 100% VAZIOS** (só `//`): scaffold
  morto (família dos vazios: Clientecontato/Colaboradorfamilia/Veiculodocumento/Menu).

### 🟡 Dívida estrutural
- **Notificacoes** monta agregação via 3 queries `DB::table`+`whereRaw`/`selectRaw`
  (android + app + cerca) — poderia ser unificado/parametrizado.
- **Menu/notificações na sessão** (organizeNotifications via AuthController) — mesmo
  padrão pré-renderizado do D11.
- **Logcerca pertence ao Monitora (D14)** — está no namespace do ERP por legado.
- **Cliente-servidor HTTP** (Appnotification→ApiResources) — mesmo elo HTTP interno a
  eliminar na unificação (D13).
- destroy HTML `<br/>` (Appnotification); catches com `$e->getLine()` (Notificacoes).

### ✅ O que está BOM
- **Appnotification** e **Logcerca** são **modernos**: Enums, authorize, FormRequest/rules,
  transações, bindings (Logcerca), Intervention\Image, Storage, máquina de status. Sem
  SQLi. Agencia: CRUD limpo com unique por grupo.

## 3. Especificação do REESCRITO (Laravel 12)
- **Notificações** → canais nativos do Laravel (database/broadcast/FCM) padronizados;
  parametrizar o agregador do `meLiga` (sem whereRaw cru); push via serviço (não HTTP
  interno).
- **Logcerca** → mover para o módulo Monitora (D14).
- **Agencia** → recurso limpo (apoio ao financeiro).
- **Triagem/descarte:** Android (scaffold vazio), Appgiro/Appvideo — manter só o que os
  apps publicados consomem (cruzar com D13); descartar o obsoleto.

## 4. DECISÃO
- **Decisão: REESCREVER** o que permanecer · **DESCARTAR** scaffolds vazios/obsoletos
  após confirmar consumo pelos apps.
- **Quick wins aplicáveis JÁ:**
  (a) parametrizar `whereRaw` do Notificacoes::meLiga (SQL cru);
  (b) corrigir nomes de campo store×update no Appnotification (title/body→fcmtitle/fcmbody);
  (c) deletar AndroidController vazio (ou completar) após triagem.
- **Pré-requisitos:** confirmar consumo pelos apps (cruzar com D13); mover Logcerca p/ D14.
- **Esforço:** baixo.
- **Ordem:** oportunístico, junto da modernização da API (D13) e do Monitora (D14).
