# Firebase — login por telefone do app do cliente

**Para quê:** trocar o login de demonstração (que aceita qualquer telefone, sem
SMS) pelo login real do Firebase, e ter um **usuário de teste confirmado** para
demonstrar ao cliente sem gastar SMS.

**Quem faz o quê:** os passos 1 a 4 são feitos por você, no navegador, com a sua
conta Google (só o dono do projeto pode criá-lo). Os passos 5 e 6 são do Claude
Code. A chave privada (passo 4) **nunca** vai pelo chat.

---

## Estado em 29/10/2026 — feito pelo Claude Code com o login do dono no CLI

Projeto usado: **`dubena-aafd1`** (número 93648776647, conta `fuseagencia10@gmail.com`).
Os passos 1 a 5 abaixo foram executados pelo `firebase-tools` e pelas APIs
administrativas do Google, sem o console:

| Passo | Como | Conferido |
|---|---|---|
| Apps Android `br.com.dubena.gasemcasa` e `.entregador` | `firebase apps:create` | `apps:list` |
| SHA-1 e SHA-256 da chave de debug nos dois | `apps:android:sha:create` | `apps:android:sha:list` |
| `google-services.json` real | `apps:sdkconfig` → `app-gas-em-casa/` | `project_id` = `dubena-aafd1` |
| Telefone ligado + número de teste `+5542999990001`/`123456` | `PATCH identitytoolkit admin/v2 .../config` | login do número de teste devolve `idToken` |
| SMS liberado para a região BR | `smsRegionConfig.allowlistOnly = [BR]` | sem isso: `OPERATION_NOT_ALLOWED ... region` |
| Chave da service account `firebase-adminsdk-fbsvc` | API IAM (habilitada no projeto) → canalizada direto para a VPS | `/opt/dubena-env/firebase-service-account.json`, 600 |
| Homologação com `kreait` + `FCM v1` | env + recriação + restart do `erpnovo-web` | token `fake:` → **401**; token real → passa da verificação (422 só porque o telefone não é cliente da empresa 2) |

A Web API key do projeto (`AIzaSy...`) **não** dá permissão administrativa —
identifica o projeto e vai dentro do APK. As operações acima exigiram o login
OAuth do dono (`firebase login`).

Para a demonstração falta só **um cliente da empresa 2 com o telefone
`(42) 99999-0001`** (cadastro no painel ou pelo próprio app) e instalar o APK
gerado com `APP_DEBUG=false`.

---

## 1. Criar o projeto (5 min)

1. Abra <https://console.firebase.google.com> com a sua conta Google.
2. **Criar um projeto** → nome: `gasemcasa` (o ID final aparece embaixo do nome,
   algo como `gasemcasa-1a2b3`; **anote o ID**).
3. Google Analytics: pode **desativar**, não é usado.

O plano gratuito (Spark) basta para a demonstração com número de teste. SMS
**real** para clientes de verdade exige o plano Blaze (pago por uso) — isso fica
para a produção.

## 2. Registrar o app Android

1. Na página do projeto: ícone do **Android** → "Adicionar app".
2. **Nome do pacote:** `br.com.dubena.gasemcasa` (tem de ser exatamente este — é o app NOVO; o antigo `com.qti.gasemcasa` continua funcionando como está).
3. **Certificado de assinatura SHA-1** (é o do APK de homologação que existe hoje):

   ```
   5E:8F:16:06:2E:A3:CD:2C:4A:0D:54:78:76:BA:A6:F3:8C:AB:F6:25
   ```

4. Depois de registrar: **Configurações do projeto → Seus apps → o app Android →
   Adicionar impressão digital** e cole também o **SHA-256**:

   ```
   FA:C6:17:45:DC:09:03:78:6F:B9:ED:E6:2A:96:2B:39:9F:73:48:F0:BB:6F:89:9B:83:32:66:75:91:03:3B:9C
   ```

   Sem as duas impressões o Android recusa a verificação do telefone.
5. **Baixe o `google-services.json`** e salve em
   `C:\FuseDev\Dubena\app-gas-em-casa\google-services.json`, substituindo o
   provisório. (Ele não é segredo — vai dentro do APK — e o git o ignora.)

> Os SHA acima são da chave de **debug** com que o APK de homologação é assinado
> hoje. Quando houver keystore de produção (Play Store), as impressões dela
> também precisam ser cadastradas aqui.

## 3. Ligar o login por telefone e criar o usuário de teste

1. Menu **Authentication → Começar → Método de login → Telefone → Ativar → Salvar**.
2. Na mesma tela, abra **Números de telefone para teste** e adicione:

   | Telefone | Código |
   |---|---|
   | `+55 42 99999 0001` | `123456` |

   O número de teste **não recebe SMS**: no app você digita o número e o código
   fixo, e o Firebase devolve um login verdadeiro. É o "usuário de teste
   confirmado" para a demonstração. Use um número que **não** seja de ninguém;
   para mostrar o cliente já cadastrado, cadastre no painel um cliente com esse
   telefone (ou ponha esse telefone num cliente fictício).

   Em aparelho sem Play Store / APK fora da loja, o Firebase pode abrir uma vez
   uma página do navegador ("verificando que você não é um robô"). É normal.

## 4. Gerar a chave do servidor (service account)

O servidor precisa dela para conferir que o login veio mesmo do Firebase.

1. **Configurações do projeto (engrenagem) → Contas de serviço → Gerar nova chave
   privada → Gerar chave.** Baixa um arquivo `.json`.
2. **Não envie esse arquivo pelo chat nem por e-mail.** Copie direto para a VPS,
   no PowerShell do seu PC (troque o nome do arquivo baixado):

   ```powershell
   scp "$env:USERPROFILE\Downloads\gasemcasa-xxxxx-firebase-adminsdk-xxxxx.json" dubena-vps:/opt/dubena-env/firebase-service-account.json
   ```

3. Avise o Claude Code com: **o ID do projeto** (passo 1) e "chave copiada".

## 5. Servidor (Claude Code)

- Proteger o arquivo (`chmod 600`) e copiá-lo para o volume persistente da
  aplicação, em `/var/www/storage/app/private/firebase/service-account.json` (sobrevive a deploys).
- No env da homologação: `FIREBASE_DRIVER=kreait`, `FIREBASE_PROJECT_ID=<id>`,
  `FIREBASE_CREDENTIALS=/var/www/storage/app/private/firebase/service-account.json`
  e, com a mesma chave, `FCM_DRIVER=v1` (push do app).
- Recriar os contêineres e **reiniciar o `erpnovo-web`** em seguida (sem isso o
  nginx fica com o IP antigo do app e devolve 502 — incidente de 29/10).
- Conferir: login com token falso passa a ser **recusado** (é o bloqueio pedido
  na auditoria) e login com o número de teste passa.

## 6. Novo APK do cliente (Claude Code, ~35 min)

O APK atual manda o token falso porque foi gerado com `APP_DEBUG=true`. O novo é
gerado com o `google-services.json` real e `APP_DEBUG=false`. Até ele ser
instalado, **o login de cliente pelo APK antigo para de funcionar** assim que o
passo 5 for aplicado — por isso os passos 5 e 6 são feitos juntos.

---

**Para cortar tudo depois:** apagar o projeto no console do Firebase (invalida a
chave) e voltar `FIREBASE_DRIVER` para `fake` no env da homologação.
