# PRD FIDEDIGNO (linha-a-linha) — Relatórios / Dashboards · D12

> 32 controllers (~11.600 linhas). Lidos integralmente: ReportController(663 — base),
> DashboardgerencialController(292), ReportFinanceiroController(488, amostra), trecho
> crítico do ReportCaixaController(1258, getQueryCentroCusto/JurosDescontos).
> **Varredura sistêmica (Grep) dos 32** para rownum/connect-by/dd/$_GET/whereRaw — os
> achados abaixo são verificados linha:arquivo. Já lidos em outros domínios:
> Vendasmensaisgestao (D01), Conveniogbgestao (D07), Fechamentomensalgestao (D04).

- **Status:** ✅ pronto (fiel — base + dashboards lidos; 32 varridos por padrão)
- **Criticidade:** 🟡 leitura (não grava) · **🔴 dashboards financeiros (DRE/Balanço) + ReportCaixa**
- **Decisão:** **REESCREVER** (camada de relatórios é repetitiva e insegura) — exceto a
  regra de DRE/Balanço (Repositories, que ficam) e o motor de impostos

---

## 1. O que o domínio FAZ (verificado)
- **~30 relatórios** seguindo o MESMO padrão: tela de filtro (`filtro*`) → relatório
  (`report*`) que monta query + gera **PDF (dompdf)** e/ou **XLS (Maatwebsite 2.1 +
  PHPExcel legado)**. Parâmetros chegam por `$par` explodido (`|`/`,`) ou por `$_GET`.
  Cobrem: clientes, aniversariantes, follow-up, vendas, vendas PDV, vendas malote,
  resumo de vendas, comissões, colaborador, comodato, convênio, vale-gás, entregas,
  estoque, financeiro (contas a pagar/receber), caixa, movimentação, NF emitidas/
  recebidas, promoções, promotor, questionários, veículos, logs, log de senha.
- **Dashboards gerenciais** (`getDashboard`/`getDre`/`getDataMarketShare`/...):
  Dashboardgerencial, Vendasmensaisgestao, Conveniogbgestao, Comodatogestao,
  Fechamentomensalgestao — gráficos meta×realizado, DRE, Balanço, market share, venda
  diária. Cálculo de DRE/Balanço delegado aos `Fechamentomensal{Dre,Balanco}Repository`.

> Regra real a preservar: as queries fiscais/gerenciais (DRE, Balanço, market share,
> giro, comissão, validação de numeração de NF) são valiosas; a **camada de entrega**
> (PDF/XLS/HTML montado à mão) é descartável.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 QUEBRADO no Postgres (resíduos Oracle ATIVOS)
- **ReportCaixaController — CONNECT BY / START WITH (Oracle) NÃO TRADUZIDO e ATIVO** em
  `getQueryCentroCusto` (1120-1121, 1128-1129, 1145) e `getQueryJurosDescontosCC`
  (1187, 1228): `start with id = $pai connect by prior id = paicentrocusto_id`. ⚠️ A
  memória dizia "ReportCaixa CONNECT BY traduzido" — **traduziu ALGUNS blocos** (os com
  comentário "// Oracle…→Postgres" em 598/877/961/1033) **mas deixou estes** → o
  **relatório de caixa por centro de custo QUEBRA no Postgres**.
- **`rownum <= 2` (Oracle) ATIVO em 3 relatórios** (subquery de telefone):
  ReportvendasmaloteController:336, ReportclientesaniversariantesController:464,
  ReportvendapdvController:660 → **quebram no PG**. (Os demais rownum/listagg já foram
  traduzidos — comentários confirmam em Reportclientes/ResumoVendas/Movimentacao/Entregas.)

### 🔴 Debug em produção / input cru
- **ReportvendapdvController:861 — `dd($nao);` ATIVO** (não comentado!): dump-and-die no
  relatório de venda PDV → mata a requisição. **Remover.**
- **ReportnfrecebidaController:27 — `$_GET["id"]` direto.**
- **`$_GET` lido direto** na maioria dos relatórios (`count($_GET)>0` + acesso por chave)
  — em geral via Eloquent binding ou Carbon; mas ReportCaixa/Dashboards interpolam datas/
  ids/empresa em SQL cru.

### 🔴 Segurança (SQLi) e authorize
- **ReportCaixa::getQueryCentroCusto/JurosDescontosCC** interpolam `$datainicio`/`$datafim`/
  `$empresa`/`$tipo`/`$pai`/`$juros_id`/`$desconto_id` em SQL cru (`DB::select`) → **SQLi**.
- **Dashboardgerencial / Vendasmensaisgestao / Conveniogbgestao** montam SQL gigante com
  Session id + `\Input::get` interpolados (datas via Carbon; ids/produto crus).
- **Dashboards financeiros SEM authorize** (getDre/getDetalhes/getCentroCustos/
  getDashboard) — DRE/Balanço/market share acessíveis via AJAX (bypass AJAX do D11).

### 🟠 Bugs funcionais
- **ReportFinanceiro::setFiltersReport:63,67 — precedência `.` vs `==`**: `' Ordem: ' .
  $ordem == 'C' ? ...` (sem parênteses) → `('Ordem: '.$ordem) == 'C'` sempre false →
  texto do filtro sempre mostra "Data Emissão/Vencimento". Cosmético, mas errado.
- Hardcodes `tipo_glp IN (3) AND PESOLIQUIDO IN (13)` (P13) embutidos nos dashboards.

### 🟡 Dívida estrutural
- **Camada de entrega obsoleta**: PDF dompdf + **XLS via Maatwebsite 2.1/PHPExcel legado**
  (stack EOL, bloqueia upgrade do framework); HTML/estilos montados à mão célula a célula.
- **Parâmetros por string explodida** (`$par` com `|`/`,`) em vez de request tipado —
  frágil, sem validação.
- **Logo inconsistente**: uns usam `imagecreatefromstring($logo)` direto, outros
  `base64_decode($logo)` — reflete a dívida do campo logo (bytea vs base64).
- **`// dd` comentados** espalhados (inócuos): Reportcolaborador:493, Reportveiculos,
  Reportestoque, Reportquestionarios, ReportController.
- **Geração síncrona** (relatório pesado dentro do request) — deveria ser job/stream.

### ✅ O que está BOM
- Maioria dos relatórios usa **Eloquent com whereIn/whereBetween/bindings** (sem SQLi) —
  só os dashboards e o ReportCaixa têm SQL cru. ReportController herda `middleware('auth')`.
- Boa parte das traduções Oracle→PG **foi feita e documentada** (comentários em
  Reportclientes/ResumoVendas/Movimentacao/Entregas/ReportCaixa parcial).
- DRE/Balanço/market share delegados a Repositories (regra isolada).
- Validação de numeração de NF (Spedfiscal/relatórios) é regra útil.

## 3. Especificação do REESCRITO (Laravel 12)
- **Camada de relatórios → REESCREVER**: Query Services parametrizados (bindings, sem
  rownum/connect-by/$_GET cru); exportação via **laravel-excel atual + dompdf/snappy**;
  relatórios pesados como **jobs** com download. Filtros via FormRequest tipado (não `$par`).
- **Manter** as regras de DRE/Balanço (Repositories) e o motor de impostos; só trocar I/O.
- **Dashboards** → endpoints com authorize + bindings; gráficos via API JSON (já são).
- **Padronizar logo** (bytea) e cabeçalho de relatório num componente único.

## 4. DECISÃO
- **Decisão: REESCREVER** a camada de relatórios (repetitiva, insegura, stack EOL);
  preservar as queries de regra (DRE/Balanço/market share/giro/comissão).
- **Quick wins aplicáveis JÁ (compat/segurança):**
  (a) **traduzir CONNECT BY/START WITH do ReportCaixa** (caixa por centro de custo quebrado no PG);
  (b) **rownum<=2 → limit** em Reportvendasmalote/clientesaniversariantes/vendapdv;
  (c) **remover `dd($nao)` ATIVO** do ReportvendapdvController:861;
  (d) parametrizar SQL cru do ReportCaixa/Dashboards (SQLi);
  (e) **authorize** nos dashboards financeiros (DRE/Balanço);
  (f) corrigir precedência `.`/`==` no ReportFinanceiro::setFiltersReport.
- **Pré-requisitos:** D02/D04/D06 (relatórios leem NF/financeiro/estoque); baseline dos
  números (DRE/Balanço conferidos). Não bloqueia escrita (é leitura) — pode vir em paralelo.
- **Esforço:** alto em volume (32 telas), baixo em risco unitário (cada relatório é simples).
- **Ordem:** quick wins de compat já (ReportCaixa/rownum/dd quebram em prod); reescrita da
  camada pode ser incremental, depois dos domínios transacionais.
