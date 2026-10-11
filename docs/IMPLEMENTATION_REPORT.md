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
