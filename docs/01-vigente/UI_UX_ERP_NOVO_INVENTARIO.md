# Inventário de apoio — UI/UX erp-novo

Data: 10/09/2026. Referência: `2b53d9fdc3b7ac4c72a25f531787afa0df260f3a`.

Inventário estrutural automatizado. Os sinais indicam ocorrência textual no arquivo, não uso correto, cobertura funcional ou auditoria visual. Arquivos sem sinal continuam no escopo. Leitura aprofundada e achados: [plano principal](PLANO_MODERNIZACAO_UI_UX_ERP_NOVO.md).

## Superfícies externas à árvore TSX

- `erp-novo/public/landing/index.html`: landing pública, CSS e marca.
- `erp-novo/docker/nginx/default.conf`: mapeamento da landing e SPA.
- `erp-novo/routes/web.php`: entrada Laravel welcome.
- `erp-novo/frontend/src/index.css`: tokens efetivos e temas.
- `erp-novo/frontend/PADRAO_UI.md`: padrão atual de identidade.
- `erp-novo/frontend/vite.config.ts`: base da SPA e saída do build.
- `erp-novo/app/Domain/Relatorio/RelatorioService.php`: semântica dos contadores da home.

## Arquivos TSX

Caminhos relativos a `erp-novo/frontend/src/`. L = quantidade de linhas. Abas = ocorrências de abertura de TabsTrigger, inclusive condicionais. Query com erro não é inferida pela presença de AsyncState.

| Arquivo | L | Abas | Componentes/padrões encontrados |
|---|---:|---:|---|
| `components/ErrorBoundary.test.tsx` | 31 | 0 | - |
| `components/ErrorBoundary.tsx` | 65 | 0 | - |
| `components/ui/async-multi-select.tsx` | 122 | 0 | - |
| `components/ui/async-select.tsx` | 108 | 0 | - |
| `components/ui/async-state.tsx` | 51 | 0 | ResourceList, DataTable, AsyncState |
| `components/ui/badge.tsx` | 24 | 0 | - |
| `components/ui/button.tsx` | 48 | 0 | - |
| `components/ui/can.test.tsx` | 22 | 0 | - |
| `components/ui/can.tsx` | 16 | 0 | - |
| `components/ui/card.tsx` | 38 | 0 | - |
| `components/ui/checkbox.tsx` | 37 | 0 | - |
| `components/ui/confirm-dialog.tsx` | 47 | 0 | FormDialog, ConfirmDialog |
| `components/ui/data-table.tsx` | 114 | 0 | DataTable |
| `components/ui/dialog.tsx` | 72 | 0 | - |
| `components/ui/dropdown-menu.tsx` | 58 | 0 | - |
| `components/ui/empty-state.tsx` | 28 | 0 | - |
| `components/ui/field.tsx` | 37 | 0 | - |
| `components/ui/form-dialog.tsx` | 47 | 0 | FormDialog |
| `components/ui/input.tsx` | 21 | 0 | - |
| `components/ui/label.tsx` | 16 | 0 | - |
| `components/ui/page-header.tsx` | 27 | 0 | - |
| `components/ui/resource-list.tsx` | 47 | 0 | ResourceList, DataTable |
| `components/ui/row-actions.tsx` | 37 | 0 | DataTable, confirm nativo |
| `components/ui/search-bar.tsx` | 38 | 0 | - |
| `components/ui/select.tsx` | 79 | 0 | - |
| `components/ui/skeleton.tsx` | 6 | 0 | - |
| `components/ui/stat-card.tsx` | 41 | 0 | StatCard |
| `components/ui/switch.tsx` | 23 | 0 | - |
| `components/ui/tabs.tsx` | 54 | 0 | - |
| `components/ui/textarea.tsx` | 20 | 0 | - |
| `components/ui/tooltip.tsx` | 35 | 0 | - |
| `features/acessos/AcessosPage.tsx` | 48 | 5 | - |
| `features/acessos/AuditoriaTab.tsx` | 98 | 2 | ResourceList |
| `features/acessos/CondicoesDialog.tsx` | 123 | 0 | - |
| `features/acessos/EstruturaTab.tsx` | 220 | 0 | ResourceList, FormDialog |
| `features/acessos/PerfisTab.tsx` | 157 | 0 | ResourceList, FormDialog |
| `features/acessos/PoliticaSenhaTab.tsx` | 54 | 0 | - |
| `features/acessos/UsuariosTab.tsx` | 179 | 0 | ResourceList, FormDialog |
| `features/alertas/AlertasPage.tsx` | 248 | 0 | FormDialog, AsyncState, StatCard |
| `features/auditoria/AuditoriaPage.tsx` | 432 | 2 | AsyncState |
| `features/auth/LoginPage.tsx` | 150 | 0 | - |
| `features/auth/SemAcessoPage.tsx` | 27 | 0 | - |
| `features/cadastros/CadastroApoioTab.tsx` | 102 | 0 | DataTable, FormDialog, ConfirmDialog |
| `features/central/CentralPage.tsx` | 237 | 0 | AsyncState, StatCard |
| `features/central-vendas/AlcadasPage.tsx` | 188 | 0 | AsyncState |
| `features/central-vendas/CentralVendasPage.tsx` | 279 | 3 | AsyncState, StatCard |
| `features/central-vendas/EstoqueFranqueadoDialog.tsx` | 144 | 0 | AsyncState |
| `features/clientes/AuditoriaTab.tsx` | 126 | 0 | AsyncState |
| `features/clientes/ClienteFormPage.tsx` | 183 | 8 | useResourceForm |
| `features/clientes/ClientesListPage.tsx` | 259 | 1 | DataTable, ConfirmDialog, useBusca |
| `features/clientes/ConvenioTab.tsx` | 97 | 0 | AsyncState |
| `features/clientes/exportacao/ExportarClientesDialog.tsx` | 421 | 2 | - |
| `features/clientes/exportacao/ProgressoExportacao.test.tsx` | 67 | 0 | - |
| `features/clientes/exportacao/ProgressoExportacao.tsx` | 135 | 0 | - |
| `features/clientes/HistoricoTab.tsx` | 19 | 0 | DataTable |
| `features/clientes/InteracoesTab.tsx` | 58 | 0 | AsyncState |
| `features/clientes/PrecosTab.tsx` | 21 | 0 | DataTable |
| `features/clientes/revisoes/RevisoesPage.tsx` | 241 | 4 | ConfirmDialog, AsyncState |
| `features/clientes/TelefonesTab.tsx` | 53 | 0 | AsyncState |
| `features/comodatos/AjustarComodatoDialog.tsx` | 190 | 2 | FormDialog |
| `features/comodatos/ComodatoDetalhe.tsx` | 235 | 0 | ConfirmDialog, AsyncState |
| `features/comodatos/ComodatoPage.tsx` | 161 | 3 | ResourceList, FormDialog |
| `features/comodatos/ConfigVigilanciaDialog.tsx` | 160 | 0 | FormDialog |
| `features/comodatos/DevolucaoDialog.tsx` | 111 | 0 | FormDialog |
| `features/comodatos/VigilanciaTab.tsx` | 162 | 0 | DataTable, AsyncState, StatCard |
| `features/comodatos/VinculosTab.tsx` | 149 | 0 | DataTable, AsyncState, StatCard |
| `features/configuracoes/ConfigGlobalTab.tsx` | 93 | 0 | AsyncState |
| `features/configuracoes/ConfiguracoesPage.tsx` | 108 | 16 | - |
| `features/configuracoes/taxas-entrega/TaxasEntregaTab.tsx` | 257 | 0 | DataTable, FormDialog, AsyncState |
| `features/convenios/ConvenioPage.tsx` | 69 | 0 | ResourceList, FormDialog, confirm nativo |
| `features/crm/ChecklistPage.tsx` | 77 | 0 | ResourceList, FormDialog |
| `features/crm/MetaPage.tsx` | 81 | 0 | ResourceList, FormDialog |
| `features/crm/PosVendaPage.tsx` | 95 | 0 | ResourceList, FormDialog |
| `features/crm/PromocaoPage.tsx` | 78 | 0 | ResourceList, FormDialog |
| `features/crm/SorteioPage.tsx` | 95 | 0 | ResourceList, FormDialog, confirm nativo |
| `features/dashboard/DashboardPage.tsx` | 49 | 0 | StatCard |
| `features/empresas/CertificadoSection.tsx` | 80 | 0 | - |
| `features/empresas/config/ContabilTab.tsx` | 21 | 0 | - |
| `features/empresas/config/EmailTab.tsx` | 20 | 0 | - |
| `features/empresas/config/EstoqueTab.tsx` | 13 | 0 | - |
| `features/empresas/config/FreteTab.tsx` | 14 | 0 | - |
| `features/empresas/config/ImpressaoTab.tsx` | 14 | 0 | - |
| `features/empresas/config/PedidoTab.tsx` | 21 | 0 | - |
| `features/empresas/config/PercentuaisTab.tsx` | 13 | 0 | - |
| `features/empresas/config/SenhaMestraDialog.tsx` | 27 | 0 | FormDialog |
| `features/empresas/config/TesteEmailDialog.tsx` | 26 | 0 | FormDialog |
| `features/empresas/ConfigTab.tsx` | 72 | 7 | AsyncState |
| `features/empresas/EmpresaFormPage.tsx` | 197 | 7 | useResourceForm |
| `features/empresas/EmpresasListPage.tsx` | 150 | 2 | DataTable, FormDialog, ConfirmDialog |
| `features/empresas/IntegracoesSection.tsx` | 108 | 0 | - |
| `features/estoque/EstoquePage.tsx` | 35 | 7 | - |
| `features/estoque/tabs/AcertoTab.tsx` | 35 | 0 | - |
| `features/estoque/tabs/FechamentoTab.tsx` | 53 | 0 | DataTable |
| `features/estoque/tabs/FisicoTab.tsx` | 75 | 0 | DataTable |
| `features/estoque/tabs/InventarioTab.tsx` | 51 | 0 | DataTable |
| `features/estoque/tabs/ItensEditor.tsx` | 31 | 0 | - |
| `features/estoque/tabs/RequisicaoTab.tsx` | 48 | 0 | DataTable |
| `features/estoque/tabs/SaldosTab.tsx` | 31 | 0 | DataTable, useBusca |
| `features/estoque/tabs/TransferenciaTab.tsx` | 54 | 0 | DataTable |
| `features/financeiro/FinanceiroPage.tsx` | 41 | 10 | - |
| `features/financeiro/tabs/CaixaTab.tsx` | 56 | 0 | DataTable |
| `features/financeiro/tabs/CentroTab.tsx` | 39 | 0 | DataTable, FormDialog, ConfirmDialog |
| `features/financeiro/tabs/ExtratoRegrasTab.tsx` | 178 | 0 | DataTable, FormDialog, ConfirmDialog |
| `features/financeiro/tabs/FinanceiroExtraTabs.tsx` | 234 | 2 | DataTable, FormDialog, ConfirmDialog, useBusca |
| `features/financeiro/tabs/LancamentosTab.tsx` | 108 | 0 | DataTable, FormDialog, useBusca |
| `features/financeiro/tabs/MaloteTab.tsx` | 168 | 0 | DataTable, ConfirmDialog |
| `features/financeiro/tabs/PlanoTab.tsx` | 46 | 0 | DataTable, FormDialog, ConfirmDialog |
| `features/fiscal/FiscalPage.tsx` | 26 | 4 | - |
| `features/fiscal/tabs/MalhaTab.tsx` | 101 | 8 | DataTable, FormDialog, ConfirmDialog |
| `features/fiscal/tabs/NfEntradaTab.tsx` | 80 | 0 | DataTable, FormDialog |
| `features/fiscal/tabs/NfeTab.tsx` | 145 | 0 | DataTable, FormDialog, useBusca |
| `features/fiscal/tabs/SpedTab.tsx` | 33 | 0 | AsyncState |
| `features/frota/VeiculosPage.tsx` | 104 | 4 | DataTable, ConfirmDialog, useResourceForm, useBusca |
| `features/geografico/GeograficoPage.tsx` | 494 | 6 | DataTable, FormDialog, ConfirmDialog, AsyncState |
| `features/geografico/ImportacaoTab.tsx` | 273 | 0 | DataTable, FormDialog, AsyncState |
| `features/gestao/BemPage.tsx` | 69 | 0 | ResourceList, FormDialog |
| `features/gestao/CupomPage.tsx` | 85 | 0 | ResourceList, FormDialog, confirm nativo |
| `features/gestao/DocumentoPage.tsx` | 69 | 0 | ResourceList, FormDialog |
| `features/gestao/McmmPage.tsx` | 76 | 0 | ResourceList, FormDialog |
| `features/missoes/MissoesPage.tsx` | 290 | 2 | AsyncState, StatCard |
| `features/pagamentos/CartaoPage.tsx` | 78 | 0 | ResourceList, FormDialog |
| `features/pagamentos/GasDoPovoPage.tsx` | 138 | 4 | ResourceList, FormDialog |
| `features/pagamentos/GasDoPovoProgramaTab.tsx` | 296 | 0 | DataTable, AsyncState, StatCard |
| `features/pedidos/KanbanView.tsx` | 302 | 0 | FormDialog, ConfirmDialog, AsyncState, drag HTML |
| `features/pedidos/ListaView.tsx` | 35 | 0 | DataTable, useBusca |
| `features/pedidos/PainelChamadas.tsx` | 89 | 0 | - |
| `features/pedidos/PedidoDialogs.tsx` | 123 | 0 | AsyncState |
| `features/pedidos/PedidosPage.tsx` | 29 | 2 | - |
| `features/pedidos/shared.tsx` | 9 | 0 | - |
| `features/produtos/OrigensTab.tsx` | 88 | 0 | - |
| `features/produtos/ProdutoConfigPage.tsx` | 156 | 2 | DataTable, FormDialog, ConfirmDialog |
| `features/produtos/ProdutoFormPage.tsx` | 306 | 6 | useResourceForm |
| `features/produtos/ProdutoPrecosPage.tsx` | 93 | 0 | DataTable |
| `features/produtos/ProdutosListPage.tsx` | 136 | 0 | DataTable, ConfirmDialog, useBusca |
| `features/relatorios/RelatoriosPage.tsx` | 111 | 0 | JSON de dados |
| `features/rh/ColaboradoresPage.tsx` | 213 | 10 | DataTable, ConfirmDialog, useResourceForm, useBusca |
| `features/rh/tabs/ComissoesTab.tsx` | 15 | 0 | DataTable |
| `features/rh/tabs/ExamesTab.tsx` | 42 | 0 | DataTable |
| `features/rh/tabs/FamiliaTab.tsx` | 29 | 0 | AsyncState |
| `features/rh/tabs/PontoTab.tsx` | 31 | 0 | DataTable |
| `features/rh/tabs/RecessosTab.tsx` | 14 | 0 | DataTable |
| `features/rh/tabs/TurnosTab.tsx` | 36 | 0 | DataTable |
| `features/satelites/CercasTab.tsx` | 759 | 0 | ConfirmDialog, AsyncState |
| `features/satelites/MapaAoVivoTab.tsx` | 306 | 0 | AsyncState |
| `features/satelites/MonitoraPage.tsx` | 59 | 4 | ResourceList |
| `features/satelites/RotaTab.tsx` | 410 | 0 | AsyncState, StatCard |
| `features/satelites/SatelitesPage.tsx` | 78 | 3 | AsyncState |
| `features/seguranca/SegurancaPage.tsx` | 158 | 0 | FormDialog |
| `features/superadmin/auth.tsx` | 71 | 0 | - |
| `features/superadmin/SaAuditoriaPage.tsx` | 67 | 0 | ResourceList |
| `features/superadmin/SaCidadesPage.tsx` | 84 | 0 | ResourceList, FormDialog |
| `features/superadmin/SaDashboardPage.tsx` | 141 | 0 | AsyncState, StatCard |
| `features/superadmin/SaEmpresasPage.tsx` | 299 | 0 | ResourceList, FormDialog, ConfirmDialog, AsyncState, StatCard |
| `features/superadmin/SaLayout.tsx` | 186 | 0 | - |
| `features/superadmin/SaLoginPage.tsx` | 134 | 0 | - |
| `features/superadmin/SaMigracaoPage.tsx` | 506 | 0 | ResourceList, FormDialog, AsyncState |
| `features/superadmin/SaPlanosPage.tsx` | 159 | 0 | ResourceList, FormDialog |
| `features/superadmin/SaRoutes.tsx` | 53 | 0 | - |
| `features/valegas/ValeGasPage.tsx` | 106 | 0 | DataTable, useBusca |
| `layouts/AppShell.tsx` | 250 | 0 | - |
| `layouts/EmpresaSwitcher.tsx` | 126 | 0 | - |
| `lib/auth.tsx` | 221 | 0 | - |
| `lib/duas-abas.test.tsx` | 238 | 0 | - |
| `main.tsx` | 57 | 0 | - |
| `routes.tsx` | 193 | 0 | - |

Total: 165 arquivos TSX. Diretórios de funcionalidades: 30.

## Evidência de validação do planejamento

- Inventário inclui todos os arquivos TSX existentes no momento da execução, incluindo testes.
- Contraste da landing calculado por luminância relativa sRGB: branco/#FF6200 = 3,0003973392; #1F1F1F/#FF6200 = 5,4935982178.
- Não foram realizadas capturas de tela, build ou testes da aplicação. A tentativa de obter navegador retornou indisponibilidade.
- Documentação adicionada sem modificar código de produção ou o checkpoint de implementação SaaS.
