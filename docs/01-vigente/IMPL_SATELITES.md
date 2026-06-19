# PRD DE IMPLEMENTAÇÃO — Satélites (RH / Frota / Vale-Gás / Relatórios / Monitoramento / Integrações)

> Módulos periféricos (migram em paralelo). Auditado: controllers por domínio. Cada um vira
> uma página completa com abas (reorganização do MAPA_NAVEGACAO_ALVO). Detalhar SPEC fina ao
> implementar cada um; este PRD fixa escopo, de-para e DoD.

## RH (Colaboradores)
Controllers: Colaborador, Colaboradorcomissoes, Colaboradorfamilia(scaffold vazio — reescrever),
Cargo, Recessos, Recessotipo, Tipoexame, Setorcolaboradores, Reportcolaborador, Reportcomissoes.
**Página-alvo:** **Colaboradores** (ficha) com abas: Dados, Cargo, **Comissões** (cálculo — refatorar
Service), **Família** (RelationManager, hoje scaffold vazio), **Exames**, **Recessos**. Config: Cargos/
Tipo de exame/Tipo de recesso (abas de Config). Relatórios → Central de Relatórios.

## FROTA (Veículos)
Controllers: Veiculo, Veiculoabastecimento, Veiculotrocaoleo (F0: rownum→limit corrigido),
Veiculopneu, Veiculoentradasaida, Veiculodocumento(scaffold), Veiculotipo, Tipocombustivel, Reportveiculos.
**Página-alvo:** **Veículos** (ficha por veículo) com abas: Dados, Abastecimentos, Trocas de óleo,
Pneus, Entrada/Saída, Documentos — timeline de manutenção. Config: Tipo de veículo/combustível.

## VALE-GÁS / CONVÊNIO
Controllers: Valegasvenda (gerarCodigo corrigido F0), Valegasbaixar, Valegascancelar, Valegasconsulta,
Conveniogbgestao (charts — parametrizar SQL), Fechamentoconvenio, Reportvalegas, Reportconvenio.
**Página-alvo:** **Vale-Gás** (ciclo: venda→consulta→baixar→cancelar, com status visível);
**Convênio** (gestão + fechamento + dashboards via query service parametrizado).

## RELATÓRIOS (26 Report*Controllers)
**Página-alvo:** **Central de Relatórios** — área única com categorias (Administrativo/Gestão/
Financeiros/Operacionais/Vendas + Vale-Gás/Checklists/Dashboard), cada relatório = filtros + preview +
export (PDF/XLSX via PhpSpreadsheet já migrado). Mover SQL pesado p/ query services parametrizados
(fechar whereRaw/$_GET interpolados remanescentes).

## MONITORAMENTO GPS (App\Monitora — schema/guard próprios)
Controllers: Api, Auth, Cadastro, Cerca, Config, Empresa, EmpresasGrupo, Evento, Rastreamento, Rota,
Search, Users, Veiculo. **Página-alvo:** **Monitoramento** (mapa + status de veículos/entregas/cercas),
jobs de rastreamento preservados. Alinhar permissões ao RBAC do app novo (tinha menu/permissões próprios).
(F0: getEmpresas use Session corrigido; verificar hardcode empresa 2 em SearchController:65.)

## INTEGRAÇÕES / NOTIFICAÇÕES / MISC
Controllers: Appnotification (fcm — F0 corrigiu), Notificacoes, Pix, Dashboardgerencial, Sorteio,
Search. Configurações Gerais, Androids, App Gás em Casa, Layout de Cobranças, Ocorrências de Remessas.
**Página-alvo:** **Administração → Integrações/Configurações** + Notificações (envio/histórico).
Encapsular integrações (FCM/Pix/eRede) em Services com chaves via config(). Descartar obsoleto.

## REORGANIZAÇÃO (resumo de-para)
Cada domínio acima: telas dispersas → 1 página com abas (ficha por entidade) + Config. Relatórios →
Central única. Nada eliminado; funções escondidas (ex.: timeline de manutenção do veículo, ciclo do
vale-gás) ficam visíveis.

## DoD (por módulo satélite, ao implementar)
1. SPEC fina auditada do módulo (campos/ações/sub-recursos) antes de codar.
2. Página com abas cobrindo as telas legadas (de-para 100%).
3. Scaffolds vazios (Colaboradorfamilia, Veiculodocumento) reescritos como RelationManager.
4. Relatórios na Central; SQL parametrizado.
5. Testes + suíte verde.
