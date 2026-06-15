# ÍNDICE — PRDs FIÉIS (linha-a-linha) · ctrl-web

> Estes PRDs foram escritos após **ler 100% das linhas** dos controllers de cada domínio
> (núcleos integralmente; periféricos caracterizados + varredura sistêmica). Diferente do
> PRD estratégico (`PRD/01..15_*.md`, por amostragem), aqui cada bug tem `arquivo:linha`.
> Total: ~55.000 linhas lidas nos 15 domínios.

## Os 15 domínios

| D | Domínio | Decisão | Arquivo |
|---|---------|---------|---------|
| 01 | Vendas / Pedidos | REFATORAR Pedido/Caixa · REESCREVER resto | [01_vendas_pedidos.md](01_vendas_pedidos.md) |
| 02 | NF-e / NFC-e / SAT / Tributação | **REFATORAR** (código mais maduro) | [02_nfe_fiscal.md](02_nfe_fiscal.md) |
| 03 | SPED (EFD ICMS/IPI + Contribuições) | REFATORAR (motor de registros) | [03_sped.md](03_sped.md) |
| 04 | Financeiro / Tesouraria | REFATORAR núcleo · REESCREVER cadastros | [04_financeiro.md](04_financeiro.md) |
| 05 | Clientes / CRM | REESCREVER (faseado) | [05_clientes.md](05_clientes.md) |
| 06 | Produtos / Estoque | REFATORAR motor · REESCREVER telas | [06_produtos_estoque.md](06_produtos_estoque.md) |
| 07 | Vale-Gás / Convênio / Cond.Pagamento | REFATORAR · REESCREVER | [07_valegas_convenio.md](07_valegas_convenio.md) |
| 08 | Colaboradores / RH | REESCREVER · REFATORAR comissões | [08_colaboradores.md](08_colaboradores.md) |
| 09 | Frota / Veículos | REESCREVER | [09_frota.md](09_frota.md) |
| 10 | Cadastros base / Geográfico | REESCREVER · REFATORAR Empresa(config) | [10_cadastros_base.md](10_cadastros_base.md) |
| 11 | Acesso / Permissões / Menu | REESCREVER | [11_acesso_permissoes.md](11_acesso_permissoes.md) |
| 12 | Relatórios / Dashboards | REESCREVER camada | [12_relatorios.md](12_relatorios.md) |
| 13 | API Mobile (App\Api) | **MANTER/REFATORAR** (mais moderno) | [13_api_mobile.md](13_api_mobile.md) |
| 14 | Monitoramento GPS (App\Monitora) | REESCREVER | [14_monitoramento.md](14_monitoramento.md) |
| 15 | Integrações / Notificações / Misc | REESCREVER · DESCARTAR obsoleto | [15_integracoes_misc.md](15_integracoes_misc.md) |

---

## 🔴 QUEBRADO EM PRODUÇÃO HOJE (Postgres ou typo) — corrigir já, via deploy GitHub Actions

> A varredura por amostragem das 98 telas só exercitou os `index`. Estes residem em
> fluxos de GRAVAÇÃO/AJAX/relatório e **quebram quando acionados**:

1. **Resíduos Oracle não traduzidos** (a afirmação "100% traduzido" estava errada):
   - `ReportCaixaController::getQueryCentroCusto/getQueryJurosDescontosCC` — **CONNECT BY/
     START WITH** ativo → relatório de caixa por centro de custo quebra.
   - `FechamentomaloteController::getParcelasStore` — **CONNECT BY** → fechamento de malote quebra.
   - `Planoconta/Centrocusto::isUsed/isUsedByConfig/isUsedByNF` — **ROWNUM + subquery sem
     alias** → cadastro de plano/centro filho quebra.
   - `AtualizarprecosController::updateStatementOracle` — **UPDATE (subquery) SET Oracle**
     → atualização de preços em massa quebra (+ SQLi).
   - **rownum órfão**: Vendaativa (4×), Maladireta/Posvenda (D05), Veiculotrocaoleo::getTrocas
     (D09), Reportvendasmalote/clientesaniversariantes/vendapdv (D12).
   - `updateLob()` (driver Oracle removido) — Empresa/EmpresasGrupo logo (D10, 8 lugares).
   - `ORA-02292`→ deveria ser SQLSTATE 23503 (delete geográfico, D10).
2. **Typos que viram fatal/dados errados:**
   - `DB::rdollback()` (Posvenda store, D05); `wwhere` (Caixa baixarduplicatas, D04);
     `catch(Excpetion)` (Spedcreditos destroy, D03); `catch(Exception)` sem import (D09);
     `$dada` em vez de `$data` → cheque recebido gravado sem banco (D04);
     `EstoqueProcessor::efetivarEstoquefisico` grava `id` no campo `empresa_id` (D06).
3. **Debug/dump em produção:**
   - `dd($data)` no Recessotipo::update (D08, editar recesso quebrado);
   - `dd()/dump()` no Nfemitida::getXmlByTxt (D02); `dd($nao)` ATIVO no Reportvendapdv (D12).
4. **TesteestoqueController** (D06) — controller de DEBUG exposto, sem authorize, **corrompe
   estoque real**; `Inventario::store/destroy` usa `$e` num `catch($ex)` (gravar/excluir quebra).
5. **`getEmpresas` sem `use Session`** (Monitora, D14) — fatal.
6. **`gerarCodigo` do vale-gás** (D07) — gera código duplicado (recursão sem retorno + coluna errada).

## 🔴 SEGURANÇA — corrigir já
- **AJAX bypass de autorização** (AuthorizeCustom:39, D11) — `if ($request->ajax()) return true`.
- **`'secret'` HMAC** no oauth do ERP (UsersController, D11) e **`sha1(APP_KEY)`** no
  NfwebController::getToken (D02/D13) — chave previsível. (App\Api já corrigido na Fase 1.)
- **`password` fora do `$hidden`** (User.php, D11).
- **SQLi** em escrita/porta pública: Atualizarprecos (D01, massa), ClienteController
  newClientWithPhone (D13, cadastro do app), Conveniogbgestao $produto (D07),
  Promotor/Cliente/geográficos (D05/D10), getLancamentosFinanceiros (D04), Vendaativa (D01).
- **Hardcode "empresa 2"**: Conveniogbgestao::getDataChartConvenioClientes (D07) e
  Monitora::getPedidosPendentes (D14) filtram empresa 2 fixa → quebra multi-empresa.

## 🟡 PADRÕES RECORRENTES (candidatos a sweep global / decisão de arquitetura)
- Controllers 100% vazios (scaffold morto): Clientecontato, Colaboradorfamilia,
  Veiculodocumento, Menu (ERP), Android.
- `destroy` retornando HTML `<br/>`; cadastros pequenos sem `DB::transaction`.
- `$_GET`/`$_POST` lidos direto; HTML montado no controller (Form::select, `<input>`, `<tr>`).
- Sobreposição de período com condições copy-paste idênticas/furadas (D05/D08/D10/D01).
- `unique` apontando coluna errada (grupo_id × empresa_id): Tiporecessos (D08),
  Nfgrupofiscal (D02).
- Controller↔controller (Pedido→Nf/Api; Caixa→Financeiro; Nfweb→Nfemitida; etc.).
- Stack EOL fiscal (NFePHP/PHPCFe/PHPExcel/Maatwebsite 2.1/laravelcollective) — bloqueia 6→8.

## Veredito de arquitetura (confirmado pela leitura)
- **Mais maduro → preservar (REFATORAR):** D02 NF-e (NfemitidaController), D13 App\Api
  (Passport/Repos), D01 PedidoController, motores (EstoqueProcessor, financeiro/caixa/
  cheque/boleto Processors, SpedProcessor, NfeImpostoProcessor).
- **Reescrever:** cadastros/CRUDs, geográfico, RH, frota, relatórios, monitoramento, CRM.
- **Ordem segura:** D11/segurança → cadastros base (D10/D06 cadastros) → estoque/financeiro
  motores (D06/D04) com baseline → fiscal (D02/D03) → vendas/pedido (D01) por último →
  monitoramento/API/relatórios em paralelo. Multi-tenant só depois de tudo (Fase 6).
