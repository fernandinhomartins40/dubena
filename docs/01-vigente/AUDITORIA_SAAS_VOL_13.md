# AUDITORIA SaaS — Volume 13 — SPA React

> **Status:** FECHADO — leitura integral (100%)  
> **Recorte:** `erp-novo/frontend/src/**` e arquivos próprios de configuração/entrada da SPA  
> **Cobertura:** **221/221 arquivos; 23.192/23.192 linhas (100%)**  
> **Método:** leitura sequencial do início ao fim de cada arquivo, seguida de buscas de reconciliação; sem amostragem. Documentos existentes não foram usados como evidência dos achados.  
> **Exclusões:** `node_modules/`, `dist/`, `package-lock.json` e `tsconfig.tsbuildinfo` (dependências/artefatos gerados); `PADRAO_UI.md` (documentação, não código).  
> **Data:** 2026-08-25

## Resultado executivo

A SPA está funcional e o recorte compila/testa, mas ainda não está segura como fronteira de apresentação multi-tenant. O achado mais grave é o cache React Query compartilhado entre sessões: as chaves não carregam identidade do tenant/empresa e os dois logouts apenas substituem a chave de autenticação, sem remover os dados operacionais já cacheados. Há ainda mistura explícita entre “empresa ativa” e “empresa filtrada”, identidade visual/rota de implantação fixas, RBAC de ações aplicado de forma desigual, classificação de veículo por texto livre e convenções operacionais codificadas no cliente.

Foram registrados **10 achados**: **6 altos, 4 médios**.

| Critério | Achados |
|---|---:|
| C1 — conceito ausente | 1 |
| C2 — classificação por texto | 1 |
| C3 — flag como proxy | 1 |
| C4 — convenção não declarada | 3 |
| C5 — conceitos misturados | 3 |
| C6 — escopo de tenant errado | 7 |

Um achado pode atender a mais de um critério.

## Achados

### A-13.01 — Cache operacional sobrevive ao logout e pode reaparecer em outra sessão

**Critério:** C6 · **Severidade:** ALTA  
**Evidência:** `frontend/src/main.tsx:14-16`; `frontend/src/lib/auth.tsx:106-121`; `frontend/src/features/superadmin/auth.tsx:19-46`.

Existe um único `QueryClient` para toda a aplicação. No logout de tenant, somente `['me']` vira `null`; no logout de plataforma, somente `['sa','me']` vira `null`. Não existe `queryClient.clear()` nem remoção das famílias de cache. As centenas de chaves operacionais permanecem na memória e, em novo login na mesma aba, componentes podem renderizar o valor cacheado da sessão anterior enquanto revalidam.

**Impacto na segunda revenda:** um operador que encerre a sessão e outro operador — possivelmente de outra revenda — entre na mesma aba pode observar dados residuais da primeira sessão. Mesmo quando a API recusa ou corrige a consulta seguinte, a exposição transitória já viola isolamento.

**Direção de correção:** no encerramento e na troca de identidade, cancelar consultas, limpar todo o cache do domínio correspondente e só então publicar a nova identidade. Separar fisicamente `QueryClient` de tenant e SuperAdmin ou aplicar namespaces incontornáveis. Criar teste de login A → carga de dado → logout → login B garantindo ausência total do dado A.

### A-13.02 — Chaves de consulta não incluem tenant nem empresa filtrada

**Critério:** C6 · **Severidade:** ALTA  
**Evidência:** `frontend/src/lib/api.ts:48-84`; `frontend/src/features/dashboard/DashboardPage.tsx:14-19`; `frontend/src/features/configuracoes/api.ts:27-31`; `frontend/src/features/satelites/extraApi.ts:203-206`; `frontend/src/layouts/EmpresaSwitcher.tsx:51-68`.

O interceptor acrescenta `empresa_id` a toda requisição, mas essa dimensão não entra nas chaves (`['dashboard-resumo']`, `['config-global']`, `['google-maps-key']` etc.). A troca tenta compensar com `invalidateQueries()` global. Isso faz a correção depender de uma convenção lateral e permite colisão de valores entre escopos; `google-maps-key`, por exemplo, usa `staleTime: Infinity` sob uma chave global.

**Impacto na segunda revenda:** qualquer fluxo que altere tenant/empresa sem passar exatamente pelo seletor, falhe no meio da troca ou reutilize a sessão pode associar resposta de uma revenda à chave lógica de outra.

**Direção de correção:** criar uma fábrica central de chaves que inclua, no mínimo, `grupo_id`, empresa ativa e empresa filtrada conforme a semântica do endpoint; remontar/limpar o cache quando a identidade mudar. Não usar invalidação global como mecanismo primário de isolamento.

### A-13.03 — O filtro de empresa persiste fora da identidade do usuário

**Critério:** C6 · **Severidade:** ALTA  
**Evidência:** `frontend/src/lib/api.ts:48-68`; `frontend/src/lib/auth.tsx:106-121`.

`erpnovo_filtro_empresa` fica em `sessionStorage`, não é qualificado por usuário/grupo e não é removido no logout. O próximo login na mesma aba herda o número de empresa escolhido na sessão anterior, e o interceptor o envia automaticamente em todas as requisições.

**Impacto na segunda revenda:** a segunda sessão pode abrir vazia, receber erros ou operar sob um filtro residual invisível; a proteção final depende de o backend rejeitar sempre um ID fora do tenant.

**Direção de correção:** remover o filtro no logout e no login; alternativamente armazená-lo sob chave versionada por usuário+grupo e validar o ID contra a lista de empresas autorizadas antes de ativá-lo.

### A-13.04 — “Empresa exibida” e “empresa ativa” são conceitos distintos controlados por uma ação única

**Critério:** C5/C6 · **Severidade:** ALTA  
**Evidência:** `frontend/src/layouts/EmpresaSwitcher.tsx:11-20`, `:48-68`, `:88-103`; `frontend/src/lib/api.ts:50-56`.

O próprio código documenta que filtro de listagem e empresa ativa (configuração, caixa e numeração fiscal) são diferentes, mas a seleção de uma empresa altera ambos. A opção “Toda a rede” limpa apenas o filtro e mantém a última empresa ativa.

**Impacto na segunda revenda:** em uma rede com filiais, a tela pode declarar “Toda a rede” enquanto novas escritas fiscais/financeiras continuam sendo atribuídas à última filial ativa. O operador não tem indicação inequívoca de onde a escrita ocorrerá.

**Direção de correção:** representar separadamente `escopoDeLeitura` e `empresaOperacional`; mostrar ambos no cabeçalho; exigir empresa operacional explícita antes de ações que geram caixa, estoque ou documento fiscal e impedir que um único clique altere silenciosamente os dois conceitos.

### A-13.05 — Marca, favicon, título e caminho de produção são globais e fixos

**Critério:** C1/C6 · **Severidade:** ALTA  
**Evidência:** `frontend/index.html:2-8`; `frontend/src/features/auth/LoginPage.tsx:76`; `frontend/src/layouts/AppShell.tsx:127-130`; `frontend/vite.config.ts:5-16`.

A aplicação fixa “Dubena”, favicon raiz, cor de tema, idioma e o `base` `/novo/app/`; a configuração de build ainda cita um host operacional específico. Não existe conceito frontend de identidade/apresentação do tenant.

**Impacto na segunda revenda:** ela recebe marca e metadados da primeira operação e exige novo build/infra para mudar URL, título ou aparência, contrariando onboarding repetível de SaaS.

**Direção de correção:** introduzir configuração pública de branding por tenant (nome, logo, favicon, cores e metadados) carregada antes do shell; parametrizar `base`/deploy por ambiente e manter a marca da plataforma separada da marca da revenda.

### A-13.06 — Autorização de mutações é aplicada de modo desigual na SPA

**Critério:** C4 · **Severidade:** ALTA  
**Evidência:** `frontend/src/routes.tsx:95-114`, `:181-188`; `frontend/src/features/configuracoes/taxas-entrega/TaxasEntregaTab.tsx:38-45`, `:68-86`, `:126-132`, `:148-161`; contraste com `frontend/src/features/acessos/UsuariosTab.tsx:102-120`.

As rotas normalmente exigem apenas `*.view`. Alguns módulos protegem criar/editar/excluir com `can()`/`<Can>`, mas vários outros renderizam e disparam mutações sem gate de ação. Em Taxas de Entrega, por exemplo, criar, editar e excluir ficam disponíveis a quem alcança a aba por `grupo.view`. A regra de quais páginas dependem exclusivamente da recusa do backend não está declarada nem uniforme.

**Impacto na segunda revenda:** perfis personalizados terão UX contraditória: botões proibidos aparecem e falham depois do clique; onde algum endpoint estiver sem gate correspondente, a inconsistência vira elevação de privilégio.

**Direção de correção:** declarar contrato único `view/create/edit/delete/approve/...` por recurso e gerar rota/menu/ações a partir dele; cobrir todas as mutações com gates de UX e manter o backend como autoridade. Adicionar testes por perfil não-support.

### A-13.07 — Ícone de veículo é inferido da descrição livre

**Critério:** C2 · **Severidade:** MÉDIA  
**Evidência:** `frontend/src/features/satelites/iconesVeiculo.ts:48-69`.

Quando `icone` não casa com o catálogo local, `desenhoDe()` normaliza `tipo` e procura fragmentos como `caminh`, `carreta`, `moto`, `carro` e `automovel`. Qualquer descrição fora desse vocabulário vira carro.

**Impacto na segunda revenda:** cadastros como “VUC”, “triciclo”, “bitrem” ou termos regionais são classificados incorretamente no mapa; o comportamento muda por texto digitado e idioma.

**Direção de correção:** tornar `icone/categoria_visual` um código obrigatório validado por catálogo, com fallback explícito “desconhecido”; nunca inferir regra de negócio ou apresentação por substring de descrição.

### A-13.08 — Um único “estado do veículo” mistura sinal, movimento, ignição e infração

**Critério:** C4/C5/C6 · **Severidade:** MÉDIA  
**Evidência:** `frontend/src/features/satelites/iconesVeiculo.ts:127-153`, `:156-174`; `frontend/src/features/satelites/MapaAoVivoTab.tsx:90-100`.

O enum `EstadoVeiculo` combina quatro dimensões diferentes em uma classificação exclusiva. A precedência é fixa (`sem sinal` antes de excesso, depois movimento/ignição) e “sem sinal” usa 15 minutos codificados na SPA.

**Impacto na segunda revenda:** uma operação com frequência de rastreador diferente terá falsos estados; um veículo pode simultaneamente estar sem sinal e ter última posição em excesso, mas a UI força apenas uma categoria segundo prioridade local.

**Direção de correção:** separar `conectividade`, `movimento`, `ignição` e `alertas`; receber estado calculado/limiares da política do tenant ou metadados do rastreador; renderizar badges simultâneos quando aplicável.

### A-13.09 — Papéis comerciais e fiscais do cliente são uma coleção de flags

**Critério:** C3/C5 · **Severidade:** MÉDIA  
**Evidência:** `frontend/src/features/clientes/ClienteFormPage.tsx:20-24`, `:42-45`, `:119-126`; `frontend/src/features/clientes/api.ts:8-14`, `:38-44`.

Cliente, fornecedor, transportador, Simples Nacional, emissão de NF e participação em Gás do Povo aparecem no mesmo formulário como booleanos equivalentes. Papéis comerciais multivalorados e propriedades fiscais/programáticas distintas ficam achatados no mesmo conjunto de flags.

**Impacto na segunda revenda:** novas funções de contraparte e regimes fiscais exigem novas colunas, novos checkboxes e condicionais; combinações inválidas não têm contrato próprio.

**Direção de correção:** modelar papéis como relação/catálogo, regime fiscal como enum historizável e adesões a programas como vínculo com vigência/status. Na SPA, renderizar seções e validações próprias para cada conceito.

### A-13.10 — Localidade e moeda são convenções globais no bundle

**Critério:** C4/C6 · **Severidade:** MÉDIA  
**Evidência:** `frontend/src/lib/format.ts:1-42`; `frontend/src/lib/googleMaps.ts:37-39`; `frontend/src/components/ui/stat-card.tsx:35`; `frontend/src/features/valegas/ValeGasPage.tsx:12-48`.

`pt-BR`, `BRL`, idioma/região do Google Maps e formatações locais estão codificados globalmente; algumas telas ainda duplicam o formato fora do helper central.

**Impacto na segunda revenda:** enquanto o produto for exclusivamente brasileiro o valor é baixo, mas franquias em outro locale/moeda ou operação de fronteira exigirão alteração e rebuild global; duplicações produzem divergência.

**Direção de correção:** definir locale, moeda e fuso no contexto do tenant; concentrar toda formatação em helpers que consumam esse contexto e substituir usos diretos de `toLocale*`.

## Validações executáveis

- `npm run lint` (`tsc --noEmit`): **aprovado**, código 0.
- `npm test -- --run`: **5 arquivos, 35 testes aprovados**, código 0.
- O runner emitiu aviso de futura incompatibilidade de `__dirname` em `vitest.config.ts:11` com o carregador nativo do Vite. Não altera os achados C1–C6, mas deve entrar na manutenção técnica.
- Só existem 5 arquivos de teste para 221 arquivos auditados; não há teste de isolamento de cache entre usuários/tenants, troca de empresa, logout do SuperAdmin, branding/locale ou matriz completa de permissões.

## Itens não verificáveis neste volume

- Se o backend impede, em todos os endpoints, o `empresa_id` residual ou adulterado. O código da SPA só prova que o parâmetro é enviado.
- Se “grupo” é de fato o tenant comercial definitivo ou apenas um agrupador operacional; essa decisão muda o escopo correto de `config-global`.
- Se a segunda revenda será white-label, terá domínio próprio, outra moeda/locale ou apenas outra base de dados. O frontend atualmente não oferece esses conceitos.
- Comportamento visual e de rede em navegador real, inclusive flashes de cache entre sessões; a conclusão decorre do ciclo de vida determinístico do `QueryClient`, mas não houve teste E2E multiusuário.
- Conteúdo de dependências e artefatos gerados, corretamente excluídos do recorte.

## Inventário e prova de cobertura

A contagem usa `Get-Content(...).Count` sobre os arquivos próprios presentes no recorte em 2026-08-25. O inventário detalhado abaixo é parte do fechamento; todos os arquivos foram lidos do início ao fim.

| Área | Arquivos | Linhas |
|---|---:|---:|
| Configuração/entrypoints fora de `src` | 9 | 216 |
| `src/components` | 31 | 1.320 |
| `src/features` | 163 | 20.353 |
| `src/layouts` | 2 | 344 |
| `src/lib` | 11 | 576 |
| `src` (entrypoints, CSS, rotas e tipos) | 4 | 382 |
| `src/test` | 1 | 1 |
| **Total** | **221** | **23.192** |

### Lista integral por arquivo

> A tabela de contagem detalhada deve ser reconciliada com o total acima; qualquer arquivo novo torna este volume desatualizado e exige nova leitura.

| Arquivo | LOC |
|---|---:|
| `erp-novo/frontend/.env.example` | 13 |
| `erp-novo/frontend/.gitignore` | 5 |
| `erp-novo/frontend/index.html` | 14 |
| `erp-novo/frontend/package.json` | 53 |
| `erp-novo/frontend/postcss.config.js` | 6 |
| `erp-novo/frontend/tailwind.config.js` | 57 |
| `erp-novo/frontend/tsconfig.json` | 22 |
| `erp-novo/frontend/vite.config.ts` | 27 |
| `erp-novo/frontend/vitest.config.ts` | 19 |
| `erp-novo/frontend/src/components/ErrorBoundary.test.tsx` | 31 |
| `erp-novo/frontend/src/components/ErrorBoundary.tsx` | 65 |
| `erp-novo/frontend/src/components/ui/async-select.tsx` | 108 |
| `erp-novo/frontend/src/components/ui/async-state.tsx` | 51 |
| `erp-novo/frontend/src/components/ui/badge.tsx` | 24 |
| `erp-novo/frontend/src/components/ui/button.tsx` | 48 |
| `erp-novo/frontend/src/components/ui/can.test.tsx` | 22 |
| `erp-novo/frontend/src/components/ui/can.tsx` | 16 |
| `erp-novo/frontend/src/components/ui/card.tsx` | 38 |
| `erp-novo/frontend/src/components/ui/checkbox.tsx` | 37 |
| `erp-novo/frontend/src/components/ui/confirm-dialog.tsx` | 47 |
| `erp-novo/frontend/src/components/ui/data-table.tsx` | 114 |
| `erp-novo/frontend/src/components/ui/dialog.tsx` | 72 |
| `erp-novo/frontend/src/components/ui/dropdown-menu.tsx` | 58 |
| `erp-novo/frontend/src/components/ui/empty-state.tsx` | 28 |
| `erp-novo/frontend/src/components/ui/field.tsx` | 37 |
| `erp-novo/frontend/src/components/ui/form-dialog.tsx` | 47 |
| `erp-novo/frontend/src/components/ui/index.ts` | 33 |
| `erp-novo/frontend/src/components/ui/input.tsx` | 21 |
| `erp-novo/frontend/src/components/ui/label.tsx` | 16 |
| `erp-novo/frontend/src/components/ui/page-header.tsx` | 27 |
| `erp-novo/frontend/src/components/ui/resource-list.tsx` | 47 |
| `erp-novo/frontend/src/components/ui/row-actions.tsx` | 37 |
| `erp-novo/frontend/src/components/ui/search-bar.tsx` | 38 |
| `erp-novo/frontend/src/components/ui/select.tsx` | 79 |
| `erp-novo/frontend/src/components/ui/skeleton.tsx` | 6 |
| `erp-novo/frontend/src/components/ui/stat-card.tsx` | 41 |
| `erp-novo/frontend/src/components/ui/switch.tsx` | 23 |
| `erp-novo/frontend/src/components/ui/tabs.tsx` | 54 |
| `erp-novo/frontend/src/components/ui/textarea.tsx` | 20 |
| `erp-novo/frontend/src/components/ui/tooltip.tsx` | 35 |
| `erp-novo/frontend/src/features/acessos/AcessosPage.tsx` | 48 |
| `erp-novo/frontend/src/features/acessos/api.ts` | 228 |
| `erp-novo/frontend/src/features/acessos/AuditoriaTab.tsx` | 98 |
| `erp-novo/frontend/src/features/acessos/CondicoesDialog.tsx` | 123 |
| `erp-novo/frontend/src/features/acessos/EstruturaTab.tsx` | 220 |
| `erp-novo/frontend/src/features/acessos/PerfisTab.tsx` | 157 |
| `erp-novo/frontend/src/features/acessos/PoliticaSenhaTab.tsx` | 54 |
| `erp-novo/frontend/src/features/acessos/UsuariosTab.tsx` | 179 |
| `erp-novo/frontend/src/features/alertas/AlertasPage.tsx` | 248 |
| `erp-novo/frontend/src/features/alertas/api.ts` | 58 |
| `erp-novo/frontend/src/features/auditoria/api.ts` | 109 |
| `erp-novo/frontend/src/features/auditoria/AuditoriaPage.tsx` | 392 |
| `erp-novo/frontend/src/features/auth/LoginPage.tsx` | 150 |
| `erp-novo/frontend/src/features/auth/SemAcessoPage.tsx` | 27 |
| `erp-novo/frontend/src/features/cadastros/api.ts` | 33 |
| `erp-novo/frontend/src/features/cadastros/CadastroApoioTab.tsx` | 102 |
| `erp-novo/frontend/src/features/central/api.ts` | 144 |
| `erp-novo/frontend/src/features/central/CentralPage.tsx` | 237 |
| `erp-novo/frontend/src/features/central-vendas/alcadaApi.ts` | 66 |
| `erp-novo/frontend/src/features/central-vendas/AlcadasPage.tsx` | 188 |
| `erp-novo/frontend/src/features/central-vendas/api.ts` | 95 |
| `erp-novo/frontend/src/features/central-vendas/CentralVendasPage.tsx` | 279 |
| `erp-novo/frontend/src/features/central-vendas/estoqueApi.ts` | 67 |
| `erp-novo/frontend/src/features/central-vendas/EstoqueFranqueadoDialog.tsx` | 144 |
| `erp-novo/frontend/src/features/clientes/api.ts` | 186 |
| `erp-novo/frontend/src/features/clientes/AuditoriaTab.tsx` | 126 |
| `erp-novo/frontend/src/features/clientes/ClienteFormPage.tsx` | 183 |
| `erp-novo/frontend/src/features/clientes/ClientesListPage.tsx` | 226 |
| `erp-novo/frontend/src/features/clientes/ConvenioTab.tsx` | 97 |
| `erp-novo/frontend/src/features/clientes/HistoricoTab.tsx` | 19 |
| `erp-novo/frontend/src/features/clientes/InteracoesTab.tsx` | 58 |
| `erp-novo/frontend/src/features/clientes/PrecosTab.tsx` | 21 |
| `erp-novo/frontend/src/features/clientes/revisoes/api.ts` | 90 |
| `erp-novo/frontend/src/features/clientes/revisoes/RevisoesPage.tsx` | 241 |
| `erp-novo/frontend/src/features/clientes/TelefonesTab.tsx` | 53 |
| `erp-novo/frontend/src/features/comodatos/AjustarComodatoDialog.tsx` | 190 |
| `erp-novo/frontend/src/features/comodatos/api.ts` | 221 |
| `erp-novo/frontend/src/features/comodatos/ComodatoDetalhe.tsx` | 235 |
| `erp-novo/frontend/src/features/comodatos/ComodatoPage.tsx` | 161 |
| `erp-novo/frontend/src/features/comodatos/ConfigVigilanciaDialog.tsx` | 160 |
| `erp-novo/frontend/src/features/comodatos/DevolucaoDialog.tsx` | 111 |
| `erp-novo/frontend/src/features/comodatos/VigilanciaTab.tsx` | 162 |
| `erp-novo/frontend/src/features/comodatos/VinculosTab.tsx` | 149 |
| `erp-novo/frontend/src/features/configuracoes/api.ts` | 41 |
| `erp-novo/frontend/src/features/configuracoes/ConfigGlobalTab.tsx` | 93 |
| `erp-novo/frontend/src/features/configuracoes/ConfiguracoesPage.tsx` | 108 |
| `erp-novo/frontend/src/features/configuracoes/taxas-entrega/api.ts` | 77 |
| `erp-novo/frontend/src/features/configuracoes/taxas-entrega/TaxasEntregaTab.tsx` | 257 |
| `erp-novo/frontend/src/features/convenios/api.ts` | 24 |
| `erp-novo/frontend/src/features/convenios/ConvenioPage.tsx` | 69 |
| `erp-novo/frontend/src/features/crm/api.ts` | 109 |
| `erp-novo/frontend/src/features/crm/ChecklistPage.tsx` | 77 |
| `erp-novo/frontend/src/features/crm/MetaPage.tsx` | 81 |
| `erp-novo/frontend/src/features/crm/PosVendaPage.tsx` | 95 |
| `erp-novo/frontend/src/features/crm/PromocaoPage.tsx` | 78 |
| `erp-novo/frontend/src/features/crm/SorteioPage.tsx` | 95 |
| `erp-novo/frontend/src/features/dashboard/DashboardPage.tsx` | 49 |
| `erp-novo/frontend/src/features/empresas/api.ts` | 146 |
| `erp-novo/frontend/src/features/empresas/CertificadoSection.tsx` | 80 |
| `erp-novo/frontend/src/features/empresas/config/ContabilTab.tsx` | 21 |
| `erp-novo/frontend/src/features/empresas/config/EmailTab.tsx` | 20 |
| `erp-novo/frontend/src/features/empresas/config/EstoqueTab.tsx` | 13 |
| `erp-novo/frontend/src/features/empresas/config/FreteTab.tsx` | 14 |
| `erp-novo/frontend/src/features/empresas/config/ImpressaoTab.tsx` | 14 |
| `erp-novo/frontend/src/features/empresas/config/PedidoTab.tsx` | 21 |
| `erp-novo/frontend/src/features/empresas/config/PercentuaisTab.tsx` | 13 |
| `erp-novo/frontend/src/features/empresas/config/SenhaMestraDialog.tsx` | 27 |
| `erp-novo/frontend/src/features/empresas/config/TesteEmailDialog.tsx` | 26 |
| `erp-novo/frontend/src/features/empresas/config/types.ts` | 7 |
| `erp-novo/frontend/src/features/empresas/ConfigTab.tsx` | 72 |
| `erp-novo/frontend/src/features/empresas/EmpresaFormPage.tsx` | 197 |
| `erp-novo/frontend/src/features/empresas/EmpresasListPage.tsx` | 150 |
| `erp-novo/frontend/src/features/empresas/IntegracoesSection.tsx` | 108 |
| `erp-novo/frontend/src/features/estoque/api.ts` | 83 |
| `erp-novo/frontend/src/features/estoque/EstoquePage.tsx` | 35 |
| `erp-novo/frontend/src/features/estoque/tabs/AcertoTab.tsx` | 35 |
| `erp-novo/frontend/src/features/estoque/tabs/FechamentoTab.tsx` | 53 |
| `erp-novo/frontend/src/features/estoque/tabs/FisicoTab.tsx` | 75 |
| `erp-novo/frontend/src/features/estoque/tabs/InventarioTab.tsx` | 51 |
| `erp-novo/frontend/src/features/estoque/tabs/ItensEditor.tsx` | 31 |
| `erp-novo/frontend/src/features/estoque/tabs/RequisicaoTab.tsx` | 48 |
| `erp-novo/frontend/src/features/estoque/tabs/SaldosTab.tsx` | 31 |
| `erp-novo/frontend/src/features/estoque/tabs/TransferenciaTab.tsx` | 54 |
| `erp-novo/frontend/src/features/financeiro/api.ts` | 207 |
| `erp-novo/frontend/src/features/financeiro/FinanceiroPage.tsx` | 41 |
| `erp-novo/frontend/src/features/financeiro/tabs/CaixaTab.tsx` | 56 |
| `erp-novo/frontend/src/features/financeiro/tabs/CentroTab.tsx` | 39 |
| `erp-novo/frontend/src/features/financeiro/tabs/ExtratoRegrasTab.tsx` | 178 |
| `erp-novo/frontend/src/features/financeiro/tabs/FinanceiroExtraTabs.tsx` | 234 |
| `erp-novo/frontend/src/features/financeiro/tabs/LancamentosTab.tsx` | 108 |
| `erp-novo/frontend/src/features/financeiro/tabs/MaloteTab.tsx` | 168 |
| `erp-novo/frontend/src/features/financeiro/tabs/PlanoTab.tsx` | 46 |
| `erp-novo/frontend/src/features/fiscal/api.ts` | 128 |
| `erp-novo/frontend/src/features/fiscal/FiscalPage.tsx` | 26 |
| `erp-novo/frontend/src/features/fiscal/tabs/MalhaTab.tsx` | 101 |
| `erp-novo/frontend/src/features/fiscal/tabs/NfEntradaTab.tsx` | 80 |
| `erp-novo/frontend/src/features/fiscal/tabs/NfeTab.tsx` | 145 |
| `erp-novo/frontend/src/features/fiscal/tabs/SpedTab.tsx` | 33 |
| `erp-novo/frontend/src/features/frota/api.ts` | 24 |
| `erp-novo/frontend/src/features/frota/VeiculosPage.tsx` | 104 |
| `erp-novo/frontend/src/features/geografico/api.ts` | 179 |
| `erp-novo/frontend/src/features/geografico/GeograficoPage.tsx` | 494 |
| `erp-novo/frontend/src/features/geografico/ImportacaoTab.tsx` | 273 |
| `erp-novo/frontend/src/features/gestao/api.ts` | 79 |
| `erp-novo/frontend/src/features/gestao/BemPage.tsx` | 69 |
| `erp-novo/frontend/src/features/gestao/CupomPage.tsx` | 85 |
| `erp-novo/frontend/src/features/gestao/DocumentoPage.tsx` | 69 |
| `erp-novo/frontend/src/features/gestao/McmmPage.tsx` | 76 |
| `erp-novo/frontend/src/features/missoes/api.ts` | 130 |
| `erp-novo/frontend/src/features/missoes/MissoesPage.tsx` | 290 |
| `erp-novo/frontend/src/features/pagamentos/api.ts` | 100 |
| `erp-novo/frontend/src/features/pagamentos/CartaoPage.tsx` | 78 |
| `erp-novo/frontend/src/features/pagamentos/GasDoPovoPage.tsx` | 138 |
| `erp-novo/frontend/src/features/pagamentos/GasDoPovoProgramaTab.tsx` | 296 |
| `erp-novo/frontend/src/features/pedidos/api.ts` | 142 |
| `erp-novo/frontend/src/features/pedidos/KanbanView.tsx` | 290 |
| `erp-novo/frontend/src/features/pedidos/ListaView.tsx` | 35 |
| `erp-novo/frontend/src/features/pedidos/PainelChamadas.tsx` | 89 |
| `erp-novo/frontend/src/features/pedidos/PedidoDialogs.tsx` | 123 |
| `erp-novo/frontend/src/features/pedidos/PedidosPage.tsx` | 29 |
| `erp-novo/frontend/src/features/pedidos/shared.tsx` | 9 |
| `erp-novo/frontend/src/features/produtos/api.ts` | 180 |
| `erp-novo/frontend/src/features/produtos/OrigensTab.tsx` | 88 |
| `erp-novo/frontend/src/features/produtos/ProdutoConfigPage.tsx` | 156 |
| `erp-novo/frontend/src/features/produtos/ProdutoFormPage.tsx` | 286 |
| `erp-novo/frontend/src/features/produtos/ProdutoPrecosPage.tsx` | 93 |
| `erp-novo/frontend/src/features/produtos/ProdutosListPage.tsx` | 136 |
| `erp-novo/frontend/src/features/relatorios/api.ts` | 60 |
| `erp-novo/frontend/src/features/relatorios/RelatoriosPage.tsx` | 111 |
| `erp-novo/frontend/src/features/rh/api.ts` | 75 |
| `erp-novo/frontend/src/features/rh/ColaboradoresPage.tsx` | 213 |
| `erp-novo/frontend/src/features/rh/tabs/ComissoesTab.tsx` | 15 |
| `erp-novo/frontend/src/features/rh/tabs/ExamesTab.tsx` | 42 |
| `erp-novo/frontend/src/features/rh/tabs/FamiliaTab.tsx` | 29 |
| `erp-novo/frontend/src/features/rh/tabs/PontoTab.tsx` | 31 |
| `erp-novo/frontend/src/features/rh/tabs/RecessosTab.tsx` | 14 |
| `erp-novo/frontend/src/features/rh/tabs/TurnosTab.tsx` | 36 |
| `erp-novo/frontend/src/features/satelites/api.ts` | 7 |
| `erp-novo/frontend/src/features/satelites/CercasTab.tsx` | 759 |
| `erp-novo/frontend/src/features/satelites/editorPoligono.test.ts` | 212 |
| `erp-novo/frontend/src/features/satelites/editorPoligono.ts` | 252 |
| `erp-novo/frontend/src/features/satelites/extraApi.ts` | 206 |
| `erp-novo/frontend/src/features/satelites/iconesVeiculo.ts` | 174 |
| `erp-novo/frontend/src/features/satelites/MapaAoVivoTab.tsx` | 306 |
| `erp-novo/frontend/src/features/satelites/MonitoraPage.tsx` | 59 |
| `erp-novo/frontend/src/features/satelites/RotaTab.tsx` | 410 |
| `erp-novo/frontend/src/features/satelites/SatelitesPage.tsx` | 78 |
| `erp-novo/frontend/src/features/satelites/useEditorCerca.ts` | 466 |
| `erp-novo/frontend/src/features/seguranca/api.ts` | 67 |
| `erp-novo/frontend/src/features/seguranca/SegurancaPage.tsx` | 158 |
| `erp-novo/frontend/src/features/superadmin/api.ts` | 357 |
| `erp-novo/frontend/src/features/superadmin/auth.tsx` | 56 |
| `erp-novo/frontend/src/features/superadmin/SaAuditoriaPage.tsx` | 67 |
| `erp-novo/frontend/src/features/superadmin/SaCidadesPage.tsx` | 84 |
| `erp-novo/frontend/src/features/superadmin/SaDashboardPage.tsx` | 141 |
| `erp-novo/frontend/src/features/superadmin/SaEmpresasPage.tsx` | 198 |
| `erp-novo/frontend/src/features/superadmin/SaLayout.tsx` | 186 |
| `erp-novo/frontend/src/features/superadmin/SaLoginPage.tsx` | 134 |
| `erp-novo/frontend/src/features/superadmin/SaMigracaoPage.tsx` | 506 |
| `erp-novo/frontend/src/features/superadmin/SaPlanosPage.tsx` | 103 |
| `erp-novo/frontend/src/features/superadmin/SaRoutes.tsx` | 53 |
| `erp-novo/frontend/src/features/valegas/api.ts` | 39 |
| `erp-novo/frontend/src/features/valegas/ValeGasPage.tsx` | 106 |
| `erp-novo/frontend/src/index.css` | 127 |
| `erp-novo/frontend/src/layouts/AppShell.tsx` | 235 |
| `erp-novo/frontend/src/layouts/EmpresaSwitcher.tsx` | 109 |
| `erp-novo/frontend/src/lib/api.ts` | 95 |
| `erp-novo/frontend/src/lib/auth.tsx` | 140 |
| `erp-novo/frontend/src/lib/cn.ts` | 7 |
| `erp-novo/frontend/src/lib/format.test.ts` | 33 |
| `erp-novo/frontend/src/lib/format.ts` | 46 |
| `erp-novo/frontend/src/lib/googleMaps.ts` | 46 |
| `erp-novo/frontend/src/lib/pdf.ts` | 36 |
| `erp-novo/frontend/src/lib/rbac.test.ts` | 51 |
| `erp-novo/frontend/src/lib/rbac.ts` | 27 |
| `erp-novo/frontend/src/lib/useBusca.ts` | 24 |
| `erp-novo/frontend/src/lib/useResourceForm.ts` | 71 |
| `erp-novo/frontend/src/main.tsx` | 57 |
| `erp-novo/frontend/src/routes.tsx` | 193 |
| `erp-novo/frontend/src/test/setup.ts` | 1 |
| `erp-novo/frontend/src/vite-env.d.ts` | 5 |
