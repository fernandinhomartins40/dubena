# Execução UI/UX — erp-novo

Autorização: implementar 100% do plano, em 10/09/2026. Base inicial: `main`, `2b53d9fdc3b7ac4c72a25f531787afa0df260f3a`.
Plano: `PLANO_MODERNIZACAO_UI_UX_ERP_NOVO.md`. Este diário não substitui gates SaaS nem sua ordem de dependência.

## Checkpoint atual

Estado: IMPLEMENTANDO. Microlotes 01–18 implementados. Build e testes locais aprovados; entrega Git na main autorizada pelo usuário em 10/09/2026. U0/U14 permanecem abertos: capturas, inspeção visual e piloto dependem de navegador/homologação. Não há certificação de 100% do plano, e as ondas específicas dos domínios ainda têm trabalho conforme lista abaixo.
Preexistentes preservados: `erp-novo/config/cache.php`, `erp-novo/perda.sql`, `kit-auditoria/`, documentos do planejamento anterior. `ctrl-web/` intocado.

## Microlote 01 — associação dos campos e abertura por teclado

- UX01/UX12: Field registra IDs individuais dos controles do design system, associa nome/erro/ajuda/obrigatoriedade via ARIA e direciona o clique do rótulo ao primeiro controle. Suporta wrappers, SelectTrigger e seletores assíncronos. Campos compostos recebem IDs distintos.
- DataTable mantém estrutura de tabela e oferece botão Abrir na primeira célula quando existe onRowClick. Botão funciona por teclado sem propagar clique e disparar a ação duas vezes.
- Fontes reconfirmadas: Field, Input, Textarea, Select, AsyncSelect, AsyncMultiSelect, DataTable, Label, Button e consumidores existentes de Field/DataTable inventariados no plano. O recorte preserva interfaces de propriedades públicas.
- Validação: `npm test -- --run src/components/ui/accessibility.test.tsx`: 3/3; `npm run lint`: aprovado.
- Limite: não certifica leitor de tela/navegador real; controles nativos ou componentes externos ao design system exigem adaptação no seu consumidor.
- Rollback: reverter apenas o diff deste recorte e seus novos arquivos; nenhuma alteração de dados/schema.
- Próximo: contrato recuperável de carregamento, erro e lookup; depois tokens e shell.

## Microlote 02 — contrato de erro e seletores

- UX02/UX03: DataTable/ResourceList agora aceitam erro e retry. AsyncState oferece mensagem acessível e ação de recuperação.
- AsyncSelect e AsyncMultiSelect compartilham useLookup: validam o formato, distinguem vazio de falha, cancelam requests obsoletos e ignoram respostas atrasadas.
- Validação: 6/6 testes focais (acessibilidade + lookup), incluindo corrida entre buscas e recuperação após offline; TypeScript verificado.
- Próximo: conectar estados aos consumidores, sem considerar a mera existência das props como resolução de todas as telas.
- Rollback: diff dos componentes/hooks; nenhum efeito no backend ou dados.

## Microlote 03 — navegação e drawer

- AppShell/SaLayout usam ResponsiveSidebar: diálogo Radix no mobile, fora do DOM quando fechado, Escape/foco modal e retorno ao acionador; sidebar em fluxo no desktop.
- Busca de módulos com normalização de acentos, favoritos e grupos/expansão persistidos por usuário; somente itens autorizados entram no buscador e nos favoritos.
- Breadcrumb por rota e skip link; grupos com aria-expanded e nomes mais legíveis. Selo Plataforma preservado.
- Validação: 28/28 testes de navegação, RBAC, cache e duas abas. A suíte existente de duas abas emite AggregateError de requests jsdom, embora as assertions passem; isso não é prova de conectividade/homologação. TypeScript executado.
- Aberto: validar drawer em navegador/teclado real, tema/densidade e estado operacional da empresa.
- Rollback: apenas diffs dos dois shells e componentes auxiliares. Sessões/rotas API não alteradas.

## Microlote 04 — home e endereço inválido

- Dashboard com hierarquia de resumo/trabalho, atalhos limitados às permissões, atualização explícita e horário da consulta.
- Contagens recebem rótulos/unidades corretos, resumo incompleto é erro, falha não vira zero. Sem inventar dados financeiros ou recorte diário.
- Rota desconhecida mostra 404 com retorno ao início.
- Validação: 2/2 testes de dashboard (semântica/permissão e erro), TypeScript executado.
- Aberto: pendências por perfil/novos indicadores, contratos de período e piloto visual.
- Rollback: DashboardPage e rota 404; nenhuma API ou dado alterado.

## Microlote 05 — landing, legibilidade e preferências

- Landing reorganizada em hero, três benefícios, módulos por tarefa, fluxo, dúvidas e CTA. Mantém marca Gás em Casa, laranja/lime/grafite; mockup explicitamente fictício. Entrada e descoberta separadas; nenhuma demonstração pública inventada.
- Navegação móvel por details com fechamento em link/Escape, main/skip link, âncoras, reduced-motion e estilos responsivos. CTA comercial permanece dependente do destino solicitado ao usuário.
- Texto sobre primary passou a grafite, ação textual usa derivação escura, sucesso tem token próprio distinto de lime. Tema respeita sistema e persiste escolha; densidade por usuário nos dois shells. Metadados de KPI sem redução extra de opacidade, títulos de página não truncam.
- Validação: landmarks/links internos/fechamento do menu em jsdom aprovados; TypeScript executado. Uma primeira tentativa de comando node falhou por quoting PowerShell, corrigida via stdin. Não equivale a captura visual nem medição CWV.
- IMPL_UI recebe aviso explícito de substituição da identidade histórica.
- Rollback: landing, tokens e preferências; sem publicação/build de produção ainda.

## Microlote 06 — formulários e descarte

- FormDialog agora usa form/onSubmit, descrição acessível, footer persistente e confirmação de descarte em alterações de controles DOM; `dirty` explícito permite cobrir seletores personalizados. Fechamento bloqueado durante loading.
- Button tem tipo button por padrão; formulários existentes de busca/login/mapa usam submit explícito. Confirmação descreve a consequência e não fecha enquanto executa.
- useResourceForm não sobrescreve cadastro alterado em refetch do mesmo registro, redefine baseline após sucesso e protege reload/fechamento via beforeunload.
- Validação: 5/5 testes de formulário/acessibilidade, TypeScript executado.
- Aberto: proteção de navegação interna e detecção de dirty em controles customizados de cada consumidor; não declarar UX13 totalmente resolvido.
- Rollback: componentes e hook; sem mudanças de schema/API.

## Microlote 07 — relatórios legíveis

- ReportResult apresenta resumos em indicadores e grupos/detalhes em tabelas, incluindo receitas/despesas da DRE; preserva colunas presentes em linhas posteriores e não infere somas. Moeda/datas/quantidades com semântica explícita e ausência como travessão.
- RelatoriosPage separa filtros em edição da consulta aplicada. Consultar novamente faz refetch; exportação fica bloqueada enquanto o resultado corresponde a filtros anteriores. Datas iniciais locais, validação de período e erro recuperável.
- Validação: 3/3 testes de apresentação (DRE negativa, quantidade/ausência/documento, colunas heterogêneas), TypeScript aprovado.
- Fontes: RelatoriosPage/api e shapes do RelatorioService (vendas, financeiro, DRE e demais retornos). Sem alteração de cálculo financeiro.
- Limite: rótulos desconhecidos são humanizados, não descartados; validação com todos os relatórios e dados de homologação permanece aberta.
- Rollback: página e apresentador, preservando API/exportação.

## Microlote 08 — retomada de consultas e abas

- useBusca preserva termo aplicado/digitado e página na sessão, escopados por usuário, empresa operacional, filtro, rota/aba e escopo opcional. CPF/telefone pesquisados não entram na URL. Outra pessoa/contexto não herda o estado.
- Tabs aceita urlKey opt-in, valida a aba contra os triggers disponíveis, preserva outros parâmetros e histórico. Pedidos, estoque, financeiro e fiscal adotados; barra de rolagem das abas visível.
- Campo de busca passa a ter nome acessível e tipo search.
- Validação: 4/4 testes de sessão/isolamento e URL de abas; TypeScript executado.
- Aberto: filtros específicos das páginas, navegação interna com rascunho e adoção das abas nos demais domínios.
- Rollback: hook/tabs e opt-ins das quatro páginas. Preferências armazenadas são apenas dados de apresentação/pesquisa da sessão.

## Microlote 09 — rascunhos e troca de contexto

- Router de dados mantém as rotas/basename e habilita bloqueio de navegação interna quando useResourceForm tem alterações. Cancelar preserva o formulário; salvar libera a saída. Mudanças de aba na mesma página permanecem disponíveis.
- Seletor distingue visualização de empresa operacional, solicita descarte e remonta o conteúdo após troca concluída. Falha na troca preserva rascunho e proteção.
- Revisão independente somente-leitura identificou limpeza prematura da proteção em ação assíncrona. Corrigida e coberta por teste de falha seguida de nova tentativa.
- Validação: 2 testes do guard + 6 de duas abas aprovados; TypeScript aprovado. Duas abas mantém stderr de requests jsdom já registrado. Não certifica isolamento de runtime/cutover SaaS.
- Rollback: provider/router, integração do hook, seletor e chave do conteúdo no shell; sem mudança de API/schema.

## Microlote 10 — clientes e produtos: consulta e correção

- Listas de clientes/produtos conectam erro e retry ao DataTable e não mantêm título de carregamento infinito após falha. Exclusão/reativação malsucedida mantém confirmação aberta.
- Situação de clientes é recuperável pela URL; CSV informa antes do download o alcance real do endpoint (situação e escopo, todas as páginas, sem aplicar busca). Contrato backend preservado.
- Cadastro de cliente indisponível não abre formulário vazio editável. Erros 422 levam à aba correspondente, focam controle inválido e exibem resumo completo; associações de erro estendidas a todos os Fields. Refetch não sobrescreve os rótulos alterados.
- Validação: 5/5 testes focais de formulário e acessibilidade; TypeScript aprovado. Teste adicional de cache do lote 09: 8/8 aprovado.
- Fontes reconfirmadas: listas, formulário, API de clientes, AsyncSelect/Field; contrato de exportação levantado na auditoria. Rollback limitado aos diffs deste recorte.
- Próximo: Kanban utilizável sem arraste e recuperação de consultas operacionais.

## Microlote 11 — pedidos acessíveis sem arraste

- Cada cartão tem abertura por teclado e menu Mover para, usando o mesmo contrato/confirmador das transições por arraste. Colunas têm reordenação pelo menu; falha restaura a ordem anterior.
- Confirmação de transição/exclusão fica aberta em falha; ação duplicada durante request fica desabilitada. Efeito operacional mantém explicação de estoque/financeiro. Cor selecionada tem estado ARIA e diálogo detecta alterações nos seletores.
- Kanban e lista têm erro recuperável; filtro de situação da lista recebe rótulo acessível.
- Validação: 2/2 testes (abertura por Enter, confirmação antes do PUT, falha preserva diálogo e erro não vira quadro vazio); TypeScript aprovado.
- Limite: teste local não efetiva venda nem certifica máquina de estados de produção. Rollback nos componentes de pedidos, sem alteração de API/regra de transição.
- Próximo: estados independentes dos painéis operacionais e da plataforma.

## Microlote 12 — painéis de operação e plataforma

- StatCard distingue loading/erro de número válido. Vendas e logística conectam o estado da consulta correspondente a cada indicador; vendas mostra horário da consulta e identifica a solicitação mais antiga pelo timestamp.
- Seções auxiliares de planos, suspensões e auditoria da plataforma têm recuperação própria. Uma falha não vira ausência normal. Blocos operacionais possuem retry; ações concorrentes/desfechos prematuros são evitados durante requests.
- Cartões de vendas/entregadores acomodam quebra no mobile; endereço logístico deixa de truncar. Botão de bloqueio tem nome acessível e falhas de prioridade/bloqueio recebem feedback. Configuração indisponível não pode ser salva como defaults.
- Validação: 3/3 testes de falha parcial dos painéis; TypeScript aprovado após corrigir opção `exact` sem suporte no tipo de consulta do teste do lote anterior. Sem alterações em cálculos/API.
- Próximo: acesso/2FA e confirmações nativas remanescentes; listas de financeiro/fiscal/estoque.

## Microlote 13 — acesso e segundo fator

- Login operacional e de plataforma distinguem falha de conexão, credencial, sessão expirada e indisponibilidade. Prazo de nova tentativa aparece apenas quando fornecido pelo Retry-After do servidor; sem minutos inventados.
- Erros persistem no formulário com anúncio acessível. Segundo fator aceita código de recuperação alfanumérico, autocomplete e obrigatoriedade, com ação Verificar e entrar. Armazenamento indisponível da preferência de e-mail não transforma autenticação bem-sucedida em falha.
- Plataforma usa linguagem de administração, preserva identidade e melhora contraste das legendas.
- Validação: 2/2 testes da classificação de falhas/Retry-After; TypeScript aprovado. Autenticação/guards e armazenamento dos tokens permanecem com seus contratos existentes.

## Microlote 14 — confirmações de convênio, sorteio e cupom

- As três confirmações nativas remanescentes foram substituídas por diálogos acessíveis, com identificação do registro, consequência e estado de execução. Falha mantém o diálogo e o contexto para recuperação.
- As listas recebem erro/retry. Seletores customizados entram na detecção de descarte; itens de cupom têm rótulos individuais, remoção nomeada e grade responsiva.
- Validação: 3/3 cenários confirmam ausência de POST antes da confirmação e preservação do diálogo após falha; TypeScript das alterações de produção aprovado. Nenhuma emissão/fechamento/sorteio real executado.
- Próximo: estados e formulários dos seis fluxos de estoque.

## Microlote 15 — consultas e documentos de estoque

- Seis abas de consulta distinguem falha e permitem retry. Transferência, requisição, inventário e físico usam formulário modal com descarte explícito, proteção durante gravação e reset ao iniciar novo documento.
- Efetivação do físico identifica o registro e explica o ajuste do saldo antes da ação. Remoção de itens recebe nome acessível.
- Validação: 7/7 testes (seis consultas offline e descarte de itens), TypeScript aprovado após remoção de três imports não utilizados. Suíte integral iniciada para verificar o conjunto transversal.
- Próximo: estados financeiros e fiscais; concluir revisão integrada, build e entrega Git solicitada.

## Microlote 16 — consultas financeiras e fiscais

- Lançamentos, cheques, boletos e notas fiscais recebem erro/retry; resumo financeiro/PIX não converte falha em configuração ausente. Cheques distinguem suas subabas na URL/sessão.
- DRE e conciliação separam período digitado do aplicado, mostram intervalo consultado e permitem executar novamente; período invertido não dispara consulta. NF-e recupera paginação da sessão e NF de entrada protege seleção de setor no descarte.
- Validação: teste focal da DRE aprovado (editar período não consulta até confirmar; repetição atualiza), TypeScript aprovado. Suíte integral anterior aos últimos ajustes: 23 arquivos / 98 testes aprovados, com stderr esperado de ErrorBoundary e requests jsdom já descritos.
- Próximo: revisão de compatibilidade de seletores, consultas do caixa e build final.

## Microlote 17 — compatibilidade e caixa

- Lookup aceita campo de rótulo explicitamente configurado: bancos usam `descricao`, conforme CadastroApoioController. O contrato padrão continua recusando respostas sem label; não há fallback silencioso por adivinhação.
- Caixa separa falha da conta/movimentos e bloqueia abertura/fechamento duplicado durante a ação. Abas ativas usam derivação textual legível do laranja.
- Validação: 3 testes de lookup aprovados e TypeScript aprovado; contrato de banco confirmado no controller. Revisão identificou dívida anterior no cadastro de cheques (nomes de campos/rota de situações divergentes do controller); não certificada como resolvida por este recorte.

## Microlote 18 — pendências na home e verificação integrada

- Home apresenta até cinco alertas pendentes com responsável, situação e prioridade; o componente/query só monta para `alerta.view`, com ligação para a central autorizada.
- Build Vite aprovado: 1.956 módulos; CSS 46,89 kB (8,80 kB gzip), chunk compartilhado UI 515,63 kB (163,85 kB gzip). Mantido lazy loading; aviso de chunk acima de 500 kB é pendência de performance, não ocultado.
- Comando de build inicial via `npm run build -- --outDir ...` perdeu a flag no repasse do npm/PowerShell e falhou por entrypoint. Corrigido por `node node_modules/vite/bin/vite.js build --outDir C:/Users/fusea/AppData/Local/Temp/erpnovo-ui-build-20260910-1838`. Output isolado, sem apagar public/app.
- Suíte integral: 24 arquivos, 99 testes aprovados antes do teste adicional de pendências. Focal atualizado da home: 3/3, incluindo ausência de request sem permissão. TypeScript e diff sem erros de whitespace verificados. Stderr de ErrorBoundary proposital e requests jsdom descrito acima.
- Push: destino `origin` = `https://github.com/fernandinhomartins40/dubena.git`, branch main, sem divergência no fetch. CI bem-sucedido aciona apenas homologação (`ENV_HOMOLOG`, porta 3120); produção exige workflow_dispatch com release/rollback explícitos. Reversão do código UI pode ser feita por revert do commit, sem schema/dados alterados por este trabalho.

## Pendências de implementação e aceite — não marcar como concluídas

- U10/U12: completar propagação de erro/retry nos consumidores restantes e percursos próprios de cada domínio; reconciliar contrato de cheques, abas auxiliares de financeiro/fiscal e formas personalizadas ainda sem dirty explícito.
- U8/U11: validar criação/detalhe de pedido, ajustes/conferência de estoque e comodato com cenário representativo, preservando autoria/direção/efeitos; mudança dos componentes compartilhados não certifica esses fluxos.
- U3: prova em navegador/runtime da troca de empresa com requests em voo; testes locais do guard não substituem gate de tenancy.
- U0/U1/U14: capturas desktop/mobile/temas, teclado real, zoom, matriz completa de contraste, desempenho e piloto por perfil. CUA retornou ausência de navegador disponível nesta sessão.
- U4: destino comercial e hierarquia final de marca ainda aguardam definição; landing usa descoberta de recursos e login existentes.
- Retomada: conferir o commit remoto/CI; seguir pelas consultas auxiliares do financeiro e contrato de cheques antes de ampliar U12. Não tratar a entrega Git como conclusão integral do plano.

## Microlote 19 — contrato de cheques e datas civis

- Push anterior confirmado em `origin/main`: `abddbcc804ecd99d47d6d715bc0860a6cbcc4d0d`, sem branch adicional. Arquivos preexistentes permaneceram fora do commit.
- Cadastro/lista de cheques passam a usar numero, conta_corrente, titular e bom_para do controller atual. Payload restrito aos campos aceitos; situação inicial em carteira é definida pelo backend, e o formulário deixa de chamar a rota inexistente de situações.
- Teste revelou data civil um dia anterior no Brasil. Formatador trata YYYY-MM-DD como calendário local, preserva tratamento de instantes completos e identifica data inválida. Data bom_para usa a parte civil do cast recebido.
- Revisão independente somente-leitura dos quatro arquivos contratuais não encontrou perda de dados/regressão introduzida. Testes cobrem POST exato e apresentação da data/número, além do formatador.
- Próximo: finalizar estados das consultas auxiliares financeiras/fiscais e reenviar o checkpoint validado à main.

## Microlote 20 (U15) — a cor volta, com o gate visual rodando

Primeira entrega deste plano com inspeção em navegador. A skill
`browser-automation` foi verificada contra homologação e usada em todos os
passos; `frontend-design` orientou a direção estética. O que U0/U1/U14 listavam
como bloqueado por ausência de navegador deixou de estar.

- **U15-a — tokens.** `--primary` volta a ser `#FF6200` puro: ele veste ícone,
  borda e indicador, medidos em 3:1, onde passa. Token novo `--primary-acao`
  (`#CC4E00`) só no botão primário, com texto branco a **4,50:1** — medido como
  o teto exato: L41% já cai para 4,32 e reprova. `--primary-texto` a L38%
  (**4,91:1**) substitui o L32% (6,44:1), escuro além do exigido.
  O override de `.text-primary` em `@layer utilities` saiu: ele aplicava
  exigência de texto a ícone e borda. Auditados os 33 usos de `text-primary` —
  a maioria é ícone e permanece em laranja puro; os 9 que são texto pequeno
  passaram a `text-marcaTexto`.
- **U15-b — o lime ganha território.** Item ativo da sidebar era bloco laranja
  sólido (5,51:1), competindo com os botões pela mesma cor. Vira barra lime +
  texto branco: **14,26:1** e **16,56:1** sobre a sidebar. No `StatCard`, o
  acento `lime` era `bg-lime/25` sobre card branco — um tint de luminância 0,95,
  indistinguível do fundo. Passa a superfície sólida com grafite (14,26:1).
- **U15-c — o número.** KPI vai de `text-3xl` para 2,75rem com tracking
  negativo e leading colado. É a única licença estética do plano, e vem do
  assunto: quem opera revenda passa o dia com quantidade.
- **U15-d — landing.** Removidos os 5 eyebrows em caixa alta, a numeração
  01/02/03 dos benefícios (que são paralelos, não sequência — o `<ol>` do fluxo
  pedido→conferência é sequência real e foi preservado), os `·` dos metadados
  (reescritos como frase) e as setas coladas ao rótulo dos botões. O botão da
  landing tinha o mesmo defeito do login e recebeu `--orange-acao`.
- **U15-e — tema escuro.** Medido separadamente, e o resultado inverte o claro:
  sobre `#141414` o laranja puro já dá **6,44:1** como texto, e no botão o
  grafite (5,81:1) ganha do branco (2,85:1). Por isso a ação no escuro fica na
  marca, sem derivação.

**Evidência visual.** Login antes: "Entrar" com texto quase preto, lendo como
campo desabilitado — aprovado em 5,51:1 pelo medidor e morto na tela. Depois:
texto branco, lê como botão. Landing e KPIs capturados nos dois temas; o card de
pedidos com lime sólido salta, e os três papéis do laranja coexistem na mesma
tela (ícone puro, botão `#CC4E00`, link `#C24A00`).

**Validação:** `tsc` limpo; Vitest **103/103**; PHPUnit do recorte **110/110**.
Duas execuções intermediárias do Vitest acusaram 2 falhas por timeout de 5s
nesta máquina — reexecutadas, passam; é lentidão local, não regressão.

**Limite:** medição de contraste e captura não substituem o piloto com
operadores (U14), que segue aberto. Não foram feitas capturas em 390px nem zoom
200% — a matriz completa da seção 9 continua pendente.

**Rollback:** cada passo é isolado — tokens, sidebar, `StatCard` e landing
revertem separadamente. Nada toca backend, schema, permissão ou dado.

## Microlote 21 (U15-f) — sidebar: hierarquia, ritmo e um só item ativo

Pedido do dono a partir de captura da sidebar em produção. Nenhuma
funcionalidade foi alterada, removida ou escondida: os 36 itens, os grupos, o
ModuleFinder, favoritos, tooltips do modo recolhido, permissões e `feature`
continuam exatamente como estavam.

**Defeito funcional encontrado na captura.** Dois itens apareciam ativos ao
mesmo tempo — "Clientes" e "Cadastros a revisar". Causa: `/clientes/revisoes`
começa com `/clientes` e o `NavLink` só usava `end` na home, então o pai casava
por prefixo. Corrigido com `prefixosDeOutroItem()`, que deriva do próprio NAV
quais caminhos precisam de correspondência exata — item novo entra e a regra
segue valendo, sem lista manual. Três testes novos, com a regressão plantada:
zerando a função, dois deles reprovam.

**Grupo órfão.** "Alçadas de desconto" estava no grupo `Configurações`, ausente
do `ORDEM_GRUPOS` — caía sozinho no fim do menu, longe da Central de Vendas que
ele governa. Passou para `Operações`.

**Hierarquia visual.** O título de seção e o item de menu competiam pelo mesmo
peso. O título recua (10px, tracking 0.12em, cor a 60%) e o item lidera (13px,
h-9). O chevron migrou para antes do rótulo, onde indica direção em vez de
enfeitar a borda.

**Ritmo vertical.** O respiro entre GRUPOS (mt-6) passou a ser maior que o
respiro entre ITENS do mesmo grupo (gap-0.5). É essa diferença que agrupa aos
olhos — nenhuma divisória foi necessária no modo expandido. No recolhido, onde
não há título, os grupos ganham divisória, porque ali o espaço sozinho não
distingue.

**Estados.** Ativo: fundo a 11%, peso semibold, ícone lime e barra lime de 3px
ancorada na borda da sidebar. Hover: fundo a 5,5%. Foco: anel lime com offset —
antes não havia indicação visível de foco por teclado. A barra fica fora do
fluxo, então ícone e rótulo não deslocam entre estados e a lista não "pula".

**Alinhamento.** Caixa fixa de 18px para todo ícone: os rótulos passam a alinhar
numa coluna só, independente do glifo.

**Campo de busca.** Tinha fundo claro dentro da sidebar escura. Passa a
`bg-white/5` com borda sutil, placeholder legível e anel de foco lime.

O `SaLayout` recebeu a mesma anatomia — o selo Plataforma continua distinguindo
a sessão.

**Validação:** `tsc` limpo; Vitest **106/106** (103 + 3 do indicador ativo);
regressão plantada e detectada. Capturas do modo expandido e do recolhido
conferidas em navegador.

**Limite:** não foram feitas capturas em 390px nem com zoom 200% — a matriz da
seção 9 segue pendente, e o drawer mobile não foi exercitado com teclado real.

**Rollback:** diff dos dois shells e do ModuleFinder; nada toca backend, schema,
permissão ou dado.

## Microlote 22 (U15-g) — landing: arte de marca e correção responsiva

- A landing em `public/landing/` recebeu a identidade visual fornecida para a
  marca: logo oficial no cabeçalho, fundo de hero e a arte do entregador com
  botijão, notebook e celular. O mockup genérico foi removido; a legenda mantém
  que as telas são ilustrativas e que os recursos dependem de acesso e
  configuração.
- O hero foi reestruturado sem alterar os destinos seguros existentes: acesso
  continua em `/novo/app/`, e a descoberta segue por âncoras locais. A imagem
  usa o asset PNG original fornecido, sem recorte, remoção de fundo, IA ou
  conversão de formato.
- Corrigido um defeito introduzido durante a edição: os três `@media` não
  fechavam seus blocos, invalidando o CSS responsivo. Os blocos agora estão
  balanceados e incluem layout de uma coluna abaixo de 960px e tipografia/logo
  ajustadas abaixo de 640px.
- Validação: servidor local respondeu 200 para landing e asset; uma checagem
  estrutural confirmou um único bloco `<style>`, 122/122 chaves CSS e referências
  dos três assets existentes.
- Correção posterior à captura: o servidor expõe a landing na raiz `/`, mas os
  arquivos vivem em `public/landing/img`. URLs relativas viravam `/img/...` e
  retornavam 404. As três referências usam `/landing/img/...`, agora com rota
  Nginx explícita e cache imutável; o fundo publicado foi renomeado para
  `bg-landing-dubena.png` e tem hash idêntico ao `bg_landing_dubena.png`
  indicado pelo dono.
- Diagnóstico na VPS após a entrega confirmou uma segunda fronteira: o Nginx
  do host encaminhava somente `/` e `/novo/` ao container, portanto
  `/landing/img/...` retornava 500 publicamente embora respondesse 200 em
  `127.0.0.1:3120`. Foi adicionada ao vhost `gasemcasa.com` a regra explícita
  `location ^~ /landing/img/ { proxy_pass http://127.0.0.1:3120; }`, com
  `nginx -t` e reload. Fundo, logo e mascote foram conferidos na URL HTTPS com
  `200 image/png`. Backup reversível do vhost foi salvo em `/root/` fora de
  `sites-enabled`, evitando novo servidor duplicado.
- Correção de procedência: o asset canônico do mascote é o PNG RGBA fornecido
  pelo dono como `mascote_hero.png`; ele foi movido para
  `public/landing/img/mascote-hero.png` e é usado diretamente pelo hero, sem
  recorte, remoção de fundo, IA ou conversão (SHA-256
  `47DAE9EBFB11E7E321005EC67AE16230B4F66742D0C95345FFFC5CF48768BDDF`).
- Limite: a sessão não expõe navegador para captura ou inspeção em 390px/zoom;
  esses itens de U0/U1/U14 permanecem abertos. Uma edição de IA que devolveu
  PNG RGB com quadriculado opaco foi descartada, sem substituir o asset usado.
- Rollback: reverter `public/landing/index.html` e remover apenas os três
  assets adicionados neste microlote; nenhum backend, schema, permissão ou dado
  foi alterado.

## Microlote 23 (U15-h) — landing: cena contínua do hero

- A composição do primeiro bloco foi alinhada à referência: o cabeçalho ficou
  transparente sobre o hero, e o próprio hero sobe sob os 76px da navegação.
  Portanto fundo, marca, navegação e conteúdo leem visualmente como uma única
  cena; ao sair dela durante a rolagem, o cabeçalho recebe fundo grafite para
  preservar contraste e legibilidade.
- A transição para benefícios deixou de ser uma linha reta. Uma curva clara
  fecha a cena, descendo no centro como na referência. O PNG RGBA canônico do
  mascote/notebook fica acima dessa camada e atravessa a curva, com espaço
  reservado no início da próxima seção; não houve recorte, alteração ou nova
  geração do asset.
- A faixa textual inferior que mantinha o hero visualmente retangular foi
  removida da composição. Os mesmos destinos de navegação e as âncoras foram
  preservados.
- Validação: `git diff --check`; CSS com chaves balanceadas; as três
  referências `/landing/img/` existem no diretório público; verificados os
  marcadores estruturais de cabeçalho transparente, sobreposição de 76px,
  curva e margem negativa da arte. Não há navegador exposto nesta sessão para
  captura visual local ou matriz 390px/zoom.
- Rollback: reverter apenas `public/landing/index.html`; não toca backend,
  schema, permissões, dados nem os assets fornecidos.
