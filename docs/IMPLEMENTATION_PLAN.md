# Plano de Implementação — praticidade para usuário leigo

Pedido do dono (11/10/2026): sistema mais prático e organizado para usuários
leigos, com a **regra dos três passos** (iniciar → revisar o necessário →
concluir), **sem eliminar nada** — reorganizar e melhorar UX/UI. Protocolo:
`PROTOCOLO_UNIVERSAL_PRODUTO_V2.md`.

## Resumo da aplicação

ERP SaaS para revendas de GLP: `erp-novo/` (Laravel 12 + PostgreSQL com RLS
por tenant) e SPA React/Vite/React Query (`erp-novo/frontend`), componentes
próprios em `@/components/ui`. Homologação com dados reais da Dubena (92 mil
clientes, 400 mil pedidos). Menu declarativo em `layouts/AppShell.tsx`: 38
itens em 8 grupos, filtrados por permissão e por recurso contratado.

## Principais problemas encontrados

1. **P1 — O formulário "Novo pedido" exige campos que o servidor descarta.**
   O diálogo obriga 11 campos. O `PedidoRequest` não valida
   `condicaopagamento_id`, e o `safe()` só repassa o que foi validado. O setor
   vai como `entregasetor_id`, mas a coluna é `setor_id`. Colaborador, cidade,
   bairro, rua, número e UF de entrega **não existem** em `pedidos`: a entrega é
   no endereço do cliente. Efeito: o atendente digita 8 campos à toa, e o
   pedido do painel nasce sem condição de pagamento e sem setor (as colunas que
   alimentam financeiro e alçada).
2. **P1 — Marca "Dubena" fixa no menu** de um produto multiempresa.
3. **P2 — Painel inicial sem ação:** mostra totais históricos ("clientes
   cadastrados") em vez do que precisa de atenção hoje.
4. **P2 — Menu em jargão interno** ("MCMM", "Cupons SAT/CFe", "Geográfico",
   "Alçadas", "Bens", "Monitora"): o leigo não sabe onde procurar, e a busca só
   casa o nome exato.
5. **P2 — Criar algo exige saber onde mora:** "Novo pedido" só existe dentro
   de Pedidos; não há atalho global para as tarefas mais comuns.

## Plano de execução

| ID | PRIORIDADE | PROBLEMA | SOLUÇÃO | ARQUIVOS/ÁREAS | RISCO | TESTE | STATUS |
|---|---|---|---|---|---|---|---|
| P1-01 | P1 | Pedido do painel perde condição e setor; 6 campos inexistentes obrigatórios | `PedidoRequest` aceita `condicaopagamento_id`; atendente = quem lança; diálogo envia `setor_id` e mostra o endereço do cliente (somente leitura) no lugar dos campos que não eram gravados | `PedidoRequest`, `PedidoController::store`, `pedidos/PedidoDialogs.tsx` | Baixo: campos novos opcionais, validação por tenant | Feature (condição/setor/atendente gravados) + vitest | DONE |
| OP-01 | P1 | Pedido de gás é quase sempre "o mesmo de sempre", mas tudo é digitado de novo | Ao escolher o cliente, sugerir pelo último pedido dele: condição, operação, setor e itens; situação inicial padrão; preço sugerido (combinado com o cliente → por condição → de venda). Tudo editável. Botão "Repetir último pedido" | `GET pedidos/sugestao`, `PedidoSugestaoService`, diálogo | Baixo: só sugere; validação e alçada do servidor intactas | Feature + vitest | DONE |
| OP-02 | P2 | Painel não diz o que fazer agora | Bloco "Para hoje": pedidos em aberto, contas a receber vencendo hoje e vencidas, a pagar hoje, estoque abaixo do mínimo, cadastros a revisar — cada um com link. Mais as 3 ações principais | `GET dashboard/hoje`, `DashboardPage.tsx` | Baixo: leitura; cada número só para quem tem a permissão | Feature (permissão por bloco) + vitest | DONE |
| OP-03 | P2 | Menu em jargão, busca literal | Grupos por tarefa e rótulos em linguagem comum; descrição curta por item; busca por sinônimos ("boleto", "nota", "entregador"). Nenhum item, rota ou permissão removido | `AppShell.tsx`, `ModuleFinder.tsx` | Baixo: só rótulo/ordem | vitest do menu (todos os itens preservados) | DONE |
| P1-02 | P1 | "Dubena" fixo | Nome da empresa ativa no menu | `AppShell.tsx` | Baixo | vitest | DONE |
| OP-04 | P2 | Criar exige navegar | Botão "Novo" no topo: pedido, cliente, produto, lançamento financeiro — só o que a pessoa pode | `AppShell.tsx`, `PedidosPage` abre o diálogo por `?novo=1` | Baixo | vitest | TODO |

## Ordem de implementação

P1-01 → OP-01 (mesmo diálogo) → OP-02 → OP-03 + P1-02 → OP-04.

## Itens que não serão alterados

- Rotas, permissões, módulos e telas: tudo continua acessível; o menu só muda
  rótulo, grupo e ordem.
- `/satelites` (Relatórios & Integrações) segue fora do menu, como já estava:
  incluí-lo é decisão de produto, não reorganização.
- Regras de alçada, preço e estoque no servidor: a sugestão não decide nada.
- Limiar de "atrasado" para pedido: não existe configuração do tenant, e
  inventar um número seria regra de uma revenda. O painel mostra o pedido em
  aberto mais antigo (fato), não um "atrasado".

## Bloqueios reais

Nenhum.
