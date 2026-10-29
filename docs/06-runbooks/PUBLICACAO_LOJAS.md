# Publicação nas lojas — Play Store (produção) e App Store

Apps: **Gás em Casa** (`br.com.dubena.gasemcasa`, cliente) e **Gás em Casa
Entregador** (`br.com.dubena.gasemcasa.entregador`). Ambos falam com o ERP novo
publicado na VPS: `https://gasemcasa.com/novo/api/`, tempo real em
`wss://gasemcasa.com/reverb/`.

---

## O que já está pronto (29/10/2026)

| Item | Onde |
|---|---|
| AAB de produção assinados com a chave de upload | `apks/gasemcasa-producao.aab`, `apks/gasemcasa-entregador-producao.aab` |
| Chave de upload da Play (RSA 4096, válida até 2054) | `C:\Users\fusea\.dubena-play\` — **faça backup da pasta inteira** |
| Script de build reproduzível | `sh scripts/build-aab-producao.sh cliente\|entregador\|ambos` |
| targetSdk/compileSdk 36 | exigência da Play para app novo desde 31/08/2026 |
| Firebase `dubena-aafd1`: apps Android + iOS, SHA de debug e de upload, telefone ligado, SMS liberado p/ BR, Play Integrity ligada | console do Firebase |
| Chave do Google Maps (restrita às APIs Maps Android/iOS, Places, Geocoding) | `C:\Users\fusea\.dubena-play\build.env` |
| Configuração iOS: manifesto de privacidade, criptografia isenta, URL scheme do SMS, push em background | `app.json` dos dois apps |
| Divulgação em destaque da localização em 2º plano (entregador) | `app-entregador/src/helpers/backgroundLocation.ts` |
| Páginas `/privacidade` e `/excluir-conta` | `erp-novo` — entram no ar com o próximo deploy + env abaixo |

**Assinatura:** o plugin `mobile-shared/plugins/withUploadSigning.js` faz o
`bundleRelease` ABORTAR sem a chave de upload. Não existe caminho para sair AAB
assinado com a chave de debug.

**Perder a chave de upload não é o fim** (a Play App Signing guarda a chave
definitiva e o suporte da Play troca a de upload), mas custa dias. Faça o backup.

---

## Pendências antes de enviar

1. **Deploy do erp-novo** (push na `main`) — leva as páginas públicas.
2. **Env das páginas públicas** na VPS (`/opt/dubena-env/erp-novo-homolog.env`),
   seguido de recriar os contêineres e `docker restart erpnovo-web`:

   ```
   PUBLICO_APP_NOME="Gás em Casa"
   PUBLICO_RAZAO_SOCIAL="Distribuidora Dubena Ltda"
   PUBLICO_CNPJ="04.190.715/0001-05"
   PUBLICO_ENDERECO="Rodovia PR-466, Guarapuava - PR, CEP 85050-290"
   PUBLICO_EMAIL="<e-mail de privacidade>"
   PUBLICO_TELEFONE="<telefone>"
   PUBLICO_WHATSAPP="<whatsapp>"
   ```

   Sem `PUBLICO_RAZAO_SOCIAL` as páginas respondem 404 (de propósito).
3. **Cliente de teste para a revisão:** cadastrar na empresa 2 um cliente com o
   telefone `(42) 99999-0001` (número de teste do Firebase, código `123456`).
4. **Entregador de teste para a revisão:** um colaborador com perfil de entregador
   e e-mail/senha só para os revisores.

---

## Play Console (conta de organização — vai direto para produção)

Conta de organização **não** tem a exigência de teste fechado com 12 testadores
por 14 dias (essa regra vale para contas pessoais criadas depois de nov/2023).

Para cada app:

1. **Criar app** → nome, idioma pt-BR, App, Gratuito.
2. **Configuração do app** (painel):
   - Política de privacidade: `https://gasemcasa.com/novo/privacidade`
   - Acesso ao app: "Todo ou parte restrito" → informe o telefone `+55 42 99999-0001`
     e código `123456` (cliente) ou o e-mail/senha de teste (entregador).
   - Anúncios: **não**.
   - Classificação de conteúdo: questionário (sem conteúdo sensível → Livre).
   - Público-alvo: **18+**.
   - Exclusão de dados — URL: `https://gasemcasa.com/novo/excluir-conta`
     (só o app do cliente cria conta; o do entregador usa conta fornecida pela empresa).
   - Segurança dos dados (ver tabela abaixo).
   - App governamental / financeiro / saúde: não.
3. **Somente entregador — Permissões:**
   - *Localização em segundo plano*: descreva "rastreamento da entrega em tempo real
     enquanto o entregador está em jornada" e envie um **vídeo curto** (gravação de
     tela): login → iniciar jornada → diálogo "Localização durante a jornada" →
     permitir "o tempo todo" → notificação "Em serviço" → app em segundo plano.
   - *Serviço em primeiro plano — localização*: mesma justificativa e o mesmo vídeo.
4. **Produção → Criar versão** → aceite a **Assinatura de apps do Google Play**
   (a Play gera a chave definitiva) → envie o `.aab` → notas da versão → revisar → lançar.
5. **Depois do primeiro envio — obrigatório para o SMS funcionar:**
   Configuração → **Integridade do app** → *Certificado da chave de assinatura do app* →
   copie **SHA-1 e SHA-256** e cadastre no Firebase (ou passe ao Claude Code, que
   cadastra por `firebase apps:android:sha:create`). O app instalado pela loja é
   assinado com ESSA chave; sem a impressão dela o login por SMS falha.

### Segurança dos dados (respostas)

| Dado | Cliente | Entregador | Finalidade |
|---|---|---|---|
| Nome | coletado | coletado | funcionalidade, conta |
| Telefone | coletado | — | conta, funcionalidade |
| E-mail | opcional | coletado | conta |
| Endereço | coletado | — | funcionalidade (entrega) |
| Localização precisa | coletada (só em uso) | coletada (inclusive em 2º plano) | funcionalidade |
| Histórico de compras | coletado | — | funcionalidade |
| Fotos | — | coletadas (comprovante) | funcionalidade |
| ID do dispositivo / push | coletado | coletado | funcionalidade |

Criptografado em trânsito: **sim**. Usuário pode pedir exclusão: **sim** (cliente).
Compartilhamento com terceiros para publicidade: **não**.

---

## App Store (depois de comprar a conta Apple Developer)

Do Windows, o build iOS sai pelo **EAS Build** (nuvem da Expo, com Mac). Grátis
até o limite mensal do plano Free.

1. Crie a conta em <https://expo.dev> e, em cada pasta de app:
   ```
   npx eas-cli@latest login
   npx eas-cli@latest init            # grava o projectId no app.json
   npx eas-cli@latest env:create --environment production --name GOOGLE_MAPS_API_KEY --value <chave do build.env> --visibility plaintext
   npx eas-cli@latest env:create --environment production --name REVERB_APP_KEY --value <chave do build.env> --visibility plaintext
   ```
2. **App Store Connect** → Meus apps → "+" → bundle `br.com.dubena.gasemcasa`
   (e `.entregador`). SKU livre.
3. Build: `npx eas-cli@latest build -p ios --profile production` — faça login com o
   Apple ID quando pedir; o EAS cria certificado, perfil e a **chave de push (APNs)**.
4. **Firebase → Configurações → Cloud Messaging → app iOS → Chave de autenticação
   APNs:** envie o `.p8` (o EAS mostra/baixa em `eas credentials`). Sem ela o SMS no
   iPhone cai sempre no reCAPTCHA e o push do cliente não chega.
5. Envio: `npx eas-cli@latest submit -p ios --profile production`.
6. No App Store Connect: política de privacidade (mesma URL), "Privacidade do app"
   (mesmos dados da tabela acima; nenhum para rastreamento), classificação 4+ ou 17+
   conforme questionário, e em **Informações para revisão** o telefone/código de teste
   ou o login do entregador. A exclusão de conta exigida pela Apple (5.1.1(v)) está em
   Perfil → Excluir conta.

O Android também pode ser gerado pelo EAS (`eas build -p android --profile production`),
mas aí o EAS precisa da MESMA chave de upload: `eas credentials` → Android →
"Upload keystore" → envie o `upload-keystore.jks` com alias `dubena-upload`.

---

## Versões

`appVersionSource: remote` + `autoIncrement` nos builds EAS. No build local, o
`versionCode` vem do `app.json` (cliente 67, entregador 1): **cada envio à Play
precisa de um versionCode maior** — suba no `app.json` antes de rodar o script.
