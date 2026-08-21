# NFWEB e MovelApp — o que já está coberto e o que falta

Levantamento de **2026-08-20**, quando o desenvolvedor do legado revelou dois
aplicativos em produção que não estavam no escopo da reescrita.

Fontes em `legado-nfweb/` e `legado-movelapp/` (referência, como o `ctrl-web`).

---

## Os dois apps

| | NFWEB | MovelApp |
|---|---|---|
| Origem | `nfweb/reactapp.git` | SVN `android/MovelApp` |
| Pilha | React Native 0.61.5 | Android Java nativo (53 classes) |
| Papel | Força de vendas em campo | Entrega / romaneio |
| Último build | 17/07/2025 | **12/11/2025** (flavio) |
| Backend | `NfwebController.php` (1773 linhas, 18 rotas) | `ctrl2/public/api/` |
| Servidor | `adm.gasemcasa.com.br` | `gasemcasa.com.br` |

O nome "NFWEB" engana: **não é sistema web de nota fiscal**. É o app do vendedor
que visita o cliente — cadastra, tira pedido, consulta duplicata e imprime.

---

## Cobertura: NFWEB → ERP novo

| NFWEB | ERP novo | Situação |
|---|---|---|
| `login` | `app/v1/login` | coberto |
| `saveCliente` | `missao/clientes` | coberto |
| `savePedido` | `missao/venda`, `missao/vale-gas` | coberto |
| `getCliente` / `pedidoConsulta` | `pedidos`, `pedidos/{id}` | coberto |
| `changeVeiculo` | `entregador/veiculos` | coberto |
| `pedidosReport` | `entregador/dashboard` | coberto |
| `changeRegistrationId` (push) | Firebase no app novo | coberto |
| `getParcelasVencidasCliente` | — | **LACUNA** |
| `visualizarDanfe` / `baixarDanfe` | só rota admin | **LACUNA** (não exposto ao app) |
| `visualizarBoleto` | só rota admin | **LACUNA** (não exposto ao app) |
| `pedidoDuplicata` | — | **LACUNA** |
| impressão térmica | — | **LACUNA CRÍTICA** |

## Cobertura: MovelApp → ERP novo

A operação de entrega está **bem coberta**: `entregador/rota`, `pedidos/{id}/status`,
`aceitar`, `recusar`, `ocorrencia`, `concluir`, `jornada/iniciar`, `posicao`,
e o app-entregador tem as telas correspondentes.

Faltam duas coisas:

1. **Operação offline.** O MovelApp tem SQLite local com 8 tabelas
   (`tbl_pedidos`, `tbl_pedidos_itens`, `tbl_veiculos`, `tbl_situacoes`,
   `tbl_motivos_atrasos`, `tbl_empresa`, `tbl_usuarios`, `tbl_config`):
   funciona sem rede e sincroniza depois. O `app-entregador` **não tem
   AsyncStorage nem fila de sincronização** — o "offline-first" citado em
   `helpers/realtime.ts` é apenas queda para polling quando o WebSocket cai.
   Para entregador em rota com sinal instável, é diferença de operação real.

2. **Impressão térmica** — abaixo.

---

## A lacuna crítica: impressão no cliente

Os **dois** apps imprimem na mão do cliente, por caminhos diferentes:

- **NFWEB**: módulos Java nativos (`PrintBoletoModule`, `PrintDanfeModule`,
  `PrintDuplicataModule`) sobre `NfePrinterLib.jar` — biblioteca **proprietária**
  do fabricante. O último commit do repo (17/07/2025) foi justamente para
  suportar a impressora "Leopardo Pro Max".
- **MovelApp**: ESC/POS por Bluetooth (`Bluetooth.java`, `ESCP.java`) — padrão
  aberto, mais fácil de replicar.

**O ERP novo não tem impressão térmica em lugar nenhum.** Busca por
`esc-pos|bluetooth|impressora térmica` em `erp-novo/`, `app-entregador/` e
`app-gas-em-casa/` não retorna nada.

Isto não se resolve reescrevendo em React: depende da biblioteca do fabricante
ou de hardware equivalente. **Pendente com o cliente: quantas impressoras
existem em campo e quais modelos** — é o que decide entre integrar o JAR
proprietário, adotar ESC/POS genérico, ou trocar o parque.

---

## Achados de segurança (do legado, não da reescrita)

| O quê | Onde |
|---|---|
| Chave Google API em produção, dentro do APK | `legado-nfweb/src/helper/Constants.js:22` |
| Chaves Firebase (`gas-em-casa`, `appmovel-169219`) | `google-services.json` dos dois |
| **Keystore de produção versionado** no SVN | `movelapp.jks` (não copiado para cá) |
| WebSocket sem TLS, com `app_key` na query | `ws://adm.gasemcasa.com.br:8092` |
| `targetSdk 28` — abaixo do mínimo do Google Play | `legado-movelapp/app/build.gradle` |

O keystore é o mais grave: quem tem acesso ao repositório pode assinar APKs
como se fosse o cliente.

---

## Limite deste levantamento

Foram analisados estrutura, rotas, dependências e configuração — **não** as
~5 mil linhas de lógica de negócio. O mapeamento acima diz que *existe* rota
equivalente, não que a *regra* seja a mesma (descontos, condições de pagamento,
comissão). Confirmar isso exige leitura linha a linha do `NfwebController` contra
o domínio `Pedido`.
