# PLANO MESTRE DE CONCLUSÃO DA MODERNIZAÇÃO
## ERP-NOVO + SPA → substituição integral do CTRL-WEB

> **Base única deste plano:** os achados de `docs/AUDITORIA_PARIDADE_MODERNIZACAO.md`
> (auditoria forense linha-a-linha, ~103k LOC). Este documento **não refaz a auditoria,
> não gera novo inventário nem novas estimativas de aderência** — apenas converte cada achado
> em backlog executável. Cada fase cita o achado de origem entre `〔…〕`.
> Data: 2026-06-24.

---

## DECISÃO ESTRATÉGICA POR FUNCIONALIDADE (princípio: não copiar o legado cegamente)

Para cada funcionalidade do CTRL-WEB identificada na auditoria, a decisão de tratamento:

| Funcionalidade legada | Decisão | Justificativa técnica (achado) |
|---|---|---|
| Núcleo venda/estoque/financeiro/caixa | **Migrar (já feito) + completar** | Já reescrito melhor (state machine, gerador único, invariantes). Falta só expor métodos órfãos 〔§3 Caixa/Financeiro〕 |
| ~20 CRUDs de apoio (Cargo, Estadocivil, Parentesco, Telefonetipo, Documentotipo, Unidademedida, Tipocombustivel, Tipoexame, situações/tipos diversos) | **Centralizar** num hub "Cadastros de Apoio" tabbed | Auditoria §6/§7: já iniciado em `CadastroApoioTab`; elimina ~15 telas |
| 3+ telas `*ConfigPage` (Cliente/Financeiro/Colaborador/Produto) | **Unificar** em hub de Configurações | §7 — reduz navegação |
| 26 Report*Controller (1 tela por relatório) | **Substituir** por central de relatórios com filtros | §6/§7 — `RelatoriosPage` já aponta o caminho; 15 faltam |
| Conciliação contábil CONSISA | **Migrar** (integração externa) | §3 🔴 não migrado; é requisito contábil |
| Boleto/CNAB Caixa+Itaú | **Migrar** (driver real) | §3 ⚠️ driver Fake; sem isso não cobra |
| NF de entrada (importXml→estoque+CP) | **Expor** (service pronto, sem rota) | §3 🔴 capacidade morta no HTTP |
| Cupom SAT via WebSocket local | **Substituir** abordagem (driver/integração explícita) | §3 — acoplamento a WebSocket local é débito; modernizar como gate |
| Mala direta | **Migrar simplificado** | §3 🟡 ausente; abordagem moderna (segmentação + export/integração e-mail) |
| Inconsistências rua/bairro com `UTL_MATCH`/`MINUS` (Oracle) | **Substituir** por similaridade compatível (pg_trgm/levenshtein) | §12 bug Oracle-only incompatível com Postgres |
| `Session::get('empresa_padrao')` em 126 controllers | **Eliminado/substituído** (já) | §1 — `TenantContext` + scope |
| Bugs herdados (organizeNotifications, `=` em comissão, grupo_id=id) | **Não reproduzir** | §12 — não portar os defeitos |
| Auditoria/logs (revisions, Logsenha, Logcerca) | **Migrar modernizado** (audit trail unificado) | §3 🔴 — compliance |

---

## SEQUENCIAMENTO E DEPENDÊNCIAS (visão macro)

```
F00 Contratos/Bugs ─┬─> F01 Fundação ──> F02 Multi-Tenancy (RLS) ──┐
                    │                                              ├─> F12 Homologação ─> F13 Migração Dados ─> F14 Go-Live
F03..F11 (módulos) ─┴── dependem de F00+F02 ──────────────────────┘
F15 Performance/Observabilidade: transversal, contínua
```

---

# FASE 00 — Correção Estrutural, Contratos e Bugs Bloqueadores

**Objetivo:** zerar débitos que comprometem qualquer fase seguinte e alinhar o contrato SPA×ERP.

**Problemas que resolve / Achados 〔auditoria〕:**
- `strftime()` (SQLite) em `RelatorioService::clientesAniversariantes` quebra em Postgres/MySQL 〔§5/§9/§12〕.
- Slugs divergentes `LookupController::MAPA` × `CadastroApoioRegistry::TIPOS` (`tipo-pessoa` vs `tipos-pessoa`) 〔§6 item 5〕.
- `StubController` órfão 〔§2〕 — remover ou documentar.
- `ClienteSubrecursoController::historico` retorna `[]` 〔§3 Cliente〕.
- Métodos implementados sem rota: `FinanceiroService::agrupar/desagrupar/reparcelar`, `CaixaService::baixarTitulos/lancarEmCaixaFechado`, `NfEntradaService` 〔§3〕.
- IDOR teórico: `CaixaService::baixarParcela` não revalida empresa em tabela-filha 〔§5/§8 item 1〕.

**Dependências:** nenhuma (primeira fase).
**Banco:** nenhuma migration nova; só validar que funções de data são agnósticas (usar `DB::raw` com função do driver-alvo ou Carbon no PHP).
**Backend:** corrigir `strftime`; padronizar slugs num único enum compartilhado; revalidar `empresa_id` em operações por id de tabela-filha (where adicional `empresa_id = tenant()`); remover `StubController`.
**Frontend:** ajustar chamadas que dependiam de slugs/`historico`.
**APIs:** congelar contrato; `Feature/ContratoSpaTest` + `RbacContratoTest` como gate de CI.
**Integrações:** —
**Segurança:** fecha IDOR; revalidação de tenant.
**Multi-Tenancy:** preparação (revalidação por id).
**Testes:** estender ContratoSpaTest p/ cada slug; teste de regressão do relatório aniversariantes em Postgres.
**Critérios de aceite:** suíte verde em Postgres/MySQL; 0 rotas órfãs; 0 slugs divergentes; grep de `strftime`=0.
**Riscos:** baixo. **Complexidade:** Baixa. **Estimativa:** 1 sprint.

---

# FASE 01 — Fundação e Configurações Globais

**Objetivo:** consolidar config global e o hub de Configurações antes de abrir módulos.
**Achados 〔auditoria〕:** Empresa/Config/Certificado ✅ porém telas de config dispersas 〔§6/§7〕; `Configuracoesgerais` (responsável técnico, SMTP, SAT) do legado.
**Dependências:** F00.
**Banco:** revisar tabela de config global no novo (equivalente a `configuracoesgerais`: RT/CSRT, SMTP, chaves).
**Backend:** endpoint de config global; consolidar `EmpresaConfigController` (testar-email, nfce-token já existem).
**Frontend:** **unificar** `ClienteConfigPage`+`FinanceiroConfigPage`+`ColaboradorConfigPage`+`ProdutoConfigPage` em **hub de Configurações** com seções (decisão: Unificar 〔§7〕).
**APIs:** `/config-global` GET/PUT.
**Segurança:** RBAC `config.*`; senha mestra (já em EmpresaConfig).
**Multi-Tenancy:** config global é por-grupo/por-empresa — marcar escopo.
**Testes:** Feature de config global + RBAC.
**Critérios de aceite:** 1 hub de Config; config global persistida e lida pelos módulos fiscais/email.
**Riscos:** baixo. **Complexidade:** Baixa-Média. **Estimativa:** 1 sprint.

---

# FASE 02 — Multi-Tenancy completo + RLS (BLOQUEADOR DE PRODUÇÃO)

> **Momento correto (resposta exigida):** **DURANTE a migração, agora — antes de Go-Live e
> antes de migrar dados.** Justificativa técnica 〔§8〕: a fundação já existe (`TenantContext`,
> `BelongsToTenant`, `ResolveTenant`) mas a cobertura é **52% (56/107 models)**, sem RLS, com
> jobs sem tenant e IDOR teórico em tabelas-filhas. Fechar isso **depois** da migração de dados
> significaria reprocessar dados já gravados sem escopo e auditar vazamentos retroativamente —
> muito mais caro e arriscado. Fazer **antes** dos módulos restantes garante que todo código
> novo nasça escopado.

**Objetivo:** isolamento de tenant seguro e auditável (defense-in-depth).
**Achados 〔auditoria〕:** 56/107 com trait; tabelas-filhas dependem do pai; sem RLS; jobs sem tenant; uploads/cache/filas sem prefixo 〔§8〕.

### Plano específico de Multi-Tenancy
- **Banco:** auditar os 107 models → classificar (escopado por empresa / por grupo / global como estados-cidades). Adicionar `empresa_id` (e/ou `grupo_id`) nas migrations faltantes 〔§8 item 1〕. **Habilitar RLS no PostgreSQL** por `empresa_id` em todas as tabelas escopadas (policy `USING (empresa_id = current_setting('app.empresa_id')::int)`), setando a variável de sessão no `ResolveTenant`/conexão.
- **Backend:** aplicar `BelongsToTenant`/`BelongsToGrupo` a 100% dos models escopáveis; em tabelas-filhas, revalidar `empresa_id` em toda busca por id (`baixarParcela`, `findOrFail` de parcela/movimento/item/nota) 〔§5/§8 item 1〕.
- **Frontend:** `EmpresaSwitcher` já existe; garantir invalidar cache do TanStack Query ao trocar `X-Empresa-Id`.
- **Permissões:** RBAC por empresa já em `temPermissao` 〔§1〕 — adicionar teste de cross-tenant.
- **Auditoria:** registrar `empresa_id` em todo log (entra na F11).
- **Cache:** prefixar chaves por `empresa_id`/`grupo_id`.
- **Filas/Jobs:** os 6 crons 〔§8 item 3〕 devem **iterar tenants explicitamente** (loop por empresa) ou rodar com `withoutTenant()` consciente; `GeocodificarClienteJob`/`NotificarEstoqueBaixoJob` já corretos.
- **Uploads:** certificado A1 e anexos com path segregado por `empresa_id` 〔§8 item 4〕.
- **Relatórios:** garantir filtro de tenant em todos (entra na F10).
- **Segurança:** RLS = 2ª barreira; pentest de IDOR entre empresas do mesmo deployment.
- **Testes:** suíte cross-tenant (usuário A não enxerga dado de B) por módulo; teste de RLS (query crua sem scope ainda filtra).

**Critérios de aceite:** 100% dos models escopáveis com trait; RLS ativo em todas as tabelas escopadas; 0 vazamento na suíte cross-tenant; jobs comprovadamente por-tenant.
**Riscos:** **Alto** (toca tudo). **Complexidade:** Alta. **Estimativa:** 2–3 sprints.

---

# FASE 03 — Vendas / Pedido (fechar gaps)

**Objetivo:** completar o fluxo de venda ponta-a-ponta.
**Achados 〔auditoria〕:** núcleo ✅ (state machine); **NFC-e a partir do pedido depende de SEFAZ real (Fake)**; gás-do-povo via PagamentoService 〔§3 Pedido〕.
**Dependências:** F02; SEFAZ real (F09).
**Banco:** —. **Backend:** garantir gatilho de emissão fiscal pós-conclusão (acopla a F09). **Frontend:** PedidosPage — ação "emitir NFC-e" condicionada ao gate fiscal. **APIs:** `/pedidos/{id}/emitir-nfce`. **Integrações:** SEFAZ (F09). **Segurança/Tenant:** escopo já. **Testes:** PedidoServiceTest (existe) + emissão com driver Fake e real. **Aceite:** venda → conclusão → financeiro+estoque+NFC-e. **Risco:** médio (depende fiscal). **Complexidade:** Média. **Estimativa:** 1 sprint (após F09).

---

# FASE 04 — Cadastros Mestres + Hub de Cadastros de Apoio

**Objetivo:** centralizar a cauda de CRUDs e fechar Cliente.
**Achados 〔auditoria〕:** Cliente CRUD+abas ✅ mas `historico` `[]` 〔§3〕; ~20 CRUDs de apoio dispersos 〔§6/§7〕; geográfico ✅.
**Decisão:** **Centralizar** apoio; **completar** histórico do cliente (vem de Pedidos).
**Banco:** —. **Backend:** `ClienteSubrecursoController::historico` → consultar Pedidos do cliente. **Frontend:** **Hub "Cadastros de Apoio"** tabbed agregando Cargo/Estadocivil/Parentesco/Telefonetipo/Documentotipo/Unidademedida/Tipocombustivel/Tipoexame/situações/tipos (decisão: Centralizar). **APIs:** `/cadastros/{tipo}` (já existe). **Tenant:** escopo. **Testes:** CadastroApoioTest (existe) ampliado. **Aceite:** histórico real; ~15 telas a menos no menu. **Risco:** baixo. **Complexidade:** Baixa-Média. **Estimativa:** 1 sprint.

---

# FASE 05 — Produtos e Estoque (consolidar)

**Objetivo:** confirmar paridade total (já ✅) e cobrir bordas.
**Achados 〔auditoria〕:** Produto ✅, Estoque ✅ (todos os 9 fluxos cobertos por `EstoqueService`) 〔§3〕.
**Banco/Backend/Frontend:** sem gap funcional; só endurecer testes de invariância de saldo (existe `BalanceInvariantTest`). **Tenant:** escopo. **Aceite:** suíte de estoque verde sob multi-tenant. **Risco:** baixo. **Complexidade:** Baixa. **Estimativa:** 0,5 sprint.

---

# FASE 06 — Compras / NF de Entrada

**Objetivo:** expor capacidade pronta e fechar entrada de mercadoria.
**Achados 〔auditoria〕:** `NfEntradaService` **completo mas sem controller/rota** 〔§3 NF de entrada 🔴〕.
**Decisão:** **Expor** (não reescrever).
**Banco:** confirmar tabelas `nf_recebidas`/`nf_recebida_itens` (existem). **Backend:** criar `NfEntradaController` (importar XML, processar → entrada estoque + CP). **Frontend:** nova tela "NF de Entrada" (upload XML + conferência). **APIs:** `/fiscal/nf-entrada` POST/import. **Integrações:** parser NFePHP (já no service). **Tenant:** escopo. **Testes:** `NfEntradaTest` (existe) + Feature do controller. **Aceite:** XML → estoque+financeiro a pagar. **Risco:** baixo. **Complexidade:** Baixa-Média. **Estimativa:** 1 sprint.

---

# FASE 07 — Financeiro, Caixa e Cheque (expor métodos órfãos)

**Objetivo:** completar superfície HTTP do financeiro.
**Achados 〔auditoria〕:** `FinanceiroService::agrupar/desagrupar/reparcelar` e `CaixaService::baixarTitulos/lancarEmCaixaFechado` **sem rota** 〔§3 Caixa/Financeiro〕; Cheque 🟡; conciliação OFX ✅.
**Decisão:** **Expor** (lógica pronta).
**Banco:** —. **Backend:** rotas para agrupar/desagrupar/reparcelar; baixar títulos em lote; lançar em caixa fechado (com RBAC `lancarfechado`). **Frontend:** ações na FinanceiroPage/ExtraTabs (agrupar/reparcelar; baixa em lote; cheque depósito/devolução/troco). **APIs:** `/financeiro/lancamentos/{id}/agrupar|desagrupar|reparcelar`, `/caixa/{conta}/baixar-titulos`. **Tenant:** revalidação por id (F02). **Testes:** CaixaServiceTest/FinanceiroServiceTest/ChequeServiceTest (existem) + Feature das novas rotas. **Aceite:** paridade de caixa/financeiro/cheque com o legado. **Risco:** médio (dinheiro). **Complexidade:** Média. **Estimativa:** 1–2 sprints.

---

# FASE 08 — Cobrança Bancária: Boleto + CNAB + Conciliação Contábil

**Objetivo:** habilitar cobrança real (bloqueador de produção).
**Achados 〔auditoria〕:** `BoletoService` lógica ✅ mas **driver Fake fixo, sem CNAB real** 〔§3 Boleto ⚠️〕; remessa/retorno CNAB Caixa/Itaú do legado; conciliação contábil **CONSISA não migrada** 〔§3 🔴〕.
**Decisão:** **Migrar** (driver real boleto/CNAB; integração CONSISA).
**Banco:** confirmar `boletos`/`boleto_ocorrencias`/`remessas_cnab` (existem). **Backend:** implementar `BoletoDriver` real (porta `eduardokum/laravel-boleto`, Caixa 104 / Itaú 341 como no legado); geração de remessa `.rem`, processamento de retorno, ocorrências; `ConciliacaoContabilService` (API CONSISA). **Frontend:** telas de boleto/remessa/retorno; conciliação contábil. **APIs:** `/cobranca/boletos`, `/cobranca/remessas`, `/conciliacao-contabil`. **Integrações:** banco (CNAB), CONSISA. **Segurança:** credenciais por empresa (encrypt). **Tenant:** escopo + path segregado de arquivos. **Testes:** geração/parse CNAB com fixtures; CobrancaServiceTest (existe). **Aceite:** boleto real emitido, remessa gerada, retorno baixa parcela; conciliação contábil bate. **Risco:** **Alto**. **Complexidade:** Alta. **Estimativa:** 2–3 sprints.

---

# FASE 09 — Fiscal: SEFAZ real + SPED completo + Cupom SAT + IBPT

**Objetivo:** emissão fiscal real ponta-a-ponta (bloqueador de produção).
**Achados 〔auditoria〕:** `NFePHPSefazDriver` existe mas **default Fake** (ativa só com `FISCAL_DRIVER=nfephp`) 〔§3 NF-e ⚠️〕; SPED gera arquivo mas cobertura de registros **provavelmente menor que os ~120 do legado** 〔§3 SPED 🟡〕; Cupom SAT transmissão é gate (legado usa WebSocket local) 〔§3 Cupom〕; IBPT 🟡.
**Decisão:** **Migrar** SEFAZ real; **completar** SPED; **substituir** abordagem SAT-WebSocket por gate explícito.
**Banco:** `config_fiscais`/`operacoes_fiscais`/`notas_fiscais`/`nota_itens` (existem). **Backend:** ativar/homologar `NFePHPSefazDriver` (transmitir/consultar/cancelar/inutilizar/CCE) com **certificado A1 por empresa** (path segregado, F02); completar registros SPED Fiscal/Contribuições faltantes vs legado; atualização IBPT automática (job). **Frontend:** FiscalPage — transmitir/cancelar/CCE/inutilizar; SPED export. **APIs:** já existem `/fiscal/nfe/{id}/transmitir|cancelar`, `/fiscal/sped`. **Integrações:** SEFAZ (NFePHP), SAT (driver/gate), IBPT. **Segurança:** certificado por tenant. **Tenant:** escopo. **Testes:** emissão com Fake (CI) e homologação SEFAZ; SpedFiscalTest/SpedContribuicoesTest (existem) ampliados. **Aceite:** NF-e/NFC-e autorizada em homologação; SPED validado no PVA; SAT transmitido. **Risco:** **Alto**. **Complexidade:** Alta. **Estimativa:** 3 sprints.

---

# FASE 10 — Relatórios (central completa)

**Objetivo:** cobrir os 15 relatórios faltantes substituindo a abordagem 1-tela-por-relatório.
**Achados 〔auditoria〕:** **11 de 26**; faltam venda PDV, vendas por entregador/convênio detalhado, NF emitidas/recebidas, promoções, promotor, questionários, **logs/auditoria de senha**, veículos, follow-up XLS 〔§3 Relatórios 🔴/🟡〕; divergência comissão (RelatorioService média vs ComissaoService fino) 〔§5〕.
**Decisão:** **Substituir** por central de relatórios; **unificar** cálculo de comissão.
**Banco:** —. **Backend:** novos relatórios em `RelatorioService`; unificar comissão usando `ComissaoService` 〔§5〕; export CSV/PDF/XLS. **Frontend:** expandir `RelatoriosPage` (seletor + filtros + export) — sem criar 15 telas. **APIs:** `/relatorios/{slug}`. **Tenant:** filtro obrigatório por empresa 〔§8〕. **Testes:** RelatorioTest (existe) por slug. **Aceite:** 26/26 relatórios disponíveis na central; comissão idêntica nos dois caminhos. **Risco:** médio. **Complexidade:** Média-Alta. **Estimativa:** 2–3 sprints.

---

# FASE 11 — Auditoria/Logs + Inconsistências de Cadastro

**Objetivo:** compliance e qualidade de dados.
**Achados 〔auditoria〕:** `revisions`/`Logsenha`/`Logcerca`/ReportLogs **não migrados** 〔§3 Auditoria 🔴〕; inconsistências rua/bairro usam `UTL_MATCH`/`MINUS` Oracle-only 〔§12〕.
**Decisão:** **Migrar modernizado** (audit trail unificado); **substituir** similaridade por `pg_trgm`/`levenshtein`.
**Banco:** tabela de auditoria unificada (model, ação, user, empresa_id, antes/depois, ip). **Backend:** trait de auditoria nos models sensíveis; log de senha-mestra (já há motivo por rota no legado); detecção de duplicidade rua/bairro via similaridade Postgres. **Frontend:** tela de auditoria (filtros) na central de relatórios. **APIs:** `/relatorios/auditoria`, `/cadastros/inconsistencias`. **Tenant:** `empresa_id` no log 〔F02〕. **Testes:** Feature de auditoria. **Aceite:** trilha de auditoria por entidade; detecção de duplicados funcional em Postgres. **Risco:** médio. **Complexidade:** Média. **Estimativa:** 1–2 sprints.

---

# FASE 12 — Frota, Convênio, Monitora, CRM, Pagamentos, Mobile (fechar gates e gaps)

**Objetivo:** completar módulos parciais e ativar drivers reais restantes.
**Achados 〔auditoria〕:**
- Frota: **entrada-saída e documento de veículo não migrados** 〔§3 Frota 🟡〕 → **Migrar**.
- Convênio: NF+boleto do fechamento dependem de gates 〔§3 🟡〕 → resolvido por F08/F09.
- Monitora: **FakeSgcasaDriver** 〔§3 ⚠️〕 → **Migrar** driver SGCasa real.
- CRM: **mala direta ausente** 〔§3 🟡〕 → **Migrar simplificado** (segmentação + export/integração e-mail).
- Pagamentos: **FakePagamentoDriver (eRede)** 〔§3 ⚠️〕 → **Migrar** driver eRede real.
- Mobile: **pagamento online e push (FCM) são gates** 〔§3 ⚠️〕 → **Migrar** drivers reais.
**Dependências:** F02; F08/F09 para convênio.
**Banco:** `veiculo_*` (entrada-saída/documento) se faltarem. **Backend:** `BoletoDriver`(F08)/`SgcasaDriver`/`PagamentoDriver`/`PushService` reais; entrada-saída de veículo; mala direta. **Frontend:** telas faltantes. **APIs:** respectivas. **Integrações:** SGCasa, eRede, FCM. **Tenant:** escopo. **Testes:** SateliteServiceTest/MonitoraServiceTest/VeiculoServiceTest/PagamentoTest/MobileTest (existem) com drivers reais mockados. **Aceite:** todos os módulos 🟡/⚠️ viram ✅. **Risco:** médio-alto (integrações). **Complexidade:** Alta. **Estimativa:** 3 sprints.

---

# FASE 13 — Performance, Observabilidade e Hardening (transversal)

**Objetivo:** preparar para carga real.
**Achados 〔auditoria〕:** queries pesadas no legado (relatórios com 20k linhas, generate_series); jobs por-tenant 〔§8〕.
**Banco:** índices por `empresa_id`+colunas de filtro; revisar N+1. **Backend:** cache por-tenant 〔F02〕; rate-limit; logs estruturados. **Frontend:** code-splitting por rota (já lazy-friendly). **Tenant:** validar RLS sob carga. **Testes:** carga nos relatórios e listagens. **Aceite:** SLA de listagens/relatórios; sem N+1 crítico. **Risco:** médio. **Complexidade:** Média. **Estimativa:** 1–2 sprints.

---

# FASE 14 — Homologação (paridade assistida com o legado)

**Objetivo:** validar com usuários reais, lado a lado com CTRL-WEB.
**Achados 〔auditoria〕:** sistema "homologável parcial" hoje 〔§10 q.11〕; após F00–F13, homologável pleno.
**Atividades:** roteiros por módulo (venda→fiscal→financeiro→cobrança→relatórios); comparação de saídas (NF, boleto, SPED, DRE) com o legado; UAT do hub de cadastros/config/relatórios. **Critérios de aceite:** todos os módulos 🔴/🟡/⚠️ da auditoria fechados; nenhum item da auditoria sem tratamento (checklist abaixo). **Risco:** médio. **Estimativa:** 2 sprints.

---

# FASE 15 — Migração de Dados (Oracle/legado → MySQL/Postgres)

**Objetivo:** trazer as ~105 tabelas restantes com integridade.
**Achados 〔auditoria〕:** 108 de 213 tabelas no novo; ETL já tem 15 migrators + invariantes (`BalanceInvariantTest`) 〔§0/§9〕.
**Decisão:** **Migrar** com invariância (modelo existente).
**Banco:** mapear tabelas faltantes; migrators ETL por entidade com `withoutTenant()` + set de `empresa_id`/`grupo_id` corretos. **Backend:** `etl:run` e `cutover:check` (existem) ampliados. **Tenant:** dados gravados já escopados (depende de F02 estar pronto — por isso F02 vem antes). **Testes:** invariantes Count/Integrity/Sum/Balance por entidade. **Aceite:** `cutover:check` verde; somas batem com o legado. **Risco:** **Alto**. **Complexidade:** Alta. **Estimativa:** 2–3 sprints.

---

# FASE 16 — Go-Live

**Objetivo:** virada definitiva.
**Atividades:** janela de corte; congelamento do legado; migração final delta; smoke test fiscal/cobrança em produção (1 NF, 1 boleto, 1 PIX reais); rollback plan. **Critérios de aceite:** operação real (fatura/cobra/rastreia) em produção multi-tenant; legado desativado. **Risco:** alto. **Estimativa:** 1 sprint + acompanhamento.

---

## RASTREABILIDADE — nenhum achado da auditoria sem tratamento

| Achado da auditoria | Fase |
|---|---|
| `strftime` SQLite; slugs divergentes; StubController; IDOR caixa; métodos sem rota | F00 |
| Telas de config dispersas; config global | F01 |
| Multi-tenant 52%, sem RLS, jobs sem tenant, uploads sem segregação | **F02** |
| NFC-e do pedido (gate SEFAZ) | F03 (+F09) |
| Cliente `historico` []; ~20 CRUDs de apoio dispersos | F04 |
| Produto/Estoque (bordas) | F05 |
| NF de entrada sem rota | F06 |
| Caixa/Financeiro/Cheque métodos órfãos | F07 |
| Boleto Fake + CNAB ausente; conciliação contábil CONSISA não migrada | **F08** |
| SEFAZ Fake; SPED incompleto; SAT gate; IBPT | **F09** |
| 15 relatórios faltantes; comissão divergente | F10 |
| Auditoria/logs (revisions/Logsenha/Logcerca); inconsistências Oracle-only | F11 |
| Frota entrada-saída/documento; Monitora/eRede/FCM Fake; mala direta | F12 |
| Performance/observabilidade | F13 |
| Homologação | F14 |
| ~105 tabelas a migrar | F15 |
| Go-Live | F16 |

**Bloqueadores de produção (caminho crítico):** F00 → F02 → F08 → F09 → F15 → F16.
Os demais correm em paralelo conforme capacidade da equipe.

**Estimativa macro:** ~28–36 sprints, com F02/F08/F09/F15 como os maiores riscos.
