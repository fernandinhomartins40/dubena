# Guia do dono — o que falta para virar o sistema

**Para quem é este documento:** você, sem precisar entender de programação.
**Data:** 2026-08-18
**Estado do sistema:** a construção acabou. Sobrou a mudança.

---

## Parte 1 — Entendendo a situação

### A analogia

Imagine que você tem uma loja funcionando (o sistema antigo, `ctrl-web`) e
construiu uma loja nova do lado (o `erp-novo`). A loja nova está pronta:
prateleiras montadas, caixa instalado, produtos no lugar.

**O que sobrou não é construir. É mudar.**

### O que já está pronto

As 46 tarefas do plano foram implementadas. Tudo que era programação está feito:

- **Segurança** — senhas, tokens, bibliotecas atualizadas
- **Dados** — 44 mil clientes duplicados corrigidos; o dinheiro confere ao
  centavo (R$ 250.029.904,80, igual ao sistema antigo)
- **Infraestrutura** — backup automático rodando, restore testado (169 segundos)
- **Funcionalidades** — tudo que existia no antigo e faltava no novo: boleto,
  DANFE, vale-gás, contrato de comodato, relatórios, malote, bina
- **839 testes automatizados** passando

### Por que ainda não dá para virar a chave

Sobraram tarefas que **não são escrever código**. Elas se dividem em três tipos.

#### Tipo 1 — Chaves que só existem fora do servidor

O sistema precisa de credenciais que **não estão em lugar nenhum do computador**.
Elas vivem em contas suas:

| O que | Onde está | Sem isso... |
|---|---|---|
| **Firebase** | Sua conta Google | O app do cliente não faz login |
| **Certificado A1** | Contador / certificadora | Não emite nota fiscal |
| **PIX** | Seu banco ou PSP | Não cobra por PIX |

É como ter a loja pronta e faltar a chave da porta. Não adianta acesso ao
servidor — a chave está no seu bolso, não lá dentro.

#### Tipo 2 — Coisas que precisam do mundo físico

Imprimir um boleto e passar no leitor do caixa. Os testes provam que o código de
barras segue a especificação técnica; só o leitor de verdade prova que ele **lê**.
Papel e leitor não têm como ser simulados. O mesmo vale para o DANFE.

#### Tipo 3 — O ensaio da mudança

Esta é a parte mais importante de entender. A migração dos dados funciona assim:

1. Fecha o sistema antigo (ninguém mais digita nada)
2. Copia **todos** os dados para o novo — 16 milhões de linhas, **leva horas**
3. Confere se tudo bateu
4. Vira a chave

**Não existe copiar "só o que mudou".** É tudo ou nada, sempre. Por isso o
sistema antigo fica congelado durante a operação inteira.

E aí vem a pergunta que só um ensaio responde: **quantas horas exatamente?**

O roteiro completo existe (`deploy/CUTOVER_RUNBOOK.md`), com os 15 passos, os
pontos de conferência e como desfazer. Mas a coluna "tempo medido" está em
branco — a única forma de preenchê-la é ensaiar com uma cópia real dos dados.

Sem esse número, você não sabe se precisa de 4 horas ou de 12. E se a janela
acabar no meio da cópia, você fica com o antigo fechado e o novo incompleto — o
pior lugar possível.

### O que foi automatizado para reduzir risco

Como a virada acontece de madrugada e sob pressão, três coisas que seriam feitas
à mão viraram comandos:

1. **Conferir se o banco novo está limpo** — eram 5 comandos digitados um a um;
   virou um só
2. **Virar a chave do site** — o comando testa a configuração *antes* de aplicar
   e desfaz sozinho se estiver errada
3. **Trava de segurança** — depois da virada, se alguém rodar a cópia de novo por
   engano, o sistema **recusa**. Ele percebe que já tem dados novos e não deixa
   sobrescrever

---

## Parte 2 — As tarefas, em ordem de execução

Cada tarefa tem: **o que é**, **por que importa**, **como fazer** e **como saber
que deu certo**.

---

### 🔴 BLOCO A — Fazer agora (segurança)

#### A1. Trocar a senha das duas contas de administrador

**O que é.** Duas contas ainda usam a senha `admin1234`:
`admin@gasemcasa.com` e `admin@dubena.com.br`.

**Por que importa.** Essas contas têm acesso total ao sistema — elas ignoram
todas as regras de permissão. Qualquer pessoa que saiba a senha entra e faz
qualquer coisa.

**Por que ainda está assim.** O sistema impede que **novas** contas nasçam com
senha padrão, mas não troca a senha de contas que já existem. Isso é de
propósito: se ele trocasse sozinho, sobrescreveria uma senha que você já tivesse
mudado à mão.

**Como fazer:**

```bash
# 1) entrar no servidor
ssh root@gasemcasa.com

# 2) abrir o console do sistema
docker exec -it erpnovo-app php artisan tinker

# 3) trocar a primeira conta (use uma senha forte, 16+ caracteres)
$u = App\Models\User::where('email','admin@gasemcasa.com')->first();
$u->password = Hash::make('SUA-SENHA-FORTE-AQUI'); $u->save();

# 4) trocar a segunda
$u = App\Models\User::where('email','admin@dubena.com.br')->first();
$u->password = Hash::make('OUTRA-SENHA-FORTE'); $u->save();

# 5) sair
exit
```

**Como saber que deu certo:** ainda no `tinker`, rode:

```php
Hash::check('admin1234', App\Models\User::where('email','admin@gasemcasa.com')->first()->password)
```

Tem que responder **`false`**.

> 💡 **Posso fazer isso por você** — gero as senhas fortes, troco as duas e te
> entrego. Leva dois minutos.

---

### 🟠 BLOCO B — Decisões que só você pode tomar

Estas não têm trabalho: são perguntas. Mas a resposta muda o que fica no sistema.

#### B1. O acerto de malote ainda acontece?

**O que é.** No sistema antigo existe uma tela onde se confere o dinheiro que o
entregador traz de volta no fim do turno.

**A pergunta:** isso ainda é feito na operação de hoje?

- **Se sim** → já está pronto, não precisa fazer nada
- **Se não** → me avise que eu removo (são 4 arquivos)

#### B2. O call-center usa bina?

**O que é.** "Bina" é quando o telefone toca e o sistema já mostra na tela quem
está ligando, abrindo a ficha do cliente.

**A pergunta:** o atendimento usa isso hoje?

- **Se sim** → está pronto; falta só apontar a central telefônica para o sistema
  (ver tarefa D6)
- **Se não** → me avise que eu removo

#### B3. Qual saldo das contas é o verdadeiro?

**O que é.** Descobri algo durante a migração que precisa da sua decisão.

**A situação.** Em contabilidade, o saldo de uma conta deveria ser a soma de
todas as entradas e saídas. No sistema antigo, **isso nunca bateu**. Exemplo real:

> A conta 692 tem saldo **zero** registrado, mas a soma de todos os movimentos
> dela dá **R$ 26,5 milhões**.

**Isso não é erro da migração** — verifiquei direto no sistema antigo, e ele já
estava assim. Provavelmente o sistema zerava saldos periodicamente sem registrar
o lançamento correspondente.

**O que você precisa decidir.** Qual dos dois números está certo?

- Se o **saldo registrado** for o correto (o que a operação usa no dia a dia),
  não há nada a fazer
- Se o **derivado dos movimentos** for o correto, é preciso recalcular — e isso
  mudaria o saldo de **28 contas**, algumas na casa dos milhões

**Como decidir:** pegue **2 ou 3 contas bancárias ativas** e compare o saldo que
aparece no sistema com o **extrato bancário real** do mesmo dia. O que bater com
o banco é o verdadeiro.

> 💡 **Posso montar o comparativo** — extraio os números das 28 contas numa
> planilha para você conferir. A conferência com o extrato continua sendo sua.

---

### 🔵 BLOCO C — Chaves e contas externas

Nenhuma destas se resolve com acesso ao servidor: o que falta está fora dele.

#### C1. Firebase — login do app do cliente

**Sem isso:** o aplicativo do consumidor não consegue fazer login em produção.

**Como fazer:**
1. Entrar no [Console Firebase](https://console.firebase.google.com) com sua conta Google
2. Ir em **Configurações do projeto → Contas de serviço**
3. Clicar em **Gerar nova chave privada** (baixa um arquivo `.json`)
4. Me enviar o arquivo, ou colocá-lo no servidor seguindo `PENDENCIAS_OPERACIONAIS_F3.md` §P1

**Como saber que deu certo:** um cliente real consegue logar no app recebendo
SMS de verdade.

#### C2. Certificado digital A1 — emissão de nota fiscal

**Sem isso:** o sistema não emite NF-e nem NFC-e.

**Situação atual:** **nenhuma empresa tem certificado cadastrado.**

**Como fazer:** para cada empresa que emite nota:
1. Ter em mãos o arquivo `.pfx` e a senha dele
2. No sistema: **Empresas → (a empresa) → Certificado → upload + senha**
3. O sistema valida na hora — se a senha estiver errada, ele recusa

> ⚠️ **Certificado A1 não se recupera sem custo e prazo.** Depois do primeiro
> upload, confirme comigo que o backup passou a incluí-lo.

#### C3. PIX — cobrança

**Sem isso:** o sistema não gera cobrança por PIX.

**Como fazer:** pegar com seu banco/PSP as credenciais e informar. **São dois
segredos diferentes** (confundir os dois é comum):
- `PIX_WEBHOOK_SECRET`
- `PIX_WEBHOOK_HMAC_SECRET`

E registrar no painel do PSP o endereço:
`https://gasemcasa.com/novo/api/pix/webhook`

#### C4. Sentry — aviso de erros

**Sem isso:** quando dá erro em produção, ninguém fica sabendo. O erro fica
gravado num arquivo que ninguém lê.

**Como fazer:** criar conta em [sentry.io](https://sentry.io) (tem plano
gratuito) e me passar o código DSN.

#### C5. Backup fora do servidor

**A situação:** o backup roda todo dia e funciona — mas **fica guardado no mesmo
servidor que ele protege**. Se o servidor for perdido, o backup vai junto.

**O que preciso de você:** para onde copiar? Outro servidor? Um serviço de
armazenamento? Com essa resposta eu configuro.

#### C6. Restringir a chave do Google

**A situação:** a chave do Google Maps vai dentro do aplicativo — isso é normal e
inevitável, qualquer app instalado a expõe. **Verifiquei que ela nunca vazou no
repositório de código.**

**A proteção correta** não é escondê-la, é limitá-la no console:
1. Entrar no [Console Google Cloud](https://console.cloud.google.com)
2. Restringir a chave por aplicativo (nome do pacote + assinatura)
3. Limitar às APIs realmente usadas (Maps SDK, Geocoding)

**Sem isso:** quem extrair a chave do aplicativo consome a sua cota.

---

### 🟡 BLOCO D — Verificações físicas e integrações

#### D1. Testar o código de barras do boleto

**Como fazer:** imprimir um boleto pelo sistema e passar no leitor do caixa.

**Por que não dá para pular:** os testes provam que o código segue a
especificação técnica. Só o leitor prova que ele **lê**. Um boleto com código
errado é pior que boleto nenhum — o cliente tenta pagar, o caixa recusa, e a
culpa recai sobre a revenda.

#### D2. Testar o código de barras do DANFE

**Como fazer:** imprimir um DANFE e passar a chave no leitor.

**Atenção:** é um **teste separado** do D1. Boleto e DANFE usam padrões
diferentes de código de barras — passar um não garante o outro.

#### D3. Revisão jurídica do contrato de comodato

**O que é.** O sistema imprime o contrato de empréstimo do botijão, com 7
cláusulas que escrevi com base no padrão do setor.

**Por que importa:** quem responde pelo contrato assinado é a revenda, não o
sistema. Vale passar o texto ao advogado.

**Facilidade:** o texto está isolado num único lugar do código. Trocar as
cláusulas não afeta mais nada.

#### D4. Apontar a central telefônica (só se B2 = sim)

Configurar o PABX para avisar o sistema quando o telefone tocar:
- Endereço: `POST https://gasemcasa.com/novo/api/pabx/chamada`
- Cabeçalho: `X-Pabx-Token` com o segredo que definirmos

#### D5. Regerar os aplicativos

**Depende de:** C1 (Firebase) e C3 (PIX).

Depois que as chaves estiverem no lugar, os apps precisam ser gerados de novo
para incluí-las. Sem isso, eles funcionam mas ficam mais lentos (consultam o
servidor repetidamente em vez de receber avisos).

---

### 🟢 BLOCO E — A virada (o cutover)

**Só depois de A, B e C resolvidos.**

O roteiro detalhado está em `deploy/CUTOVER_RUNBOOK.md`. Resumo do que ele exige:

#### E1. Ensaio completo (uma semana antes)

**O mais importante desta lista.** Fazer a mudança inteira num ambiente de teste,
com uma cópia real dos dados, **cronometrando cada passo**.

**Por que:** é assim que se descobre quanto tempo a janela precisa ter. A regra é
reservar **o dobro** do tempo medido no ensaio.

#### E2. Preparar o banco de produção

O banco novo precisa nascer **vazio** — nunca reaproveitar o de testes, que tem
dados fictícios.

Existe um comando que confere isso:
```bash
php artisan banco:producao-check
```
Ele responde se o banco está pronto. Se disser **PORTÃO FECHADO**, não prossiga.

#### E3. Testar a virada e a volta

Antes da noite da virada, testar o comando que muda o site do sistema antigo para
o novo — **e o que desfaz**:

```bash
bash deploy/nginx/virar.sh novo      # vira
bash deploy/nginx/virar.sh legado    # desfaz
```

Desfazer leva segundos. É a sua primeira linha de defesa.

#### E4. Conferência humana (na noite da virada)

Depois que os dados forem copiados e antes de virar a chave, alguém que conhece
o negócio confere, com o sistema antigo aberto ao lado:

- 5 clientes de tipos diferentes: nome, telefone, endereço, histórico, saldo
- Um pedido do começo ao fim: criar → concluir → ver se baixou estoque e gerou
  financeiro
- Emitir uma nota fiscal de verdade e conferir na SEFAZ
- Login no app do cliente e no app do entregador
- **A soma do financeiro tem que bater ao centavo**
- Um usuário de uma empresa **não pode** ver dados de outra

**Qualquer item errado = não vira.**

#### E5. Definir quem decide

Escolher **uma pessoa** (não um grupo) que dá a palavra final na noite da virada,
e uma **hora-limite**: se os testes não estiverem OK até tal hora, adia — não se
estende a madrugada improvisando.

#### E6. Acompanhamento pós-virada

- **Primeiros 15 minutos:** conferir se o site responde e não há erros
- **Primeira hora:** conferir se as tarefas automáticas rodaram
- **Primeiro dia:** acompanhar os operadores. Cada "não consigo fazer X" precisa
  ser anotado
- **Primeiros 30 dias:** manter o sistema antigo ligado, mas **só para consulta**

---

## Parte 3 — Resumo em uma tabela

| # | Tarefa | Quem faz | Bloqueia a virada? |
|---|---|---|---|
| A1 | Trocar senhas de admin | Você (ou eu, se autorizar) | 🔴 Sim — segurança |
| B1 | Malote ainda acontece? | Só você | Não — só define o que fica |
| B2 | Call-center usa bina? | Só você | Não — só define o que fica |
| B3 | Qual saldo é o verdadeiro? | Só você (com o extrato) | 🟠 Sim, se for o derivado |
| C1 | Firebase | Você (conta Google) | 🔴 Sim — app não loga |
| C2 | Certificado A1 | Você (certificadora) | 🔴 Sim — não fatura |
| C3 | PIX | Você (banco/PSP) | 🔴 Sim — não cobra |
| C4 | Sentry | Você (criar conta) | Não, mas voa às cegas |
| C5 | Backup externo | Você decide o destino | Não, mas é risco |
| C6 | Restringir chave Google | Você (console) | Não |
| D1 | Boleto no leitor | Você (papel + leitor) | 🔴 Sim |
| D2 | DANFE no leitor | Você (papel + leitor) | 🔴 Sim |
| D3 | Revisão jurídica | Advogado | Não |
| D4 | Apontar PABX | Técnico da telefonia | Só se B2 = sim |
| D5 | Regerar apps | Depende de C1 e C3 | Não |
| E1 | **Ensaio cronometrado** | Equipe técnica | 🔴 **Sim — o mais crítico** |
| E2 | Banco de produção | Equipe técnica | 🔴 Sim |
| E3 | Testar virada e volta | Equipe técnica | 🔴 Sim |
| E4 | Conferência humana | Operador experiente | 🔴 Sim |
| E5 | Nomear decisor | Você | 🔴 Sim |
| E6 | Acompanhamento | Equipe | Depois |

---

## Parte 4 — O que eu posso fazer, se você autorizar

Quatro itens da lista eu resolvo com acesso ao servidor:

1. **Trocar as duas senhas** (A1) — gero fortes e te entrego
2. **Montar o comparativo de saldos** (B3) — extraio os números das 28 contas
3. **Comparar a configuração do site** com o que está salvo no repositório (E3,
   primeira metade)
4. **Configurar a cópia do backup** (C5) — assim que você disser o destino

O resto da lista não é questão de acesso: é chave que está na sua conta, papel
que precisa passar num leitor, ou decisão que só você tem como tomar.

---

## Onde encontrar mais detalhe

| Assunto | Arquivo |
|---|---|
| Roteiro completo da virada, passo a passo | `deploy/CUTOVER_RUNBOOK.md` |
| Detalhe técnico das chaves externas | `docs/gauntlet/PENDENCIAS_OPERACIONAIS_F3.md` |
| A divergência de saldo, investigada | `docs/gauntlet/T5.1_ACHADOS.md` §4 |
| O que foi entregue em cada fase | `docs/gauntlet/STATUS_FINAL.md` |
| O plano técnico original (46 tarefas) | `docs/gauntlet/PLANO_PRODUCAO.md` |
