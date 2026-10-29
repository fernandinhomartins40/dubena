# Firebase — login por telefone do app do cliente

**Para quê:** trocar o login de demonstração (que aceita qualquer telefone, sem
SMS) pelo login real do Firebase, e ter um **usuário de teste confirmado** para
demonstrar ao cliente sem gastar SMS.

**Quem faz o quê:** os passos 1 a 4 são feitos por você, no navegador, com a sua
conta Google (só o dono do projeto pode criá-lo). Os passos 5 e 6 são do Claude
Code. A chave privada (passo 4) **nunca** vai pelo chat.

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
