# PRD — Relatórios / Dashboards  ·  D12

- **Status:** ✅ pronto
- **Criticidade:** 🟢 consulta (não grava) · alguns 🔴 por VALOR (DRE/Balanço/fiscais)
- **Decisão:** **REESCREVER** (camada de leitura; ótimo para modernizar com baixo risco de escrita)

---

## 1. Escopo
- **31 controllers Report\*** + `Dashboardgerencial`, `Vendasmensaisgestao`,
  `Fechamentomensalgestao`, `Metavenda`, `Inconsistencia`.
- Repositories: `FechamentomensalDreRepository`, `FechamentomensalBalancoRepository`.
- Não tem tabelas próprias relevantes — **lê** de todos os outros domínios.

## 2. O que o módulo FAZ
- Relatórios operacionais (clientes, vendas, entregas, estoque, comissões,
  comodato, veículos, vale-gás, aniversariantes...) e **gerenciais/fiscais**
  (DRE, Balanço, fluxo de caixa, dashboard gerencial, vendas mensais, metas).
- Saída em tela, PDF (dompdf) e XLS.

## 3. Como FAZ hoje
- **Maior concentração de SQL cru/funções Oracle** do sistema (já traduzido na
  Fase Postgres: TO_DATE/TO_CHAR/LISTAGG/ROWNUM/CONNECT BY/generate_series/
  WITH RECURSIVE/date_trunc/ADD_MONTHS...).
- Muitos lêem **`$_GET` direto** (`count($_GET)>0`) — só rodam a query com filtro.
- DRE/Balanço usam árvore de plano de contas (WITH RECURSIVE traduzido).

## 4. Gambiarras / dívida técnica
- [ ] `$_GET` direto (padrão em quase todos os Report\*).
- [ ] SQL cru gigante embutido nos controllers (strings concatenadas).
- [ ] Geração de PDF/XLS acoplada ao controller.
- [ ] Lógica de cálculo (totais, subtotais) misturada com apresentação.

## 5. Riscos de tocar
- **Baixo para ESCRITA** (relatório só lê). **Médio/alto para VALOR** nos fiscais
  (DRE/Balanço/fluxo de caixa): número errado engana gestão / cruza com fiscal.
- Como só leem, reescrever a apresentação é seguro; o cuidado é manter o VALOR.

## 6. Estado de compatibilidade Postgres
- ✅ 30 index + 6 com filtro validados (200). Funções Oracle traduzidas.
- 🟡 **valor numérico dos fiscais (DRE/Balanço/fluxo) NÃO validado** — depende de
  baseline + dados reais (Frentes D/E/F). Sintaxe ok, valor a confirmar.

## 7. Visão REESCRITA (Laravel 12)
- **Camada de leitura** dedicada: Query Services / read models (SQL parametrizado,
  sem `$_GET` direto; filtros via FormRequest).
- **Apresentação** moderna: dashboards interativos (gráficos), export PDF/XLS via
  serviço dedicado, agendamento de relatórios.
- Reaproveitar as queries já traduzidas (não jogar fora o trabalho da Fase Postgres);
  encapsulá-las em Query objects testáveis.
- Os fiscais (DRE/Balanço) só "fecham" quando o baseline confirmar o valor.

## 8. DECISÃO e justificativa
- **Decisão: REESCREVER** (é camada de leitura — modernizar é seguro e de alto
  impacto visual).
- **Por quê:** relatórios não gravam → risco de escrita baixo; é onde o "visual
  moderno" mais aparece (dashboards). As queries já estão traduzidas; falta
  encapsular e dar UI nova.
- **Pré-requisitos:** parametrizar `$_GET`/whereRaw (Frente C); para os fiscais,
  baseline (Frente D) antes de confiar nos números.
- **Esforço:** médio (muitos relatórios, mas padrão repetível).
- **Ordem:** relatórios operacionais cedo (vitrine de UX); fiscais por último
  (dependem do baseline dos domínios fonte).
