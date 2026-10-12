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
| OP-04 | P2 | Criar exige navegar | Botão "Novo" no topo: pedido, cliente e produto (só o que abre a criação direto) — só o que a pessoa pode | `AppShell.tsx`, `PedidosPage` abre o diálogo por `?novo=1` | Baixo | vitest | DONE |

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

---

# Segunda passada — de ponta a ponta (11/10/2026)

Pedido do dono: analisar a aplicação inteira, não só as telas da primeira
passada. A primeira passada achou o defeito grave seguindo o caminho tela →
API → banco; fazer isso à mão em ~600 endpoints é inviável, então o caminho
virou **ferramenta**:

- `aceitos.php` lê, para cada rota de escrita de `api/admin`, as chaves que o
  servidor aceita (regras do método, métodos auxiliares e FormRequest).
- `contrato.js` lê, em cada pasta de tela, os campos que os formulários
  escrevem (os 5 padrões de estado levantados no próprio código: `campo()`
  192×, `({ ...f, x })` ~200×, `set()`, `value={form.x}`, `name=`) e acusa o
  campo que nenhuma rota chamada por aquela tela cita.
- **Provado antes de usar**: rodado contra a versão antiga do diálogo de
  pedido, acusou exatamente os 7 campos descartados.

Resultado: 174 arquivos de tela, 221 rotas de escrita. Os achados reais estão
abaixo; o resto foi conferido à mão e era falso positivo (rótulos de
seletores, filtros de busca, rotas montadas por variável, chaves validadas em
listas de constantes).

| ID | PRIORIDADE | PROBLEMA | SOLUÇÃO | STATUS |
|---|---|---|---|---|
| E2-01 | P1 | Lançamento financeiro descartava data, vencimento e condição: emissão "hoje" e UMA parcela vencendo hoje | Servidor aceita `vencimento` e `condicaopagamento_id` (a condição dirige parcelas, como no financeiro do pedido); tela manda os nomes certos | DONE |
| E2-02 | P1 | Produto: formulário com nomes antigos; API omitia campos fiscais; ANP/IPI/grupo fiscal/EAN tributável descartados; origens recriadas zeradas | Formulário passa a usar os nomes das colunas; API devolve tudo o que ele edita; servidor aceita os campos | DONE |
| E2-03 | P1 | Seletor "Grupo fiscal" era lista fixa com ids 1..4 — não são grupos de ninguém | Lista os grupos fiscais reais; servidor recusa id que não seja grupo. Feito ANTES de aceitar o campo | DONE |
| E2-04 | P1 | Salvar a configuração da empresa falhava SEMPRE (422), reproduzido na homologação | A aba manda só o que mudou, com os nomes aceitos; 4 chaves que a tela oferecia entram na lista | DONE |
| E2-05 | P0 | A configuração que a emissão de nota lê (`config_fiscais`) estava VAZIA nas 7 empresas e nenhuma tela a gravava | `GET/PUT /empresas/{id}/fiscal` + tela; ETL preenche (fora de produção, "produção" vira homologação — não arma emissão real num teste); chave de tenant | DONE |
| E2-06 | P1 | Operação fiscal: "Movimenta estoque/financeiro" sempre desmarcados e descartados | Nomes das colunas | DONE |
| E2-07 | P1 | Veículo: KM atual descartado; "KM inicial" sem coluna; KMs de troca de óleo sem tela | Nomes certos; Renavam e KMs de óleo entram (o aviso de troca depende deles) | DONE |
| E2-08 | P2 | Alçada de desconto aceitava papel de outra rede | Papel restrito ao grupo | DONE |
| E2-09 | P1 | Colaborador: sem telefone nem marca "é entregador" na tela; e-mail sem coluna | Campos entram; e-mail sai | DONE |
| OP-05 | P2 | CEP digitado não preenchia nada | `GET /geo/cep/{cep}` pela própria base (ruas e clientes); preenche só o vazio | DONE |
| E2-10 | P0 | **A NFC-e real falharia na assinatura**: o NFePHP exige CSC/CSCid para o QR Code da NFC-e e eles não iam ao `Tools`; o modelo 65 também não era informado (o envio iria ao serviço da NF-e) | CSC, CSCid e modelo levados ao `Tools`; sem CSC, erro dizendo onde cadastrar. Quase o declarei "bloqueado": o que depende de terceiro é só a PROVA na SEFAZ (certificado A1 do cliente) | DONE (prova na SEFAZ pendente) |
