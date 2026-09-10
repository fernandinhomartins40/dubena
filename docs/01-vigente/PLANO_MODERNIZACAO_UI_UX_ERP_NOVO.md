# Plano de modernização de UI e UX — erp-novo

Data: 10/09/2026. Estado: planejamento, sem implementação de interfaces.
Base: branch `main`, commit `2b53d9fdc3b7ac4c72a25f531787afa0df260f3a`.
Escopo: landing pública, autenticação, SPA do ERP e SuperAdmin de `erp-novo`.

> ⚠️ **Revisado em 10/09/2026, depois da primeira inspeção em navegador.** A
> seção 3 foi reescrita: a implementação escureceu a marca além do necessário
> porque o gate visual nunca chegou a rodar. A seção 5 recebeu os achados da
> landing renderizada, e a seção 8 ganhou o lote U15. O restante (achados
> UX01–UX22, microlotes, gates) permanece como estava.

## 1. Direção recomendada

Modernizar a experiência a partir da identidade existente: laranja para ações, lime para destaques pontuais, grafite na estrutura e superfícies neutras. O maior ganho virá da hierarquia da informação, clareza dos estados, orientação por tarefa e consistência dos componentes.

A base React/Tailwind/Radix já permite essa evolução. Preservar os componentes úteis, o carregamento por rota, as permissões, a separação de sessões de plataforma e tenant e os fluxos operacionais consolidados. Não é necessário trocar de framework, comprar template ou introduzir outra biblioteca de componentes para executar este plano.

Resultados esperados:

- Visitante entende o produto e encontra uma próxima ação real na landing.
- Operador identifica empresa, pendências e próxima tarefa ao entrar no painel.
- Listas permitem localizar, abrir e retomar registros sem perder contexto.
- Formulários orientam preenchimento e recuperação de erros.
- Estados de falha, ausência de dados e atualização são inequívocos.
- As telas mantêm a mesma linguagem visual em desktop, celular e temas claro/escuro.

## 2. Cobertura e limites da análise

Foi realizado inventário de todos os arquivos TSX da SPA: **165 arquivos**, incluindo componentes e testes, distribuídos em **30 diretórios de funcionalidades**. Esses números não representam quantidade de telas ou testes aprovados. A navegação principal declara **36 entradas**, filtradas por acesso e recursos contratados. O anexo `UI_UX_ERP_NOVO_INVENTARIO.md` registra arquivos e indicadores estruturais para orientar a execução.

A leitura aprofundada concentrou-se na landing, rotas, tokens, shells, seletor de empresa, login, dashboard, listas/formulários compartilhados, clientes, pedidos, centrais, relatórios e composição dos módulos. Nos demais domínios, a cobertura foi de inventário e busca estrutural; as melhorias propostas para eles são diretrizes a confirmar no respectivo microlote. Não houve auditoria linha a linha de todo o backend.

**Não houve inspeção visual renderizada nem teste de usabilidade.** A ferramenta de navegador retornou inventário vazio e `No browser is available`. Não foram medidos responsividade real, contraste de todas as composições, performance, comportamento com dados de produção ou todos os percursos autenticados. As evidências abaixo são do código, com hipóteses de experiência identificadas como tal. Ausência de teste não constitui aprovação.

Fontes consultadas primeiro: procedimento contínuo SaaS, `implementacao-saas/ESTADO_ATUAL.md`, princípios do plano SaaS e `AGENTS.md`. Este trabalho atende ao pedido específico de análise e planejamento; não retoma implementação F5–F10 nem declara gates SaaS aprovados. Mudanças futuras que afetem contratos de negócio devem respeitar a ordem e os gates desse plano.

Alterações preexistentes preservadas: `erp-novo/config/cache.php`, `erp-novo/perda.sql` e `kit-auditoria/`. Nenhum arquivo de `ctrl-web/` foi analisado ou alterado. A referência ao acesso clássico existente no rodapé da landing não significa inclusão do legado no projeto.

## 3. Identidade visual: o que preservar e o que ajustar

Fontes de identidade efetiva: `erp-novo/frontend/src/index.css`, `erp-novo/frontend/PADRAO_UI.md` e `erp-novo/public/landing/index.html`.

| Elemento | Preservar | Evoluir |
|---|---|---|
| Laranja `#FF6200` | Reconhecimento da marca e acentos de ação | Combinações acessíveis de fundo/texto e estados hover/foco |
| Lime `#DBFB3B` | Destaques pontuais com texto grafite | Separar destaque de marca de sucesso operacional |
| Grafite | Sidebar, contraste estrutural, áreas institucionais | Harmonizar variações quentes da landing com o painel |
| Neutros | Branco/greige e predominância de superfícies calmas | Escala coerente de bordas, fundos e elevação |
| Tipografia | Família system-ui/Segoe UI já adotada | Escala consistente de títulos, corpo, rótulos e números |
| Ícones | Lucide na SPA | Mesma espessura e tamanhos; rótulo acessível para ação por ícone |
| Marca/nome | Ativos e nomes existentes | Explicitar relação entre Gás em Casa, Dubena e empresa ativa |
| Temas | Claro e escuro | Persistência da escolha e validação completa dos estados |

**Divergência documental confirmada:** `docs/01-vigente/IMPL_UI.md` descreve azul/roxo/amarelo e capacidades de tabela diferentes do código atual. Na implementação, reconciliar esse documento com `PADRAO_UI.md`; não restaurar a paleta antiga. Também não assumir que uma dependência instalada, como TanStack Table, significa que suas capacidades estão implementadas.

### 3.1 Revisão de 10/09/2026 — a paleta foi alterada sem o gate visual

⚠️ **Esta subseção corrige a orientação anterior, não a complementa.** A
implementação (commit `abddbcc8`) aplicou o parágrafo abaixo sobre contraste e
escureceu a marca além do necessário. A causa é registrada em
`UI_UX_ERP_NOVO_EXECUCAO.md`: o gate visual deste plano nunca rodou, por
ausência de navegador na sessão. A cor foi decidida por cálculo isolado, sem
ninguém ver o resultado.

O cálculo original estava certo e continua valendo: branco sobre `#FF6200` dá
**3,00:1** e grafite `#1F1F1F` sobre o mesmo laranja dá **5,49:1**. O erro foi de
escopo — tratou-se todo uso do laranja como se fosse texto pequeno.

**O que o WCAG realmente exige, por papel do laranja:**

| Papel | Mínimo | `#FF6200` puro | Pode usar puro? |
|---|---|---|---|
| Texto corrido (< 18,66px bold) | 4,5:1 | 3,00:1 | **Não** |
| Texto grande (≥ 24px, ou ≥ 18,66px bold) | 3:1 | 3,00:1 | **Sim** |
| Borda, ícone, indicador, gráfico | 3:1 | 3,00:1 | **Sim** |
| Superfície com texto grafite por cima | 4,5:1 (do texto) | 5,49:1 | **Sim** |

O laranja da marca só reprova em **texto pequeno sobre branco**. Escurecer a
marca inteira para resolver esse caso foi desproporcional, e é exatamente o que o
parágrafo anterior alertava para não fazer.

**Três desvios medidos na implementação:**

| Mudança aplicada | Medição | Veredito |
|---|---|---|
| `--primary-foreground` branco → grafite | 3,00:1 → 5,51:1 | **Correto.** Mantém |
| `.text-primary` → laranja L32% (`#A33F00`) | 6,44:1, quando **L38% já dá 4,91:1** | **Exagerado.** Escureceu 6 pontos além do exigido |
| `--success` lime → verde | — | **Correto** conceitualmente, mas deixou o lime órfão em 1 uso na SPA inteira |

Há ainda um erro de método a desfazer: o override foi escrito como
`@layer utilities { .text-primary { color: hsl(var(--accent-foreground)); } }`,
que sequestra **toda** ocorrência de `text-primary` — inclusive ícones, bordas e
gráficos, medidos em 3:1, onde o laranja puro já passava. Requisito de texto
aplicado a coisas que não são texto.

**Tokens vigentes após esta revisão:**

| Token | Valor | Contraste medido | Papel |
|---|---|---|---|
| `--primary` | `#FF6200` | 3,00:1 s/ branco | Marca. Superfícies, bordas, ícones, texto grande |
| `--primary-foreground` | grafite 12% | 5,51:1 | Texto sobre superfície laranja |
| `--primary-acao` *(novo)* | `#CC4E00` (L40%) | **4,50:1 c/ branco** | Só o botão de ação primária, com texto branco |
| `--primary-texto` | L38% (`#C24A00`) | **4,91:1** | Links e texto laranja sobre claro |
| `--destaque` (lime) | `#DBFB3B` | **14,26:1 sobre grafite** | Ver 3.2 |
| `--success` | verde 140° | — | Sucesso operacional, distinto da marca |

`--primary-acao` é a peça central: o botão primário recupera o texto branco — o
que o faz parecer clicável — pagando 10% de luminosidade **num componente**, em
vez de escurecer a marca inteira. Hoje, no login, "Entrar" tem texto quase preto
e parece desabilitado.

### 3.2 O lime precisa de território, não de uso pontual

O lime é a cor mais saturada da marca e hoje aparece em **um único lugar** da SPA
(variante `warning` do badge). A medição mostra o desperdício: **14,26:1 sobre
grafite**, contraste altíssimo, seguro para texto e número grande. Sobre branco
dá 1,16:1 — invisível.

Daí a regra: **o lime vive em fundo escuro, nunca sobre branco.** Isso lhe dá um
território próprio em vez de competir com o laranja pelos mesmos lugares.

| Lugar | Uso | Por quê |
|---|---|---|
| Sidebar grafite | Indicador do item ativo | A sidebar já é escura; hoje o ativo é laranja, competindo com os botões |
| KPI do dashboard | Acento `lime` do `StatCard` | Já implementado no componente, sem nenhum consumidor |
| Landing, hero grafite | Manter | É o único lugar onde o lime está certo hoje |

Nenhum componente novo é necessário.

### 3.3 Onde gastar a ousadia

Concentrar a boldness em um elemento e manter o resto disciplinado. Para um ERP
de GLP, esse elemento é **o número**.

Quem opera este sistema passa o dia com quantidade: botijões em poder do cliente,
pedidos na fila, parcelas em aberto, saldo por setor. O número é o conteúdo — não
o card que o embrulha, não o ícone ao lado. Hoje os KPIs usam `text-3xl` e têm o
mesmo peso visual de todo o resto.

Direção: o número é o elemento tipográfico da interface — grande, tabular,
tracking apertado, rótulo discreto embaixo; borda, sombra e tint recuam. É a
única licença estética que este plano pede, e ela vem do assunto, não de
tendência visual.

Corolário: sem gradiente decorativo, sem sombra colorida, sem animação de entrada
por seção. Movimento fica reservado para responder a uma ação da pessoa (abrir,
confirmar, mover), que é onde ele informa.

### 3.4 Escala tipográfica e espaçamento

Proposta, sujeita ao gate visual: corpo 14–16px, metadados 12–13px, títulos de página 24–28px, números principais 28–36px; espaçamento 4/8/12/16/24/32px; raio derivado do token atual de 10px, com 12–16px em superfícies maiores. Densidade compacta para operação e confortável para consulta. Não reduzir informação crítica para caber em um card.

## 4. Achados prioritários com evidência

Convenção: P0 = correção de entendimento/acessibilidade antes da expansão visual; P1 = núcleo da modernização; P2 = extensão e refinamento. Prioridade não significa incidente de segurança confirmado.

| ID | Evidência no código | Efeito ou risco de UX | Intervenção |
|---|---|---|---|
| UX01 · P0 | `components/ui/field.tsx`: `Label htmlFor` aponta para uma `div`, não para o controle | O componente não associa corretamente rótulo e campo | Contrato de IDs para Input, Select e AsyncSelect; `aria-describedby`, `aria-invalid`, ajuda e erro associados |
| UX02 · P0 | `DashboardPage.tsx`: ausência de `isError`, valor ausente vira `0`; `DataTable` e `ResourceList` não recebem erro | Falha pode parecer operação vazia; correção transversal necessária | Estados tipados e retry explícito; caller sempre fornece o estado da consulta |
| UX03 · P0 | `async-select.tsx`: catch transforma falha em opções vazias; resposta inesperada também vira `[]` | Usuário pode interpretar indisponibilidade como cadastro inexistente | Erro distinto, nova tentativa, resposta inválida identificada; cancelar/ignorar resposta de busca obsoleta |
| UX04 · P0 | Landing `.btn-primary` branco/laranja; tokens equivalentes na SPA | Leitura insuficiente em texto pequeno | Corrigir pares cromáticos e verificar ambos os temas |
| UX05 · P0 | `EmpresaSwitcher.tsx`: “Toda a rede” e empresa ativa são conceitos distintos; empresa única fica `hidden md:flex` | Operador pode não saber onde uma escrita será feita, especialmente no celular | Exibir separadamente “Visualizando” e “Operando em”; contexto persistente nos formulários críticos |
| UX06 · P1 | Dashboard tem quatro contadores e mensagem de boas-vindas | Home oferece pouca orientação para trabalho | Resumo por tarefa/perfil, pendências acionáveis e atalhos autorizados |
| UX07 · P0 | `RelatorioService::dashboardResumo`: `financeiro` conta parcelas a receber em aberto; UI diz apenas “Financeiro” | Contagem pode ser confundida com dinheiro; pedidos também não possuem recorte diário | Renomear para “Parcelas a receber em aberto”; explicitar contagem/período, sem aplicar formato monetário a esse valor |
| UX08 · P1 | `AppShell.tsx`: 36 entradas; grupos abertos inicialmente, preferências em estado local | Custo provável de localizar funções e recompor ambiente | Busca de módulos, favoritos, grupos persistidos e breadcrumb; validar com operadores |
| UX09 · P0 | `AppShell` e `SaLayout`: drawer por translate e backdrop, sem gestão modal explícita | Risco de foco alcançar menu escondido ou conteúdo atrás do overlay | Drawer com foco contido, Escape, retorno ao acionador, conteúdo fechado fora da navegação |
| UX10 · P1 | `FinanceiroPage`: 10 abas; `EstoquePage`: 7; `TabsList` oculta scrollbar | Funções podem passar despercebidas em telas estreitas | Agrupar por tarefa, indicar overflow e persistir aba na URL |
| UX11 · P1 | `useBusca.ts`: termo e página apenas em estado local | Voltar/recarregar pode perder investigação | Filtros, página e aba na URL; separar termo digitado de filtro aplicado |
| UX12 · P0 | `DataTable`: linha clicável usa `onClick` no `tr`, sem ação de teclado equivalente própria | Abrir registro pode depender do mouse | Link/botão nomeado na célula principal; menus de linha continuam independentes |
| UX13 · P1 | `FormDialog`: não contém form/onSubmit; `useResourceForm` expõe dirty mas não faz proteção de saída | Enter e prevenção de perda dependem de cada consumidor | Contrato de submissão, primeiro erro/aba, proteção de rascunho e feedback de sucesso |
| UX14 · P1 | `KanbanView`: drag HTML, colunas `w-80`; reordenação otimista com toast no erro | Interação por toque/teclado e recuperação de ordem precisam de validação | Alternativa “Mover para…”, modo lista, rollback visual da ordem e confirmação de efeitos preservada |
| UX15 · P1 | `RelatoriosPage`: cabeçalhos por chave de objeto e JSON em `<pre>` para resumo/DRE | Resultado usa linguagem técnica e dificulta leitura | Apresentadores tipados, nomes de negócio, BRL/datas, subtotais e drill-down |
| UX16 · P1 | Landing promete acesso de demonstração e aponta `/novo/app/`; `LoginPage` só mostra preenchimento demo em DEV com env | Jornada de descoberta sem demonstração pública comprovada | Separar acesso de cliente de conhecer/solicitar demonstração; destino comercial real antes de publicar |
| UX17 · P1 | Landing usa G/Gás em Casa; shell D/Dubena; login combina ambos | Relação entre produto e operação pode ficar ambígua | Definir hierarquia textual de marca sem trocar nomes/logos automaticamente |
| UX18 · P1 | Landing esconde navegação abaixo de 820px sem substituto; não declara main ou reduced-motion | Menor orientação em celular e navegação assistiva incompleta | Menu acessível, landmarks, skip link, offset das âncoras e movimento reduzido |
| UX19 · P1 | `SaDashboardPage`: query principal tem erro; auxiliares de empresas/auditoria caem em arrays vazios | Erro auxiliar pode parecer “nenhuma suspensa” ou “sem ações” | Estado independente em cada card, mantendo contexto Plataforma |
| UX20 · P2 | `ConvenioPage` e `SorteioPage` usam confirm nativo; outros fluxos usam ConfirmDialog | Confirmações inconsistentes | Padronizar objeto, consequência, loading e erro, sem remover confirmação crítica |
| UX21 · P1 | `LoginPage`: exceções fora de 423/429 viram credenciais inválidas | Queda de conexão/servidor pode induzir tentativas inúteis | Diferenciar indisponibilidade de credencial inválida; usar Retry-After real quando disponível |
| UX22 · P1 | `ClientesListPage`: exportação rápida envia situação, mas não o termo `q` | Alcance exportado pode divergir da busca que o usuário vê | Conferir contrato backend; alinhar filtros ou informar claramente alcance antes do download |

As centrais já usam `AsyncState` com erro nas listas, e a logística já exibe horário da posição do entregador. Preservar isso. Seus KPIs derivados de `data ?? []` ainda precisam compartilhar o estado da respectiva consulta. Não generalizar o problema para “a aplicação não trata erros”.

## 5. Landing: arquitetura e modernização

Arquivo-alvo: `erp-novo/public/landing/index.html`. `erp-novo/docker/nginx/default.conf` direciona `/` à landing e `/novo/app` à SPA; `resources/views/welcome.blade.php` é outra superfície Laravel. Validar a configuração do ambiente de publicação antes de escolher o arquivo servido. Não redesenhar o welcome por engano.

Ordem proposta:

1. **Cabeçalho:** marca existente, Produto, Como funciona, Dúvidas e “Entrar”. Menu compacto funcional no celular.
2. **Hero:** manter a proposta de gestão para gás/água e destacar o benefício principal em uma frase. Uma ação principal de descoberta com destino real; “Entrar” permanece acessível para clientes.
3. **Produto em uso:** screenshot real de uma versão homologada com dados fictícios identificados, ou mockup explicitamente ilustrativo. O mockup atual não comprova que os gráficos mostrados existem na home.
4. **Três resultados centrais:** atendimento, vasilhame e fechamento; cada um ligado a um fluxo concreto. Consolidar repetição entre seis benefícios e seis recursos.
5. **Jornada operacional:** pedido → entrega → estoque/fiscal/financeiro, com condicionais compatíveis com os fluxos efetivos. Revisar promessas absolutas de automatização, sem presumir a ordem de todos os eventos.
6. **Módulos por necessidade:** vender, entregar, controlar e administrar. Expandir detalhes sob demanda em vez de uma sequência longa de cards equivalentes.
7. **Implantação e dúvidas:** acesso, importação, treinamento, suporte, dispositivos e contratação. Publicar somente respostas verificadas; não inventar preço, prazo, número de clientes ou depoimento.
8. **CTA final:** mesma ação e expectativa do hero. Se não houver demonstração comercial funcional, usar navegação informativa até existir destino verificável.
9. **Rodapé:** identidade, contato e documentos institucionais existentes. Decidir manutenção do link clássico como item de transição sem tocar no legado.

Tratamento visual: preservar hero grafite, cores de marca e família tipográfica; reduzir efeitos decorativos que competem com o conteúdo; usar lime em realces menores; revisar a faixa lime extensa à luz da regra atual de predominância neutra. Seções com ritmo consistente, imagem de produto maior e botões sem competição de hierarquia. Não adicionar vídeo pesado, carrossel automático ou números fictícios para parecer moderno.

### 5.1 Achados da landing renderizada (10/09/2026)

Primeira inspeção em navegador do projeto, contra homologação
(`gasemcasa.com/novo/`), com a skill `browser-automation`. A estrutura entregue
melhorou de fato; o que a captura revelou são marcas de layout genérico que não
vêm da marca nem do negócio:

| Achado | Onde | Correção |
|---|---|---|
| Eyebrow em CAIXA ALTA espaçada acima de **toda** seção | `GESTÃO PARA GÁS E ÁGUA`, `MENOS RETRABALHO`, `O PRODUTO POR DENTRO`, `UMA ROTINA CONECTADA`, `ANTES DE COMEÇAR` | Remover. Onde a seção precisa de contexto, o título já diz |
| Marcadores `01/02/03` em conteúdo que **não é sequência** | Os três benefícios (atendimento, vasilhame, fechamento) são paralelos | Remover a numeração. Mantê-la só no fluxo pedido→entrega→conferência, que é sequência real |
| Metadados unidos por `·` | `Clientes · pedidos · relacionamento` | Reescrever como frase ou lista |
| Seta colada ao texto do botão | `Entrar ↗`, `Conhecer o sistema ↓` | Tirar do rótulo; seta é ícone, não palavra |
| Cards idênticos, mesmo raio e mesma sombra | Benefícios, módulos, CTA | Diferenciar por hierarquia: o mais importante ganha peso, não outro card |

Preservar o que está correto: hero grafite com lime, mockup honestamente marcado
como ilustrativo, ausência de número inventado, sem depoimento falso, sem
carrossel. A disciplina de não inventar prova comercial não deve ser desfeita ao
mexer no visual.

Gate da landing: destino de cada CTA verificado, navegação por teclado, textos e ativos revisados, layout estreito sem estouro, âncoras não cobertas pelo header, imagens com dimensões/alternativas e versão compartilhável coerente. Formulário comercial, se escolhido, exige API/destino, estados de envio/erro e confirmação de recebimento; um botão bonito sem jornada não conclui a tarefa.

## 6. Shell, dashboard e principais percursos

### 6.1 Estrutura comum

Manter sidebar grafite e topbar. Adicionar breadcrumb e busca de módulos com resultados limitados ao acesso do usuário. Favoritos opcionais, persistidos por usuário/contexto, sem revelar módulos não autorizados. Persistir tema, densidade e grupos recolhidos; não armazenar rascunhos sensíveis no navegador como solução padrão.

No cabeçalho, tornar “Visualizando: [escopo]” e “Operando em: [empresa]” compreensíveis. Na visualização de rede, deixar explícita a empresa que receberá uma criação. Em troca de empresa, apresentar estado de transição, preservar isolamento de cache e tratar formulário alterado. A proposta visual não altera grants, tenancy ou empresa de escrita por conta própria.

Rotas desconhecidas devem mostrar página não encontrada com retorno útil, substituindo o redirecionamento silencioso para home. Abas e filtros relevantes devem aceitar links diretos, voltar/avançar e recarga. Não incluir dados pessoais nos parâmetros da URL sem análise do conteúdo.

### 6.2 Dashboard do ERP

Composição proposta, priorizando dados já disponíveis:

| Região | Conteúdo | Comportamento |
|---|---|---|
| Cabeçalho | Título, empresa/escopo, período aplicável, atualização | Não aplicar filtro “Hoje” a métricas históricas sem suporte da API |
| Resumo | 3–4 indicadores relevantes ao perfil | Unidade, definição, estado e acesso ao detalhe filtrado |
| Prioridades | Pedidos pendentes, solicitações, alertas autorizados | Ordenação explicável, ação de resolução, erro isolado por bloco |
| Trabalho rápido | Novo pedido, buscar cliente, caixa, conforme permissão | Atalhos para fluxos existentes |
| Análise secundária | Evolução ou distribuição somente com dados válidos | Gráfico com tabela/descrição equivalente; não usar apenas decoração |

Atendimento prioriza pedidos e solicitações; logística prioriza fila e entregadores; financeiro prioriza vencimentos e caixa; gestão prioriza visão consolidada autorizada. São hipóteses de composição a validar com pessoas desses perfis, não novos papéis de autorização.

Separar duas entregas: primeiro corrigir rótulos/estados e adicionar atalhos com contratos existentes; depois criar agregações de indicadores aprovadas. Receita diária, margem, tendências e comparação com período anterior não estão provadas pelo endpoint atual. Cada novo KPI precisa de definição, fonte, recorte temporal, timezone, acesso e regra para ausência de dados. Zero é dado válido; falta de acesso ou falha é outro estado.

### 6.3 Listas e formulários

Listagem padrão: título/total → ação principal → busca e filtros aplicados → tabela → paginação. Filtros em chips removíveis, limpar filtros, ordenação explicitada e resumo do alcance da exportação. Evoluir DataTable incrementalmente com ordenação/paginação server-side onde o contrato existir; nunca ordenar só a página corrente fingindo ordenar todo o conjunto.

No celular, definir colunas essenciais por domínio e detalhe acessível; usar cards quando favorecem a tarefa. Tabela contábil pode continuar horizontalmente rolável, com indicação clara; a página inteira não deve rolar horizontalmente. Densidade compacta não deve reduzir área clicável e legibilidade abaixo dos critérios.

Formulário curto cabe em diálogo; cadastro longo usa página com grupos e resumo de erros. Rodapé com ação principal acessível sem encobrir campos. Ao falhar: preservar entradas, abrir aba do primeiro erro, focar o campo e explicar como corrigir. Ao cancelar/navegar com alterações: oferecer continuar editando ou descartar. Após salvar: resetar o estado dirty e confirmar o resultado real, sem modal ou confirmação adicional para tarefas simples.

### 6.4 Fluxos operacionais

- **Pedidos:** manter kanban e lista; busca/filtros em ambas; situação com texto e cor; ação por teclado/toque para mover; resumo do efeito antes de concluir/cancelar. Reordenação com falha restaura a ordem confirmada.
- **Atendimento:** localizar cliente → confirmar endereço → itens/preço → entrega/pagamento → resumo → salvar. Não forçar wizard longo em cada venda; testar fluxo compacto com operador experiente.
- **Central de Vendas:** decisão com valor original, desconto, limite/justificativa e resultado esperado. Reuso de Pós-venda/Missões com cabeçalho contextual para evitar hierarquia duplicada.
- **Logística:** fila e disponibilidade lado a lado no desktop; alternância clara no celular. Diferenciar informação atual, posição antiga e falha de atualização; manter lista útil se o mapa falhar.
- **Financeiro:** agrupar operação diária, cobrança, análise e configurações; entradas e saídas com sinal/unidade; baixar/estornar com resumo de impacto. Não remover funções por reduzir abas.
- **Estoque/comodato:** distinguir saldo, movimentação, conferência e ajuste; exibir unidade/setor, direção concedido/recebido, autoria e divergência. Reconciliar não pode aparecer como ajuste automático.
- **Fiscal:** fila por situação, última resposta, próxima ação válida e histórico; explicar rejeição de maneira legível sem esconder código técnico útil no detalhe. Não prometer emissão bem-sucedida quando só houve envio.
- **Relatórios:** catálogo por finalidade, apresentação de DRE e resumos, totais e período visíveis, exportação consistente. Eliminar JSON cru da leitura de negócio.

## 7. Cobertura dos demais módulos

Aplicar o padrão transversal e depois validar o fluxo próprio. A tabela é backlog de avaliação, não afirmação de defeito individual em cada tela.

| Domínios inventariados | Direção específica | Gate do domínio |
|---|---|---|
| clientes, cadastros, geografico | Cadastro progressivo, contatos/endereço claros, revisão e duplicidades com evidência | Buscar → abrir → editar → voltar preservando consulta; erro de lookup não vira cidade inexistente |
| produtos | Separar cadastro, preço e configuração; unidade e vigência visíveis | Alteração de preço com contexto e retorno ao produto correto |
| crm, missoes | Prioridade, responsável, prazo, próxima ação; filtros por situação | Operador conclui tarefa e encontra seu histórico |
| pagamentos, convenios, valegas | Estados de autorização/consumo/fechamento, saldo e comprovante | Ação executada uma vez, retorno inequívoco e recuperação de falha |
| gestao | Documentos, bens, cupons e MCMM organizados por tarefa; explicar sigla quando necessário | Encontrar registro, entender direção/status e abrir documento |
| rh | Resumo do colaborador e navegação previsível entre ponto/turnos/exames/comissões | Informação sensível respeita acesso e exportação; erros de aba são localizados |
| frota, satelites | Veículo, posição, histórico e cercas com contexto consistente | Mapa indisponível não bloqueia consulta textual; posição antiga identificada |
| empresas, configuracoes | Busca por configuração, agrupamento e escopo empresa/rede explícito | Usuário sabe onde a configuração terá efeito; credenciais continuam protegidas |
| acessos, seguranca | Permissões compreensíveis, ações sensíveis contextualizadas, recuperação 2FA guiada | Não ampliar privilégio ao simplificar UI; sessões e resultado da revogação claros |
| auditoria, alertas | Timeline/filtros, entidade/ator/motivo e ligação com resolução | Alerta leva à ação permitida; auditoria permanece consultável |
| superadmin | Selo Plataforma permanente, contexto do cliente em ação administrativa, estados por bloco | Sessão separada do ERP, alvo inequívoco, falhas auxiliares não viram ausência |

## 8. Execução em microlotes e prioridades

Estimativas são tamanho relativo de trabalho, não prazos garantidos: S = recorte local; M = múltiplos componentes/consumidores; L = fluxo transversal ou contrato backend. Dividir os lotes M/L antes de editar. Cada microlote contém 1–3 tarefas e só avança após validação e registro. O desenho visual pode seguir enquanto uma decisão comercial da landing aguarda definição.

| Lote | Prioridade / tamanho | Entregas (até três por microlote inicial) | Dependência | Critério de saída |
|---|---|---|---|---|
| U0 | P0 / M | Capturas baseline e cenários; reconciliar referências visuais; definições dos KPIs | Navegador e homologação para capturas | Cobertura desktop/mobile/temas e limitações registradas |
| U1 | P0 / M | Contraste/tokens; Field acessível; DataTable com abertura por teclado | U0 | Pares medidos, nome acessível e tarefa por teclado passam |
| U2 | P0 / M | Estados de query; lookup recuperável; consumidores críticos | U1 | 403/500/offline/resposta inválida distintos de vazio/zero |
| U3 | P0 / M | Drawer acessível; contexto visual/operacional; troca com rascunho | U1–U2 | Teclado/mobile e testes de isolamento sem regressão |
| U4 | P1 / M | Arquitetura/copy landing; destino CTA; layout responsivo | U0–U1; destino comercial para CTA | Percurso público completo e marca consistente |
| U5 | P1 / M | Busca/favoritos; breadcrumb e URLs; tema/densidade persistidos | U3 | Usuário encontra módulo e retoma estado após recarga |
| U6 | P1 / M | Dashboard com rótulos corretos; ações/pendências; estados por card | U2–U3, contratos de dados | Indicadores conferem com fonte e detalhe autorizado |
| U7 | P1 / L | Lista canônica de clientes; form canônico; exportação com alcance | U1–U3–U5 | Buscar → editar → voltar → exportar é consistente |
| U8 | P1 / L | Pedidos lista/kanban; criação/detalhe; mover com alternativa acessível | U2–U3–U7 | Venda, cancelamento e falha sem efeito duplicado |
| U9 | P1 / L | Central de Vendas; logística; atualização/erro de painéis | U6–U8 | Atendente/expedição resolvem fila com contexto preservado |
| U10 | P1 / L | Financeiro; fiscal; relatórios | U7; gates SaaS aplicáveis | Consultar/baixar/estornar/exportar com evidência; dividir por domínio |
| U11 | P1 / L | Estoque; comodatos; divergências/conferência | U7; contratos patrimoniais | Direção, saldo, autoria e efeito permanecem íntegros |
| U12 | P2 / L | Ondas dos demais domínios da seção 7 | U7; priorização por frequência | Cada domínio tem percurso próprio validado; até três tarefas por vez |
| U13 | P1 / M | SuperAdmin shell; estados independentes; detalhes administrativos | U1–U3 | Sem mistura de sessão/escopo; ação com alvo explícito |
| U14 | P1 / M | Regressão visual/funcional; piloto com operadores; documentação final | Lotes do escopo liberado | Aceites abaixo aprovados com evidência |
| U15 | P0 / M | Correção da paleta (seção 3): tokens, lime com território, número como elemento tipográfico, limpeza da landing | Navegador disponível — hoje há | Cada item com captura antes/depois; contraste recalculado por script |

**U15 é P0 porque corrige uma regressão visual já em homologação.** Ordem
interna, cada item revertível isoladamente e sem tocar backend, schema ou dado:

| Passo | Entrega | Gate |
|---|---|---|
| U15-a | Tokens `--primary-acao` e `--primary-texto` L38%; remover o override de `.text-primary`; ajustar `button.tsx` | Contraste recalculado; captura do login e de uma lista |
| U15-b | Lime na sidebar (item ativo) e nos KPIs pelo acento já existente do `StatCard` | Captura da sidebar e do dashboard, dois temas |
| U15-c | Número como elemento tipográfico no `StatCard` | Captura do dashboard; rótulo continua legível |
| U15-d | Landing: remover eyebrows, numeração não-sequencial, `·` e setas no rótulo (seção 5.1) | Captura da página inteira; nenhum texto novo inventado |
| U15-e | Tema escuro completo dos quatro acima | Captura em ambos os temas, larguras 390 e 1280 |

Primeira entrega funcional recomendada: U0–U3, landing U4, dashboard U6 e uma lista/formulário canônicos U7. Isso melhora a experiência pública e a entrada no produto, com base segura para expansão. Não começar redesenhando trinta módulos de forma independente.

Toda entrega deve registrar IDs UX cobertos, arquivos, cenários executados, evidência antes/depois, limitações, rollback e próximo microlote. Novos contratos de negócio precisam de tarefa correspondente no plano SaaS, em sua fase aplicável; este plano não autoriza antecipar cutover, alterar schema ou pular gates.

## 9. Gates de qualidade e medição

### Acessibilidade e responsividade

Meta: WCAG 2.2 AA no recorte entregue. Verificar contraste de texto comum ≥ 4,5:1, texto grande ≥ 3:1 e componentes/indicadores relevantes ≥ 3:1; navegação por teclado, foco visível/não encoberto, nomes acessíveis e mensagens de status. Alvos de ponteiro devem atender 24×24 CSS px ou exceção aplicável; adotar 44×44 como meta de conforto para ações frequentes no celular. Esses critérios e exceções devem ser conferidos no [W3C — WCAG 2.2](https://www.w3.org/TR/WCAG22/).

Matriz visual inicial: larguras 360, 390, 768, 1280 e 1440px; ambos os temas da SPA; zoom 200%; reflow a 320 CSS px onde aplicável. Validar menu aberto/fechado, tabelas longas, nome de empresa longo, modal alto e teclado virtual. Guardar screenshots por rota/estado. Screenshot sem interação não comprova acessibilidade.

**O gate visual deixou de ser bloqueado.** A skill `browser-automation` está
instalada e verificada contra homologação (`gasemcasa.com/novo/`): carrega a
página, captura PNG, lista erros de console e requests falhados, e avalia JS na
página. Foi assim que os achados de 5.1 apareceram. A skill `frontend-design`
está instalada para a direção estética.

Consequência prática: **nenhum lote que mexa em cor, espaçamento ou hierarquia
fecha sem captura antes/depois.** A regressão da seção 3 aconteceu precisamente
por decidir paleta sem olhar a tela — cálculo de contraste sozinho não substitui
a inspeção, como o caso do botão "Entrar" demonstrou: aprovado em 5,51:1 e, na
tela, parecendo desabilitado.

### Estados e fluxos obrigatórios

| Cenário | Resultado esperado |
|---|---|
| Primeiro carregamento e atualização posterior | Skeleton inicial; atualização identificada sem piscar conteúdo válido do mesmo contexto |
| Zero registros e busca sem resultado | Mensagens diferentes, com criar ou limpar busca quando permitido |
| Sem permissão / sem recurso contratado | Mensagem e saída adequada, sem exposição de dados ou convite impossível |
| Erro 500, conexão indisponível ou timeout | Mensagem recuperável e retry; não renderizar zero como dado |
| Troca de empresa com request em voo | Nenhum dado do contexto anterior sob rótulo novo |
| Submit duplicado / resposta lenta | Ação em progresso e proteção efetiva contra duplicação conforme backend |
| Erro 422 em outra aba | Aba revelada, foco e erro vinculados ao campo, valores preservados |
| Voltar, avançar, recarregar | Filtros/aba consistentes, sem restaurar dados de outro usuário |
| Falha após ação otimista | Estado confirmado restaurado ou reconciliado, com explicação |
| Exportação | Período/filtros/escopo conhecidos e coerentes com o resultado |

Percursos mínimos: visitante → destino CTA; login/2FA → home; busca de cliente → edição → retorno; pedido → mudança de situação; solicitação → decisão; fila → atribuição; consulta financeira → ação autorizada; estoque/comodato → detalhe; relatório → exportação; SuperAdmin → empresa → auditoria. Executar mutações somente em homologação com dados de teste.

Usar Vitest/Testing Library nos contratos compartilhados, checagem TypeScript/build e testes E2E dos percursos alterados. Preservar e executar os testes existentes de RBAC, isolamento de cache e duas abas quando houver mudança no shell/contexto. Não exigir suíte backend integral por ajuste puramente visual; ampliar quando o contrato atingido justificar. Build aprovado não substitui gate de UI.

### Performance e resultados de produto

Não há baseline medido neste trabalho. Para a landing, usar como referência de boa experiência LCP ≤ 2,5s, INP ≤ 200ms e CLS ≤ 0,1 no percentil 75, separando medições de campo de ensaios locais. Fonte: [web.dev — definição dos limiares de Core Web Vitals](https://web.dev/articles/defining-core-web-vitals-thresholds). Lighthouse isolado não certifica INP real nem aprovação em produção.

Para a SPA, medir tempo de abrir lista, pesquisar, mudar aba e concluir tarefa com volume representativo, além de carregamento inicial. Preservar lazy loading; novos gráficos devem ter custo medido. Evitar novos carregamentos globais para widgets que o usuário não pode acessar.

Registrar antes/depois: sucesso sem ajuda, tempo por tarefa, erros/retorno de formulário, perda de filtros, erros de contexto e entendimento dos KPIs. Na landing: clique de descoberta, chegada ao destino e conclusão real da solicitação, separados do login de clientes. Metas de melhoria percentual só devem ser fixadas depois do baseline; não inventar conversão atual.

Piloto proposto: ao menos um representante de atendimento, logística, financeiro e gestão, mais administração de plataforma no seu recorte. Registrar observações e falhas, não só opinião estética. Essa amostra é qualitativa e não comprova significância estatística.

## 10. Decisões em aberto e entrega segura

| Decisão | Recomendação inicial | Bloqueia |
|---|---|---|
| Relação entre Gás em Casa e Dubena | Explicitar produto/empresa mantendo nomes e ativos | Copy final de identidade, não correções compartilhadas |
| Próxima ação comercial | Separar “Entrar” de conhecer/solicitar demonstração com destino real | Publicação do CTA comercial |
| KPIs e perfis prioritários | Atender a operação diária antes de novos gráficos executivos | Novos agregados; não rótulos/estados existentes |
| Densidade padrão | Confortável na consulta e compacta opt-in nas filas/tabelas | Acabamento visual, após piloto |
| Ambiente/contas de teste | Homologação com acesso por perfil e dados representativos | Inspeção autenticada e gates E2E |

Antes de publicar, homologar cada recorte e preservar rollback por release/commit da UI. Se houver configuração de ativação gradual já disponível, usá-la; não pressupor infraestrutura de flags. Separar alterações de API/schema do ajuste visual quando possível. Deploy remoto requer destino, impacto e rollback verificados conforme as instruções do repositório.

**Concluído neste trabalho:** análise estrutural, achados localizados, inventário e plano priorizado. **Pendente:** inspeção renderizada, validação com usuários, decisões comerciais, implementação e execução dos gates. Nenhuma alteração visual, build, teste funcional, commit ou deploy foi realizado para este planejamento.
