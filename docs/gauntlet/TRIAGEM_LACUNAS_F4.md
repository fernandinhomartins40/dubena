# Triagem das lacunas funcionais restantes (T4.9)

> **O que este documento é.** A auditoria alerta que *"auditorias por checklist
> de tela tendem a marcar como migradas"* telas que existem mas não permitem
> concluir o trabalho. Esta triagem é a defesa contra virar sem saber o que
> falta: **cada linha aberta recebe um veredito explícito**, e nenhuma fica sem.
>
> **O que este documento NÃO é.** Não é promessa de implementação. Um veredito
> `PÓS-GO-LIVE` é uma decisão consciente de operar sem aquilo por um tempo
> definido, com workaround escrito.
>
> **Data:** 17 de agosto de 2026. **Base:** Auditoria §2 (matriz de 46 linhas).

---

## Onde a F4 chegou

| Tarefa | Lacuna | Estado |
|---|---|---|
| T4.1 | Resolução de inconsistências geográficas | ✅ implementada |
| T4.5 | Tipos de documento de veículo | ✅ implementada |
| T4.6 | Boleto, recibo e comanda em PDF | ✅ implementada (⚠️ conferência humana do barcode pendente) |
| T4.7 | Escrita de recessos, comissões, óleo e pneus | ✅ implementada |
| T4.8 | Motivos de atraso e de não-venda | ✅ implementada |
| T4.2 | Classificação automática do extrato (OFX) | ⏳ **PRÉ-GO-LIVE** — ver §1 |
| T4.3 | Fechamento de malote | ❓ **decisão do dono** — ver §2 |
| T4.4 | Telefonia/bina no atendimento | ❓ **decisão do dono** — ver §3 |

---

## §1 — T4.2: classificação automática do extrato → **PRÉ-GO-LIVE**

**Veredito: implementar antes de virar.** É a única lacuna técnica que sobra na
família "ação que fecha o ciclo", e o volume torna o trabalho manual inviável.

A importação OFX existe (`ConciliacaoService`), mas sem as regras de
classificação (`extratoconfig` do legado) **cada linha do extrato precisa ser
classificada à mão**. Com `contamovimentos` em 410.417 linhas, isso não é
inconveniência — é impedimento.

**Esforço estimado:** tabela + enum + service + CRUD + integração na conciliação
+ aba no front. Comparável à T4.2 como especificada no plano; não tem
dependência externa.

**Workaround se escorregar:** conciliar só as contas de maior movimento e deixar
as demais para o mês seguinte. Não recomendado como estado permanente.

---

## §2 — T4.3: fechamento de malote → **PERGUNTA ABERTA AO DONO**

> **Pergunta:** *o acerto físico de valores do entregador (malote) ainda
> acontece na operação diária?*

Não é pergunta retórica: a resposta muda a classificação de bloqueante para
aposentado, e **eu não tenho como respondê-la a partir do código**.

- **Se SIM** → é `PRÉ-GO-LIVE` e bloqueante: o dinheiro do dia não fecha sem o
  acerto. O legado tem `FechamentomaloteController` (pedidos do malote,
  parcelas, `updatePedido`, `fechar`) + `ReportvendasmaloteController`.
- **Se NÃO** (o acerto virou digital, pelo app do entregador) → `APOSENTADO`,
  com uma verificação: confirmar que o fluxo de missões/entregas
  (`app/Domain/Logistica/`, `app/Domain/Missao/`) de fato cobre a prestação de
  contas, e não só o registro da entrega.

**Estado no novo:** grep `malote` retorna apenas um campo de config contábil em
`ContabilTab.tsx`. Não existe endpoint, service nem tela.

---

## §3 — T4.4: telefonia/bina → **PERGUNTA ABERTA AO DONO**

> **Pergunta:** *o call-center usa o bina (identificação da chamada abrindo a
> ficha do cliente) hoje?*

- **Se SIM** → `PRÉ-GO-LIVE`. E o custo é maior do que parece: além da
  integração com o PABX, a SPA **não usa Echo hoje** (grep `laravel-echo` em
  `frontend/src` = vazio), então seria a primeira assinatura de canal do
  frontend web. O Reverb já está funcionando (F3), o que reduz parte do risco.
- **Se NÃO** → `APOSENTADO`. O histórico (`LIGACOESTELEFONICAS`, 13.214 linhas)
  já está **arquivado** e consultável no schema `legado` — decisão registrada na
  T2.7 justamente porque este veredito estava pendente.

**Atenção de segurança se for portar:** o legado autentica o webhook do PABX com
`sha1(APP_KEY)`. Não repetir — usar segredo dedicado com HMAC, como o webhook
PIX já faz.

---

## §4 — As demais lacunas ⚠️, uma a uma

Classificação: **PRÉ** = antes do go-live · **PÓS** = depois, com prazo ·
**APOSENTADO** = o negócio abandona.

| # | Lacuna | Veredito | Justificativa e workaround |
|---|---|---|---|
| 3 | Impressão de contrato/etiquetas de convênio | **PÓS** (90d) | A mala direta já exporta CSV para etiquetas. Contrato de convênio é evento raro (97 clientes conveniados no total) — imprimir de um modelo em Word cobre o intervalo. |
| 6 | PDFs de requisição/transferência de estoque | **PÓS** (90d) | Movimentação **interna**: o saldo está correto no sistema, o papel é conferência de conveniência. A infra de PDF da T4.6 torna isto barato depois. |
| 8 | Exportar XMLs em lote; enviar NF por e-mail; DANFE | ✅ **DANFE feita** / **PÓS** (resto) | DANFE implementada (Code 128C, canhoto, tarja de cancelada; só de nota autorizada). ⚠️ conferência humana do barcode pendente. Exportação em lote e envio por e-mail seguem **PÓS** — o contador aceita receber os XMLs por pasta compartilhada no intervalo. |
| 10 | SPED Créditos; download do arquivo gerado | **PÓS** (60d) | SPED Fiscal e Contribuições existem e são as obrigações mensais. Créditos é apuração específica; confirmar com a contabilidade se a empresa a entrega. |
| 12 | Recibo de caixa | ✅ **feito** (T4.6) | — |
| 13 | Desconto/antecipação de cheque | **PÓS** (90d) | Operação financeira pontual. O ciclo carteira→depósito→compensação→devolução está completo. |
| 14 | PDF do boleto | ✅ **feito** (T4.6) | ⚠️ conferência humana do barcode pendente |
| 14b | CRUD de layouts de banco | **APOSENTADO** | Viraram drivers em código (`Cnab/`). Perde-se configurar banco novo sem deploy — mas ganha-se validação e teste. Adicionar banco passa a ser tarefa de desenvolvimento. |
| 16 | Importar arquivo do adquirente de cartão | **PÓS** (60d) | Virou registro de NSU. O conferente cruza o relatório do adquirente à mão no intervalo — volume baixo comparado ao extrato bancário. |
| 18 | Convênio: NF+boleto encadeados; PDF/XLS do fechamento; dashboard GB | **PÓS** (60d) | O fechamento existe e consolida. Emitir NF e boleto a partir dele é um passo manual a mais — aceitável para 97 conveniados. |
| 19 | Vale-gás impresso e duplicata em PDF | **PRÉ** | O vale **é** um documento físico entregue ao cliente: sem impressão, o produto não existe. A infra da T4.6 torna isto pequeno agora. |
| 20 | Contrato de comodato em PDF; gestão de saldos/vencidos/giro | **PRÉ** (contrato) / **PÓS** (gestão) | O contrato é o documento que protege o patrimônio da revenda (o vasilhame). A gestão analítica é gerencial. |
| 21 | Recessos e comissões | ✅ **feito** (T4.7) | — |
| 22 | Troca de óleo e pneus; conferência de carga da portaria | ✅ **feito** (T4.7) / **PÓS** (portaria) | — |
| 24 | DANFE/boleto/duplicata no dispositivo do entregador; parcelas vencidas na entrega | **PÓS** (90d) | O entregador leva o papel impresso da comanda (T4.6). Consultar parcelas vencidas no ato é melhoria de cobrança, não bloqueio. |
| 26 | Filtros de prospecção da venda ativa; tipos de ocorrência; relatórios de promotor | **PÓS** (120d) | As missões de campo cobrem e ampliam a operação. Os filtros por média de giro são ferramenta de campanha. |
| 27 | E-mail em massa e etiquetas na mala direta | **PÓS** (90d) | Segmentação e CSV existem. O e-mail em massa depende de SMTP configurado (pendência da F3) e de cuidado com reputação de domínio. |
| 28 | ~23 relatórios sem equivalente | **ver §5** | Classificados individualmente. |
| 29 | Fechamento mensal com e-mail/export; PowerPoint; dashboards | **PÓS** (120d) | Gestão, não operação. O DRE e o dashboard básico existem. |
| 30 | Versionamento de documento com upload/download; impressão do MCMM | **PÓS** (90d) | O CRUD documental existe; falta o histórico de versões. |
| 31 / A11 | Campanhas de push segmentadas; vídeo de abertura; giro de compras | **PÓS** (120d) | Push **transacional** funciona (é o que o cliente espera do pedido). Campanha é marketing. |
| A10 | Vídeo de abertura do app | **APOSENTADO** | Já desligado deliberadamente no código, com comentário. Confirmar com o dono que não voltará. |

---

## §5 — Os ~23 relatórios (item 28), um a um

O plano pede atenção especial aqui, porque é o maior volume e o mais fácil de
classificar em bloco por engano.

| Relatório | Veredito | Por quê |
|---|---|---|
| **Fluxo de caixa** | **PRÉ** | Rotina financeira diária/semanal. É o relatório que responde "tenho dinheiro para pagar o quê". |
| **Clientes sem compra / inativos** | **PRÉ** | Rotina comercial: é a lista de quem parar de comprar. Base da venda ativa. |
| Clientes incompletos | **PÓS** (60d) | Higiene de cadastro; pode esperar. |
| Mapas de entrega georreferenciados (4 variantes) | **PÓS** (120d) | A central de logística e o rastreamento cobrem a operação do dia. Os mapas são análise. |
| Tempo de entrega | **PÓS** (90d) | Indicador de qualidade; o dado está registrado e pode ser extraído depois. |
| Metas × vendas no mapa | **PÓS** (120d) | Gerencial. |
| Follow-up | **PÓS** (90d) | O CRM tem pós-venda e checklists. |
| Fornecedores | **PÓS** (60d) | Consulta pontual. |
| Convênio-funcionários | **PÓS** (60d) | Relacionado ao item 18. |
| Colaboradores: exames/férias/faixa etária | **PÓS** (90d) | RH tem as telas; o relatório é consolidação. |
| Retroativo | **PÓS** (60d) | Consulta histórica. |
| **Log de senha mestra** | **APOSENTADO** (verificado) | Verifiquei: no novo a senha mestra existe apenas como **configuração da empresa** (`empresa_configs.senha_mestra`, migrada do legado) — **não há fluxo que a valide** para liberar operação. Ou seja, não há uso a registrar. O que o legado logava não existe mais como comportamento. A trilha de auditoria do novo é mais ampla que a do legado (12+ tipos em `security_events`, incluindo `login.suporte`, `autorizacao.negada` e todo o histórico de papéis). **Se** o fluxo de liberação por senha mestra for reintroduzido, o evento tem de nascer junto. |
| Vendas por malote | **depende de §2** | Se o malote for aposentado, este relatório vai junto. |
| Troca de óleo vencida | ✅ **coberto** | O alerta existe (`alertaTrocaOleo`) e a T4.7 fez a escrita zerá-lo. Falta só expor como relatório — trivial. |
| Vendas por seg/setor/produto | **PÓS** (60d) | O catálogo novo tem vendas por produto/operação/entregador. |
| Demais (aniversariantes, comissões, estoque, NF, promoções, veículos…) | ✅ **cobertos** | Já existem entre os 17 slugs do catálogo novo. |

---

## Resumo do que falta antes de virar

**PRÉ-GO-LIVE (6 itens):**

1. ~~T4.2 — classificação automática do extrato bancário~~ ✅ **feito**
2. ~~DANFE (item 8)~~ ✅ **feito** (⚠️ conferência humana do barcode)
3. Vale-gás impresso e duplicata (item 19)
4. Contrato de comodato em PDF (item 20)
5. Relatório de fluxo de caixa (item 28)
6. Relatório de clientes sem compra/inativos (item 28)

*(O log de senha mestra saiu da lista: verifiquei que o fluxo que ele registrava
não existe mais no novo — ver §5.)*

**+ 2 decisões suas** (§2 malote, §3 bina) que podem adicionar dois itens
bloqueantes ou remover ambos do escopo.

**Conferência humana pendente:** o código de barras do boleto (T4.6, I2of5) e a chave do DANFE (Code 128C). São padrões diferentes — conferir os dois separadamente num leitor.

Tudo o mais está classificado como PÓS com prazo e workaround, ou aposentado com
justificativa. **Zero linhas sem veredito.**
