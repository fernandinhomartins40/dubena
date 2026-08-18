# PLANO DE IMPLEMENTAÇÃO — Modernização do ctrl-web (Gás em Casa / ctrl+)

> ⚠️ **SUPERSEDIDO de F4 em diante (2026-06-17):** o plano vigente passou a ser
> `PLANO_MODERNIZACAO_UX.md` (baseado nos PRDs `PRD/MODERN_*.md` e na decisão do cliente:
> sem menu-no-banco, navegação declarativa, página completa por entidade, RBAC). As fases
> F0–F3 e a F4A descritas abaixo **já foram concluídas e estão em produção** — mantidas aqui
> como referência histórica. Para o que vem a seguir, ver `PLANO_MODERNIZACAO_UX.md`.

> **Base de verdade:** os 15 PRDs FIÉIS (linha-a-linha) em `PRD/` (índice: `PRD/00_INDICE.md`).
> **Objetivo do user:** deixar a aplicação **100%** — moderna, **sem quebrar**, com **UX/UI
> moderna e intuitiva**.
> **Estratégia escolhida:** **Strangler in-place** no próprio ctrl-web (produção sempre no ar).
> **Stack alvo:** **Laravel 12 + PHP 8.3**. **UI/UX:** **Filament 3** (admin TALL) substituindo
> AdminLTE/Blade gradualmente.
> **Regras invioláveis:** mudanças via **deploy GitHub Actions** (não paramiko); **preservar
> regras fiscais**; **não quebrar apps publicados** (contratos da API D13/Passport); cada fase
> tem **portão de saída** com testes verdes em dev ANTES de produção.

---

## 0. Princípios (como NÃO quebrar)

1. **Caracterização antes de tocar regra fiscal/financeira.** Para todo módulo 🔴
   (Pedido, Caixa, NF, SPED, Estoque, Comissão, Convênio, Vale-gás), escrever **testes de
   caracterização** que capturam o comportamento ATUAL (números reais) ANTES de refatorar.
   Refatorar só com a rede de testes verde.
2. **Strangler por rota/módulo.** Cada módulo é migrado isoladamente; o resto continua no
   legado. A navegação nova (Filament) e a antiga (AdminLTE) coexistem atrás do mesmo login.
3. **REFATORAR o maduro, REESCREVER o frágil** (veredito dos PRDs):
   - **Preservar/refatorar:** NF-e (NfemitidaController), App\Api (Passport/Repos),
     PedidoController, e os motores (Estoque/Financeiro/Caixa/Cheque/Boleto/Sped/Imposto
     Processors). Extrair Services/Actions + testes; NÃO reescrever a regra do zero.
   - **Reescrever:** cadastros/CRUDs, geográfico, RH, frota, relatórios, monitoramento, CRM,
     permissões — em Filament com FormRequest/Policy/transação.
4. **Validar em dev → deploy → conferir em prod**, módulo por módulo (método que já funciona:
   `tests/Fluxo/NavegacaoPostgresTest.php` exige **200, não 302**).
5. **Toggle de UI por módulo** (feature flag): permite ligar a tela nova só quando pronta,
   com rollback instantâneo para a antiga.

---

## FASE 0 — ESTABILIZAÇÃO (corrigir o que QUEBRA hoje) · ~1–2 semanas · risco BAIXO

> Sem upgrade, sem UI nova. Só parar o sangramento. Tudo via deploy GitHub Actions.
> Fonte: `PRD/00_INDICE.md` §"QUEBRADO EM PRODUÇÃO" e §"SEGURANÇA".

### 0.1 Resíduos Oracle que quebram no Postgres (gravação/AJAX/relatório)
- [ ] `ReportCaixaController::getQueryCentroCusto` + `getQueryJurosDescontosCC` —
      CONNECT BY/START WITH → **WITH RECURSIVE** (usar helper `app/Helpers/SqlRecursivo.php`).
- [ ] `FechamentomaloteController::getParcelasStore` — CONNECT BY → WITH RECURSIVE.
- [ ] `Planoconta`/`Centrocusto` `isUsed/isUsedByConfig/isUsedByNF` — remover `WHERE ROWNUM<=1`
      + dar alias à subquery (ou EXISTS).
- [ ] `AtualizarprecosController::updateStatementOracle` — `UPDATE ... FROM` (PG) + **bindings**.
- [ ] rownum órfão → `LIMIT`/subquery: Vendaativa (4×), Maladireta/Posvenda (D05),
      Veiculotrocaoleo::getTrocas (D09), Reportvendasmalote/clientesaniversariantes/vendapdv (D12).
- [ ] `updateLob()` (8 lugares D10) → update Eloquent normal de coluna `bytea` (logo empresa/grupo).
- [ ] `ORA-02292` → SQLSTATE `23503` (delete geográfico Bairro/Cidade/Rua, D10).

### 0.2 Typos/bugs que viram fatal ou gravam errado
- [ ] `DB::rdollback()`→`DB::rollBack()` (Posvenda store, D05).
- [ ] `->wwhere`→`->where` (Caixa baixarduplicatas, D04) + colunas snake do recibo CR.
- [ ] `catch(Excpetion)`→`\Exception` (Spedcreditos destroy, D03); `catch(Exception)` sem
      import → `\Exception` (Veiculo* D09; Recessos/Tiporecessos D08).
- [ ] `$dada`→`$data` no banco_id (Chequerecebido, D04 — cheque gravado sem banco).
- [ ] `EstoqueProcessor::efetivarEstoquefisico` — `$estoquefisico->empresa_id` (não `->id`) (D06).
- [ ] `Inventario::store/destroy` — `$e` no `catch($ex)` → unificar variável (D06).
- [ ] `gerarCodigo` do vale-gás — retorno da recursão + checar coluna `codigo` (não
      `prevendasequencia`) (D07).
- [ ] `Promocao::update` — `|` faltando entre `max:255` e `unique` (D01).
- [ ] `ReportFinanceiro::setFiltersReport` — parênteses no `.`/`==` (D12).

### 0.3 Debug/lixo em produção
- [ ] Remover `dd()/dump()`: Recessotipo::update (D08), Nfemitida::getXmlByTxt (D02),
      Reportvendapdv:861 `dd($nao)` ATIVO (D12); remover `// dd` comentados e blocos XLS mortos.
- [ ] **DELETAR `TesteestoqueController`** + rota (D06 — corrompe estoque real).
- [ ] `getEmpresas` (Monitora D14) — adicionar `use Session` (fatal hoje).
- [ ] Debug `produto_id==104` no EstoqueProcessor (D06); código morto pós-`return`
      (Monitora getCercaRastreamento D14).

### 0.4 Segurança crítica
- [ ] **AJAX bypass** (AuthorizeCustom:39, D11) — aplicar a MESMA regra de permissão em AJAX.
- [ ] `'secret'` HMAC → env (UsersController D11); `sha1(APP_KEY)`→APP_TOKEN_KEY/hash_equals
      (NfwebController D02/D13 — igual ao App\Api já corrigido).
- [ ] `password` no `$hidden` (User.php D11).
- [ ] **SQLi (parametrizar/bindings):** Atualizarprecos (D01), ClienteController
      newClientWithPhone (D13, porta pública — **prioridade**), Conveniogbgestao $produto (D07),
      Promotor/Cliente/geográficos (D05/D10), getLancamentosFinanceiros (D04), Vendaativa (D01),
      Notificacoes::meLiga (D15).
- [ ] **Hardcode "empresa 2"** → empresa/grupo do user logado: Conveniogbgestao
      getDataChartConvenioClientes (D07) e Monitora getPedidosPendentes (D14).

### 0.5 Bugs de dados/lógica
- [ ] `unique` apontando coluna errada (grupo_id×empresa_id): Tiporecessos (D08),
      Nfgrupofiscal (D02).
- [ ] Colaborador::show grupo_id (D08); Empresaconfig::update:419 condição invertida (D10);
      sobreposição de período copy-paste (D05/D08/D10/D01/Promocao).
- [ ] `Appnotification::update` title/body → fcmtitle/fcmbody (D15).

**Portão de saída F0:** suíte dev verde + os fluxos antes quebrados (caixa centro de custo,
malote, atualizar preços, cheque recebido, estoque físico, vale-gás, NF import) exercitados
com 200. Deploy + conferência em prod. **Snapshot de segurança** (rotacionar segredos).

---

## FASE 1 — REDE DE TESTES (caracterização) · ~2–3 semanas · risco BAIXO

> Pré-requisito ABSOLUTO para refatorar os módulos fiscais/financeiros sem quebrar.

- [ ] **Baseline fiscal/financeiro**: dataset anonimizado representativo (NFs homologadas,
      pedidos, financeiro, estoque, SPED de um mês fechado).
- [ ] **Testes de caracterização (golden master)** dos motores e fluxos 🔴:
  - Pedido: store/update + máquina de estados (estoque+financeiro+NF+vale-gás+estorno).
  - EstoqueProcessor: movimentação/custo médio/fechamento/físico.
  - financeiroProcessor/caixaProcessor: baixa parcial/rateio/estorno/cheque/boleto.
  - NfeImpostoProcessor: cálculo ICMS/ST/PIS/COFINS/IPI por cenário (snapshot dos valores).
  - SpedProcessor: gerar EFD de mês fechado e comparar com arquivo validado no PVA.
  - Comissão (D08), Convênio/Vale-gás (D07/D04): fechamento e financeiro gerado.
- [ ] CI roda a suíte a cada PR (GitHub Actions) — bloqueia merge se quebrar baseline.

**Portão F1:** golden master verde e reprodutível; números fiscais conferidos.

---

## FASE 2 — UPGRADE DE STACK 5.8 → 6 → 8 → 12 + PHP 8.3 · ~4–6 semanas · risco MÉDIO-ALTO

> Incremental, um salto por vez, suíte (F1) verde a cada salto. NÃO mexer em regra aqui —
> só compatibilizar. Cada salto é um deploy validado.

- [ ] **5.8 → 6 LTS**: helpers removidos (`array_get`/`str_random`→Str::), pacotes.
- [ ] **6 → 7 → 8**: trocar/compatibilizar as **libs EOL** (o que bloqueava):
  - Excel: **Maatwebsite 2.1 → laravel-excel 3.x**; **PHPExcel → PhpSpreadsheet** (relatórios
    D12, fechamentos D04, convênio D07). Maior item do upgrade.
  - **laravelcollective/html (Form::) →** Blade components / forms nativos (some no caminho da
    UI nova; criar shim temporário se necessário).
  - **NFePHP / PHPCFe**: subir para versões compatíveis com PHP 8 (validar emissão em
    homologação SEFAZ com a suíte fiscal F1).
  - Passport: subir versão (D13 — não quebrar tokens dos apps publicados).
- [ ] PHP 7.4 → 8.1 → 8.3 (Dockerfile dev+prod); doctrine/dbal já em 2.13 (ok PG).
- [ ] **8 → 12**: ajustes finais (casts, middleware, rotas).
- [ ] Atualizar `docker-compose` (dev+prod) + workflows (composer install já com `--no-plugins`).

**Portão F2:** Laravel 12 / PHP 8.3 rodando em prod; suíte F1 verde; **emissão fiscal
validada em homologação**; apps publicados continuam autenticando.

---

## FASE 3 — FUNDAÇÃO DA UI MODERNA (Filament 3) · ~2 semanas · risco BAIXO

- [ ] Instalar **Filament 3**; tema/branding Gás em Casa (logo, cores, dark mode), layout
      responsivo, navegação por grupos de módulos.
- [ ] **Autenticação/permissões unificadas** (entra junto com D11 reescrito): login Filament
      reusando guard atual; **spatie/laravel-permission** OU manter `menuusers` mapeado em
      Policies. **Sem o bypass de AJAX**; permissão checada por Policy/Gate.
- [ ] **Feature flag de UI por módulo** (rota nova Filament vs. tela antiga) — coexistência +
      rollback.
- [ ] Componentes compartilhados: cabeçalho de relatório, seletor de empresa, máscara de
      moeda/decimais (helpers `*NumeroDecimalOracle` → cast/value object), upload de logo (bytea).

**Portão F3:** Filament no ar (1 módulo-piloto: um cadastro simples) coexistindo com o legado.

---

## FASE 4 — MIGRAÇÃO MÓDULO A MÓDULO (Strangler) · contínua · risco por módulo

> Ordem segura (do índice): **fundação → cadastros → motores → fiscal → vendas → satélites**.
> Cada módulo: (a) caracterização/ajuste, (b) UI Filament, (c) Policy/FormRequest/transação,
> (d) validar dev→prod, (e) ligar flag. **DELETAR scaffolds vazios** (Clientecontato,
> Colaboradorfamilia, Veiculodocumento, Menu, Android) no caminho.

### Bloco A — Fundação (primeiro)
- [ ] **D11 Acesso/Permissões/Menu** → REESCREVER em Filament + Policies; menu declarativo
      (sem HTML no Model, sem eager 100 níveis, reflete permissão na hora). É a base da navegação.

### Bloco B — Cadastros base (vitrine do padrão novo, baixo risco)
- [ ] **D10 Cadastros/Geográfico** → recursos Filament limpos (Banco, Região, Cidade/Bairro/Rua
      com API JSON, Documento, Motivo, Sorteio, Empresabens, Setor, EmpresasGrupo). Empresa →
      UI por abas (cadastro/fiscal/SAT/SPED/certificado), schema preservado.
- [ ] **D06 cadastros** (Produtoclasse, Tipocombustivel, Unidademedida) + **Produto** (UI por
      abas; regras fiscais GLP/origens/no-inativa-com-estoque preservadas).
- [ ] **D07 Condicaopagamento** (config central — refatorar cálculo de parcelas testável).
- [ ] **D08 cadastros RH** + **D09 Frota** (Service de Km/Manutenção) + **D05 cadastros de apoio**.

### Bloco C — Motores transacionais (REFATORAR com baseline F1)
- [ ] **D06 Estoque** → motor como Domain Service testável (saldo/custo médio/fechamento/
      físico); telas (requisição/transferência/acerto/inventário) em Filament.
- [ ] **D04 Financeiro/Tesouraria** → Services (Lançamento/Baixa/Estorno/Cheque/Boleto/
      Fechamentos/DRE/Balanço); plano/centro de contas reescritos; UI Filament.
- [ ] **D07 Vale-gás** + **D08 Comissões** → Services (efeito financeiro; com baseline).

### Bloco D — Fiscal (REFATORAR, preservar a regra)
- [ ] **D02 NF-e/NFC-e/SAT** → extrair **NfBuilder** (elimina a duplicação dos 4 fluxos) +
      Actions (Emitir/Transmitir/Cancelar/CCe/Inutilizar/ImportarXML); manter NFePHP/PHPCFe;
      tabelas tributárias + Impostonf com UI nova. **Validado em homologação**.
- [ ] **D03 SPED** → bindings no getNf, arquivo em storage com nome único, geração **assíncrona
      (job)**; árvore de blocos em config; classes de registro preservadas.

### Bloco E — Vendas/Pedido (POR ÚLTIMO entre os transacionais)
- [ ] **D01 Pedido/Caixa** → Domain/Service + Actions por transição (Criar/Confirmar/Cancelar/
      TrocarSetor) com transação atômica; eventos de domínio; **UI de venda nova** consumindo
      os Services; preservar contrato mobile (D13). Atualizarprecos reescrito (já corrigido em F0).
- [ ] **D05 Cliente core** (ficha 360º, convênio com baseline) + Maladireta/Posvenda/Promotor/
      Promoção como Query Services seguros.

### Bloco F — Satélites (em paralelo, baixo risco)
- [ ] **D12 Relatórios/Dashboards** → Query Services + laravel-excel/dompdf atual; relatórios
      pesados como jobs; filtros tipados.
- [ ] **D13 API mobile** → manter arquitetura (Passport/Repos); trocar elo HTTP interno
      (ApiResources) por chamadas internas; aposentar tabelas espelho; token por identidade.
- [ ] **D14 Monitoramento** → UI de mapa moderna (broadcasting/WebSocket); unificar cadastro de
      veículo com D09; Policies por empresa.
- [ ] **D15 Integrações** → notificações via canais Laravel; mover Logcerca p/ D14; triar/
      descartar obsoletos (Android/Appgiro/Appvideo) após confirmar consumo pelos apps.

**Portão F4 (por módulo):** tela nova validada (dev→prod), flag ligada, tela antiga
aposentada, scaffolds mortos deletados, suíte verde.

---

## FASE 5 — LIMPEZA & HARDENING · ~1–2 semanas
- [ ] Remover AdminLTE/Blade legado e flags de UI já 100% migradas.
- [ ] Sweep final: nenhum `$_GET/$_POST` cru, nenhum HTML no controller, nenhum
      controller↔controller, nenhum `destroy` retornando `<br/>`.
- [ ] Rotacionar TODOS os segredos; revisão de segurança final; LGPD (logs/scrub).
- [ ] Documentação + cobertura de testes consolidada.

## FASE 6 — MULTI-TENANT (só depois de tudo) · adiada por decisão
- [ ] 1 banco + `tenant_id` + RLS (Postgres), reusando `empresa_id`/`grupo_id`. Ver
      `decisao-adiar-multitenant`. Só inicia com F0–F5 fechadas.

---

## Sequência recomendada (visão macro)

```
F0 Estabilização ──► F1 Testes ──► F2 Upgrade 12/8.3 ──► F3 Filament base
                                                              │
                         ┌────────────────────────────────────┘
                         ▼
F4: D11 → D10/D06cad/D07cond/D08cad/D09 → [D06estoque, D04fin] → [D02 NF, D03 SPED]
        → [D01 Pedido/Caixa, D05 Cliente] → [D12, D13, D14, D15 em paralelo]
                         │
                         ▼
                 F5 Limpeza ──► F6 Multi-tenant
```

## Riscos & mitigações
- **Quebrar fiscal:** mitigado por F1 (golden master) + validação em homologação SEFAZ.
- **Quebrar apps publicados:** versionamento retrocompatível da API (D13); testar getToken/
  pedido/track a cada deploy.
- **Upgrade travar por lib EOL:** F2 isola a troca de cada lib com a suíte; Excel/Form são os
  maiores — encarar cedo.
- **Regressão silenciosa:** feature flag por módulo + rollback instantâneo; `NavegacaoPostgresTest`
  exige 200 (não 302) em dev e prod.
- **Escopo:** F4 é incremental e entregável módulo a módulo — valor contínuo, sem big-bang.

## Estimativa macro (1 dev sênior)
- F0 ~1–2 sem · F1 ~2–3 sem · F2 ~4–6 sem · F3 ~2 sem · F4 ~12–20 sem (por blocos) · F5 ~1–2 sem.
  Total ~6–9 meses até "100% moderno", com produção no ar o tempo todo e entregas a cada módulo.
