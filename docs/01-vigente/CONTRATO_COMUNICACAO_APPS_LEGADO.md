# Contrato de comunicação — como os apps falam com o `ctrl-web`

O `erp-novo` nasceu de uma refatoração do `ctrl-web`, mas os dois apps de campo
continuam falando com o legado. Este documento mapeia **o contrato atual** e onde
o ERP novo diverge — para que o acoplamento não quebre nem a regra de negócio nem
a aplicação em campo.

Fonte: código de `ctrl-web/`, `legado-movelapp/`, `legado-nfweb/`,
`app-entregador/` e `erp-novo/`.

---

## 1. Envelope de resposta — a divergência que quebra tudo

`ctrl-web/app/Helpers/customHelper.php:1648-1685` define **três** envelopes, e os
três respondem **HTTP 200**:

| Helper | Corpo | HTTP | Significado |
|---|---|---|---|
| `responseSuccess` | `{data, msg, status:"OK"}` | 200 | sucesso |
| `responseError` | `{msg, status:"NOK"}` | **200** | erro técnico |
| `responseReject` | `{msg, status:"OPS"}` | **200** | **rejeição de regra de negócio** |

O cliente confirma a leitura (`legado-nfweb/src/helper/Http.js:164`):

    if (responseHttp.status === 'OK' || responseHttp.status === 'OPS') {
      ...  // OPS é tratado como resposta válida, com rejeição
    } else {
      reject(treatNOKResponse(responseHttp));
    }

O `erp-novo` faz o **oposto**: `{data: ...}` em sucesso e HTTP **4xx** com
`{message}` em erro (`AppAuthController:59,91`; `AppPerfilController:183`).

### Por que isso importa

`OPS` não tem equivalente no ERP novo. É o canal pelo qual o legado devolve
**recusa de regra de negócio** — *"Não há limite o suficiente no convênio"*,
*"Produto X não disponível para convênio"* (`MobileAppProcessor`, código 102).

Um app apontado para o ERP novo receberia **HTTP 422** onde espera **200 + OPS**,
e trataria uma recusa legítima como falha de rede. **Distinguir "erro" de "recusa
de negócio" é requisito, não detalhe de formato.**

---

## 2. Autenticação — três esquemas diferentes

| | ctrl-web | erp-novo |
|---|---|---|
| Driver | **Passport (OAuth2)** — `config/auth.php:45` | **Sanctum** |
| Credencial do app | `app_key` → `getToken()` devolve `access_token` | e-mail/senha → token pessoal |
| Papel | implícito (usuário/colaborador) | **ability no token** (`role:entregador`) |
| Transporte | `Authorization: Bearer` **e** `token` no corpo | apenas header |

O MovelApp manda o token nos **dois lugares** ao mesmo tempo
(`Utils.java:187,197`) — header `Bearer` e campo `token` do formulário.

### 2.1 O `app_key` compartilhado

`NfwebController::getToken:85` valida um segredo global (`APP_TOKEN_KEY`) com
`hash_equals`. O comentário no próprio código registra que a chave anterior
**vazou no repositório do app** e por isso foi trocada.

É autenticação **de aplicativo**, não de usuário — qualquer instalação com a
chave obtém token.

---

## 3. Transporte — form-urlencoded, tudo POST

`legado-movelapp/.../Utils.java:196-205`:

    String type = "application/x-www-form-urlencoded; charset=utf-8";
    httpURLConnection.setRequestMethod("POST");

**Todas** as chamadas do MovelApp são POST com formulário, mesmo as de leitura
(`getPedidosPendentes`, `getVeiculos`). O NFWEB mistura: `Route::get` para
consulta e `Route::post` para escrita (`routes/api.php:174-192`).

O `erp-novo` usa JSON e verbos REST (`GET`/`POST`/`PUT`/`DELETE`).

---

## 4. Tenant — a diferença mais delicada

### 4.1 No legado: o app **informa** a empresa

`Utils.java:189` envia `revenda_id` em toda requisição. E o servidor confia:

`ctrl-web/app/Http/Controllers/ApiController.php:34,71,796`

    $empresa = Empresa::find($data['revenda_id']);

**Sem verificar se o token pertence àquela empresa.** Trocar o `revenda_id`
alcança dados de outra revenda — IDOR de tenant.

### 4.2 No ERP novo: o servidor **deriva** do token

`Middleware/ResolveTenant.php:31` — `$empresaId = (int) $user->empresa_id`, com
RLS no Postgres como segunda barreira. E o `app-entregador` documenta a decisão
(`src/services/auth.service.ts:8`):

    O tenant (empresa) é derivado do token no servidor;
    o app nunca envia empresa_id no login do entregador.

**Consequência para a migração:** um app legado apontado para o ERP novo teria o
`revenda_id` **ignorado**. Se o usuário estiver na empresa certa, funciona por
acidente; se o app dependia de trocar de revenda, quebra. É preciso confirmar se
alguma instalação usa mais de uma revenda no mesmo aparelho.

---

## 5. Identidade do dispositivo — `androidid` é o eixo de tudo

O MovelApp não se identifica só pelo usuário. `Utils.java:188` envia o
`Settings.Secure.ANDROID_ID`, e o servidor o usa como chave.

### 5.1 Registro (`ApiController::setAndroidRegistration:31`)

Cria um `Android` com `empresa_id`, `grupo_id`, `androidid`, `user_id`,
`colaborador_id` e — o decisivo — **`setor_id`**, tirado do primeiro setor do
colaborador.

### 5.2 O setor decide a rota (`ApiController::getPedidosPendentes:323`)

    $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
    ...
    $situacoes = Pedidosituacao::where('entregapendente', true)->pluck('id');
    $condicoes[] = ['empresa_id', $android->empresa_id];
    $condicoes[] = ['entregasetor_id', $android->setor_id];
    if (!$config->androidenviatodos) {
        $condicoes[] = ['colaborador_id', $android->colaborador_id];
    }

**A rota do entregador é definida pelo dispositivo, não pelo login.** Três regras
embutidas:

1. Só situações com `entregapendente = true`;
2. Filtro por **empresa + setor do aparelho**;
3. `empresaconfigs.androidenviatodos` decide se o entregador vê **todo o setor**
   ou **só os pedidos dele**. É um flag de configuração por empresa.

E há um portão acima de tudo: `if ($config->androidutiliza)` — se a empresa não
tem o app habilitado, não devolve nada.

### 5.3 No ERP novo

A rota vem de `entregador/rota` e `entregador/pedidos`, resolvidos por
**usuário + jornada**, não por aparelho. Não há equivalente a `androidid`,
`setor_id` do dispositivo, nem ao flag `androidenviatodos`.

**Isto precisa de decisão:** o modelo "um aparelho = um setor" some. Se a operação
depende dele (aparelho fixo no veículo, trocando de motorista), o ERP novo precisa
de um equivalente — ou a operação muda.

---

## 6. Quadro comparativo

| Aspecto | ctrl-web | erp-novo | Risco |
|---|---|---|---|
| Envelope sucesso | `{data,msg,status:"OK"}` | `{data}` | **alto** — formato diferente |
| Envelope erro | `{msg,status:"NOK"}` HTTP 200 | `{message}` HTTP 4xx | **alto** — app não trata 4xx |
| Recusa de negócio | `status:"OPS"` HTTP 200 | **não existe** | **alto** — perde a distinção |
| Auth | Passport + app_key global | Sanctum + token por usuário | médio — reautenticar |
| Papel | implícito | ability no token | médio |
| Transporte | form-urlencoded, tudo POST | JSON, REST | médio |
| Tenant | `revenda_id` do app | derivado do token | **alto** se usa multi-revenda |
| Rota do entregador | por `androidid` → setor | por usuário + jornada | **alto** — modelo diferente |
| `androidenviatodos` | flag por empresa | não existe | médio |
| Situação do pedido | 9 flags | 3 efeitos | **alto** — ver REGRAS §5.1 |

---

## 7. Como acoplar sem quebrar

Três estratégias, da mais conservadora à mais limpa:

### 7.1 Camada de compatibilidade (recomendada para transição)

Um grupo de rotas no ERP novo que **fala o dialeto do legado**: envelope
`{data,msg,status}`, HTTP 200 em erro, `OPS` para recusa de negócio, aceita
form-urlencoded e `revenda_id`.

- **Vantagem:** os apps legados passam a apontar para o ERP novo **sem alteração
  de código nem republicação em loja** — o que importa, já que `targetSdk 28`
  impede publicar o MovelApp na Play Store hoje.
- **Custo:** um adaptador a manter, com data para morrer.
- **Cuidado:** o adaptador **não** deve reintroduzir o IDOR de tenant. Aceita
  `revenda_id` e o **valida** contra o token, em vez de confiar.

### 7.2 Atualizar os apps legados

Republicar MovelApp e NFWEB falando o dialeto novo. Exige resolver o `targetSdk`
do MovelApp e distribuir APK a todo o campo. Mais caro e mais lento.

### 7.3 Substituir pelo app unificado

O alvo do plano. Só depois que o `app-entregador` cobrir impressão e os perfis
novos — ver `PLANO_ACOPLAMENTO_PERFIS_CAMPO.md`.

**Recomendação:** 7.1 como ponte, 7.3 como destino. A 7.2 gasta esforço num app
que será desligado.

---

## 8. O que precisa de decisão antes de codar

1. **Multi-revenda no mesmo aparelho** — alguma instalação troca de `revenda_id`?
   Se sim, derivar tenant do token não basta.
2. **Aparelho fixo por setor** — a operação depende de "um aparelho = um setor"
   (`androidid` → `setor_id`), ou o entregador leva o próprio celular?
3. **`androidenviatodos`** — hoje é por empresa. Vira permissão por perfil no
   modelo novo, ou continua configuração de empresa?
4. **Recusa de negócio** — o ERP novo precisa de um status equivalente ao `OPS`,
   ou a recusa vira 422 com corpo estruturado? Afeta todo app que consumir a API.

---

## 9. O que ainda não foi lido

- `PedidoController::store` do `ctrl-web` — o que acontece **depois** do
  `createOrder` (validações, triggers, geração de financeiro).
- Layouts de impressão (`NotaFiscalImpressao.java`, 2000+ linhas;
  `BoletoImpressao.java`).
- Cálculo de imposto do legado (CST/CFOP por operação).

Os dois últimos importam para a fase de impressão; o primeiro, para conferir a
paridade de criação de pedido.
