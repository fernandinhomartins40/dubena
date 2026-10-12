# Relatório Final — praticidade para usuário leigo

## Resumo

Análise do ERP como produto pela ótica de quem nunca usou o sistema, com a
regra dos três passos (iniciar → revisar o necessário → concluir) e a
condição do dono: **reorganizar sem eliminar nada**. Foram examinados o menu
(36 itens), as rotas, o painel inicial e o fluxo mais frequente do negócio —
lançar um pedido de gás — da tela até a coluna no banco.

O achado mais sério não era de aparência: o formulário de pedido **obrigava
o atendente a preencher campos que o servidor jogava fora**, e o pedido
lançado pelo painel nascia sem condição de pagamento e sem setor.

## Melhorias realizadas

1. **Pedido em três blocos, pré-preenchido pelo cliente** (P1-01 + OP-01).
   Antes: 11 campos obrigatórios antes do primeiro item, mais o preço digitado.
   Agora: (1) escolhe o cliente — o sistema mostra o endereço de entrega, o
   telefone e o último pedido; (2) "Repetir último pedido" traz os itens com o
   preço de hoje e diz de onde ele veio (combinado com o cliente, tabela da
   condição ou preço de venda); (3) condição, situação, operação e setor já
   vêm preenchidos — revisar e criar. Endereço incompleto é avisado antes de
   salvar, com link para o cadastro.
2. **Painel "Para hoje" e "O que você quer fazer?"** (OP-02). Pedidos
   esperando (e há quanto tempo o mais antigo espera), vendas do dia, a
   receber hoje, recebimentos atrasados, a pagar hoje, estoque abaixo do
   mínimo, comodatos vencidos e clientes duplicados — cada cartão leva para
   onde se resolve. Três ações rápidas no topo. Os números históricos e os
   atalhos continuam, abaixo.
3. **Menu por tarefa, em linguagem comum** (OP-03). Grupos "Vendas e
   clientes", "Entregas", "Produtos e estoque", "Dinheiro", "Notas fiscais"…;
   jargão traduzido ("MCMM" → Movimentação de material, "Geográfico" →
   Endereços, "Alçadas" → Limites de desconto, "Monitora" → Rastreamento);
   dica de uma linha por item; busca por sinônimos ("boleto", "entregador",
   "pagar conta").
4. **Nome da empresa no menu** (P1-02) no lugar do "Dubena" fixo.
5. **Botão "Novo" no topo de toda tela** (OP-04): pedido, cliente, produto —
   só o que a pessoa pode criar.

## Arquivos e áreas alteradas

- Backend: `PedidoRequest`, `PedidoController` (+ `sugestao`, `precos`),
  `Domain/Pedido/PedidoSugestaoService` (novo), `RelatorioController`
  (+ `dashboardHoje`), `Domain/Relatorio/PainelDoDiaService` (novo),
  `routes/api.php`, `ApiManifestGerar` (exceção justificada), manifesto.
- SPA: `pedidos/NovoPedidoDialog.tsx` (novo), `pedidos/api.ts`,
  `pedidos/PedidoDialogs.tsx`, `dashboard/ParaHoje.tsx` (novo),
  `dashboard/DashboardPage.tsx`, `layouts/AppShell.tsx`,
  `layouts/ModuleFinder.tsx`, `layouts/BotaoNovo.tsx` (novo), `lib/titulo.ts`.

## Problemas corrigidos

- **P1** — pedido do painel gravado sem `condicaopagamento_id` (regra ausente
  no request; `safe()` descartava) e sem `setor_id` (enviado como
  `entregasetor_id`). Seis campos obrigatórios sem coluna no banco
  (colaborador, cidade, bairro, rua, número, UF de entrega).
- **P1** — marca de uma revenda fixa no menu de um produto multiempresa.
- **P2** — painel sem informação acionável; menu em jargão; criação presa ao
  módulo.

## UX/UI

Ver "Melhorias realizadas". Padrões preservados: componentes de
`@/components/ui`, tokens de cor (`text-marcaTexto` para contraste AA),
estados de carregando/erro/vazio pelo `AsyncState`. Grades responsivas
(`sm:`/`xl:`) e o botão "Novo" vira só ícone em tela estreita.

## Backend e banco

Sem migration. Dois endpoints de leitura novos (`pedidos/sugestao`,
`pedidos/precos`) e um de painel (`dashboard/hoje`). A sugestão **não decide
nada**: preço final, alçada e estoque continuam no `PedidoService`. O
atendente do pedido passa a ser quem o lança (o "colaborador" do formulário
não era gravado, e escolhê-lo pelo corpo é a brecha de alçada que o serviço
já recusa).

## Segurança

- Todos os ids novos validados com `ExisteNoTenant` (condição de pagamento de
  outro grupo é 422 — testado; cliente de outra empresa é 422 — testado).
- `dashboard/hoje` entrega cada bloco só a quem tem o módulo; bloco sem
  permissão fica **ausente**, não zerado — testado. A rota entrou na lista
  fechada de exceções do manifesto com a justificativa escrita.
- Menu: destino, permissão e recurso dos 36 itens idênticos ao commit
  anterior (comparado por diff e travado por teste).

## Performance e infraestrutura

Nenhuma dependência nova, nenhum serviço novo. O painel faz consultas
agregadas por empresa e se atualiza a cada 60 s; a sugestão lê um pedido e
seus itens.

## Testes executados

| Verificação | Comando | Resultado |
|---|---|---|
| Backend, suíte integral (sqlite) | `php artisan test` | 1.915 passaram, 22 pulados (os de PostgreSQL/RLS), 1 falha — o guardião `EnderecoPontoUnicoTest` pegou a sugestão montando endereço à mão; corrigido para usar o acessor do model e reverificado (verde) |
| Backend, testes novos | `NovoPedidoPraticoTest`, `PainelDoDiaTest` | 9 + 3 verdes |
| Guardião provado | regra da condição removida do `PedidoRequest` | 2 testes certos falharam; regra restaurada |
| Contrato da API | `api:manifest` + `ApiContratoDriftTest` | 613 endpoints, verde |
| SPA, typecheck | `npx tsc --noEmit` | limpo |
| SPA, build | `npx vite build` | ok |
| SPA, testes novos/alterados | pedidos, dashboard, layouts, titulo | todos verdes, inclusive isolados e repetidos |
| SPA, suíte completa | `npx vitest run` | 136/138; as 2–4 falhas variam a cada rodada (duas abas, cadastro de cliente, Kanban, confirmações) e **ocorrem igualmente no código sem estas mudanças** (comparado com `git stash`: 3–4 falhas, mesmos testes). Instabilidade preexistente de timing, não regressão |
| Menu preservado | diff contra o commit anterior + teste | 36 itens, destino/permissão/recurso idênticos |

## Itens bloqueados

Nenhum.

## Riscos restantes

- O "Para hoje" foi validado em sqlite; o comportamento com os dados reais de
  homologação é conferido após o deploy (ver resposta da sessão).
- Desconto combinado por cliente (`clienteprecos.desconto`) **não** entra no
  preço sugerido: o cadastro não diz se é valor ou percentual, e adivinhar
  mudaria o preço cobrado. O preço combinado (`preco`) entra.

## Próximas melhorias recomendadas

- "Novo pedido para este cliente" direto da ficha do cliente (o diálogo já
  aceita abrir por URL; falta aceitar o cliente pré-escolhido).
- Filtros por URL nas listas de Pedidos e Financeiro, para os cartões do
  "Para hoje" abrirem já filtrados (hoje levam à tela certa, sem filtro).
- `/satelites` (Relatórios & Integrações) existe sem entrada no menu —
  decidir se entra.
- Prazo de "pedido atrasado" como configuração do tenant, para o painel
  poder destacar atraso em vez de só idade.

---

# Segunda passada — de ponta a ponta (11/10/2026)

## O que foi analisado, e como

A primeira passada cobriu menu, painel inicial e o fluxo de pedido. Esta
cobre **toda tela que grava algo**: uma ferramenta compara, para cada pasta de
tela, os campos que os formulários escrevem com as chaves que cada rota de
escrita aceita (174 arquivos, 221 rotas). Ela foi **provada antes de usar**
contra a versão antiga do pedido (acusou os 7 campos descartados). Depois, o
sentido inverso — o formulário abre com campo que a API não devolve — foi
conferido nos cadastros com edição (produto, empresa, cliente, colaborador,
veículo), comparando com a resposta real da API da homologação.

## O que foi encontrado e corrigido

| Gravidade | Tela | O que acontecia |
|---|---|---|
| P0 | Empresa / emissão | `config_fiscais` (de onde a emissão lê ambiente, regime, séries, CSC) estava **vazia nas 7 empresas** e nenhuma tela a gravava. Toda nota real cairia em erro |
| P0 | Emissão de NFC-e | O NFePHP exige CSC/CSCid para o QR Code e eles não iam; o modelo 65 também não. **Toda NFC-e real falharia na assinatura** |
| P1 | Configurações da empresa | **Salvar falhava sempre** (422), reproduzido na homologação; 29 de 40 chaves com nome errado |
| P1 | Lançamento financeiro | Data, vencimento e condição descartados: UMA parcela vencendo **hoje** |
| P1 | Produto | Campos fiscais (ANP, IPI, grupo fiscal, EAN tributável) descartados e abertos em branco; origens recriadas zeradas |
| P1 | Produto | Seletor "Grupo fiscal" era lista fixa com ids que não são grupos de ninguém |
| P1 | Operação fiscal | "Movimenta estoque/financeiro" sempre desmarcados e descartados |
| P1 | Veículo | KM atual descartado; troca de óleo sem tela |
| P1 | Colaborador | Sem telefone nem "é entregador" na tela |
| P2 | Alçada de desconto | Aceitava papel de outra rede |
| Oportunidade | Cliente | O CEP passa a preencher cidade, bairro e rua pela própria base |

Campos que a tela pedia e **não tinham coluna** (nunca foram gravados) saíram
do formulário, com o motivo no código: competência do lançamento, KM inicial
do veículo, e-mail do colaborador, CSC duplo do certificado.

## Dados reais

Na homologação: `config_fiscais` preenchida pela carga para as 7 empresas
(ambiente de **homologação** — o legado está em produção, e copiar isso para
um ambiente de teste armaria emissão real), com a chave de tenant; conferido
pela API logado como o dono.

## Testes

- Backend, suíte integral: **1.936 passaram, 22 pulados (PostgreSQL/RLS), 0
  falhas**. Testes novos: lançamento manual, produto fiscal, configuração e
  fiscal da empresa, carga fiscal, CEP, NFC-e, alçada.
- Correções provadas contra a versão antiga (o teste falha sem a correção):
  lançamento, produto, configuração da empresa.
- SPA: typecheck limpo, build ok, 146 testes. Os testes instáveis
  preexistentes continuam (ver primeira passada). O teste novo do colaborador
  oscilou sob a suíte inteira; a mensagem capturada mostrou que checava o
  rótulo da rua antes do efeito que o preenche — passou a esperar por ele.
  Depois disso: 8 rodadas completas seguidas, 146/146.

## O que NÃO foi coberto (honestamente)

- **Prova da NFC-e/NF-e na SEFAZ**: precisa do certificado A1 do cliente.
- **Fluxos de uso diário fora dos cadastros** — Central de entregas, baixa no
  financeiro, entrada de estoque, fechamento de convênio, relatórios — tiveram
  o contrato de gravação auditado, mas **não** a análise de passos para o
  leigo que o pedido e o cliente tiveram.
- O sentido "abre em branco" foi conferido nos 5 cadastros com edição, não em
  toda tela de leitura.

---

# Terceira passada — fluxos do dia a dia (11/10/2026)

| Gravidade | Fluxo | O que acontecia | Agora |
|---|---|---|---|
| P0 | Entregas | Central, **app do entregador** e roteirizador mostravam só o número da casa | Endereço completo (conferido com dados reais: "Rua Holanda 1099, Conjunto Europa, Colônia Agrícola Jordão, Guarapuava"), itens, telefone, referência e tempo de espera |
| P1 | Financeiro | Não havia como receber/pagar uma conta pela tela | Botão na linha: conta de destino → confirmar |
| P1 | Financeiro | Conta em aberto com R$ 0,00; resumo com "—" | Valores e resumo reais (R$ 1.059.996,57 a receber, R$ 514.623,14 a pagar na homologação) |
| P1 | Convênio | Fechar o mês falhava sempre | Período sugerido e editável |
| P2 | Estoque | Entrada escondida em "Acerto" | "Entrada e saída" + botão Novo |

**Lição para a próxima auditoria:** a checagem "campo enviado e não aceito"
não enxerga "campo obrigatório nunca enviado". A segunda checagem existe agora
e foi provada contra o caso real.

**Testes:** SPA 154/154 em 3 rodadas seguidas, build ok; testes novos de
Central, recebimento, resumo, convênio (inclusive a sugestão de período com
mês curto e virada de ano); correção da Central provada contra a consulta
antiga.
