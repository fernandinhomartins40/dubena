# PLANO DE MODERNIZAÇÃO UX/UI — ctrl-web

> ⚠️ **SUPERSEDIDO NA CAMADA DE FRONTEND (2026-06-18):** a UI nova deixou de ser
> **Filament** e passou a ser um **SPA React/Vite + Laravel API (Sanctum)** — ver
> `PLANO_SPA_REACT.md` (vigente). A FUNDAÇÃO deste plano (M1: RBAC/spatie, fix unique-PG,
> cast moeda, navegação) **continua válida e reusada** pelo novo frontend; apenas a
> escolha de UI (Filament) foi trocada por React. As fases M2+ aqui descritas (telas em
> Filament) são substituídas pelas fases S2+ do plano SPA. Mantido como referência.

> **Substitui** `PLANO_IMPLEMENTACAO_MODERNIZACAO.md` de F4 em diante (este passa a
> referência histórica). Baseado na auditoria de código real em `PRD/MODERN_*.md`.
>
> **Stack:** Laravel 12 + PHP 8.3 + **Filament 3** (já em produção desde a F3).
> **Norte (cliente):** abandonar a "cara de dinossauro" — **sem menu-no-banco**, navegação
> **declarativa**, **página completa por entidade** (abas/RelationManagers), layout
> **sidebar + header + conteúdo**, permissão por **papel/recurso** (RBAC), não menuusers.
> Princípios em [`PRD/MODERN_00_VISAO_UX.md`](PRD/MODERN_00_VISAO_UX.md).
>
> **Regra de ouro:** Strangler — coexistir com o legado atrás do mesmo login, migrar
> módulo a módulo com feature flag e rollback, **sem quebrar produção**. Cada entidade
> migrada DELETA a tela legada correspondente só após validada.

---

## ESTADO ATUAL (concluído — base do plano)

- **F0 Estabilização** ✅ em prod — bugs Oracle→PG/typos/debug fechados (verificado nos MODERN).
- **F1 Rede de testes** ✅ — caracterização dos motores (estoque/financeiro/custo).
- **F2 Upgrade de stack** ✅ em prod — Laravel 5.8→12, PHP 7.4→8.3, libs EOL trocadas.
- **F3 Fundação Filament** ✅ em prod — painel `/admin`, guard único, branding Dubena,
  piloto Cidades/Bairros (nav declarativa nativa do Filament já funciona).
- **F4A (parcial)** ✅ em prod — kill-switch do bypass AJAX, UserResource (IDOR corrigido),
  gestão de permissões (RelationManager menuusers), limpeza do Menu.
- **Pendências abertas (herdadas):** fix `unique`-PG do cadastro de cliente (branch não
  mesclada); flag de bypass ligada em staging; `unique`-PG em EmpresaBens/Nf; resíduos de
  SQLi de filtro (FinanceiroController) e whereRaw interpolado.

---

## FASE M1 — FUNDAÇÃO DA UX MODERNA (encerrar D11) · risco BAIXO

> Antes de migrar telas, fechar a base: identidade visual, navegação declarativa e RBAC.

- [ ] **M1.1 Quitar pendências abertas:** mesclar fix `unique`-PG (Cliente) + aplicar o
      mesmo fix em EmpresaBens/Nf; decidir destino da flag de bypass (ligar/observar/reverter).
- [ ] **M1.2 RBAC com papéis** (spatie/laravel-permission): instalar; definir papéis iniciais
      (Admin, Gerente, Caixa, Vendedor, Estoquista, Fiscal) e permissões nomeadas por
      recurso/ação; **mapear menuusers → permissões** (script de migração idempotente);
      Filament Shield para gerir papéis na UI. Conviver com `podeNoMenu` durante a transição.
- [ ] **M1.3 Navegação declarativa:** consolidar os `navigationGroup` dos recursos conforme
      o sidebar-alvo (MODERN_00 §3). Nenhuma dependência de `menus`/`menuusers` na UI nova.
- [ ] **M1.4 Layout/tema:** sidebar + header + conteúdo, responsivo, dark mode, branding;
      componentes compartilhados (seletor de empresa, máscara de moeda/decimais, upload logo).

**Portão M1:** painel com sidebar declarativo + papéis aplicáveis a usuários; D11 fechado
(sem menu-no-banco na UI nova); pendências de gravação (unique-PG) resolvidas.

---

## FASE M2 — CADASTROS (vitrine do padrão "página completa") · risco BAIXO

> Ordem: do mais simples/independente ao central. Cada um = 1 Resource completo.

- [ ] **M2.1 Geográfico** — Cidade ✅(F3), Bairro ✅(F3), **Rua**, **Região**; selects
      dependentes nativos. Liga flag, deleta telas legadas.
- [ ] **M2.2 Bancos / Layout de banco** — Resources simples.
- [ ] **M2.3 Cliente (página completa)** — 1º grande alvo (exemplo do cliente): ficha em abas
      (Dados/Endereço/Fiscal/Convênio) + RelationManagers (Telefones, Contatos/CRM, Pedidos,
      Financeiro). Lista que aparece ao abrir. Substitui o God ClienteController.
- [ ] **M2.4 Produto** — abas (Dados/Fiscal/Estoque/Preço); classes/unidades como apoio.
- [ ] **M2.5 Empresa/Empresaconfig** — abas (Dados/Fiscal/Estoque/Boleto/NFC-e); upload logo.
- [ ] **M2.6 Cadastros de apoio** — condição de pagamento, segmento, tipo de pessoa, telefone,
      estado civil, parentesco, etc. (Resources enxutos).

**Portão M2 (por entidade):** Resource validado dev→prod, flag ligada, tela antiga deletada,
permissão via papel, regra de negócio preservada (teste).

---

## FASE M3 — MOTORES (Estoque + Financeiro) · risco MÉDIO

> Motores já caracterizados (F1). Extrair Services e dar UI moderna por cima.

- [ ] **M3.1 Estoque** — EstoqueProcessor → Domain Service; telas de saldo/requisição/
      transferência/acerto/inventário/fechamento com saldo em tempo real e preview.
- [ ] **M3.2 Financeiro/Tesouraria** — Processors → Services; lançamentos (corrigir SQLi de
      filtro `FinanceiroController:355-365` + `case 4;`), Caixa com preview de saldo, baixa
      com rateio visual; Conta/Plano/Centro (árvore) como Resources.

**Portão M3:** motores com baseline de teste verde; telas novas; SQLi de filtro fechado.

---

## FASE M4 — FISCAL (NF-e / NFC-e / SAT / SPED) · risco ALTO

> Código mais maduro (REFATORAR). Oráculo de validação = SEFAZ homologação / PVA.

- [ ] **M4.0 (BLOQUEANTE) Validar emissão fiscal em homologação SEFAZ** (Carbon 3 + PHP 8.3).
- [ ] **M4.1 Malha fiscal unificada** — agrupar grupo fiscal + impostos (ICMS/IPI/PIS/COFINS/
      CST) num fluxo coeso (abas/wizard) em vez de ~10 telas isoladas.
- [ ] **M4.2 NF-e/NFC-e** — recurso com status visível (rascunho/transmitida/autorizada/
      cancelada); aplicar fix `unique`-PG do NfRequest; refatorar motor em Service.
- [ ] **M4.3 SPED** — geração com preview de blocos/contagem + validações; histórico.

**Portão M4:** emissão validada na SEFAZ homologação; malha fiscal coesa; SPED gerável.

---

## FASE M5 — VENDAS / PEDIDOS (núcleo) · risco MÁXIMO — POR ÚLTIMO

> O ativo mais caro. Só com cadastros + motores + fiscal sólidos e baseline de teste.

- [ ] **M5.1 PedidoController → Domain/Actions** por transição (CriarPedido/ConfirmarPedido/
      CancelarPedido/TrocarSetor) com `DB::transaction` atômica e eventos de domínio; cobrir
      com testes. Preservar contratos do app mobile (D13).
- [ ] **M5.2 UX da venda** — jornada em etapas (cliente → itens c/ preço/estoque em tempo real
      → pagamento → confirmação); Pedidos como painel/Kanban de status.
- [ ] **M5.3 CaixaController → Service** de tesouraria testável (já parte em M3.2).
- [ ] **M5.4 Atualizarprecos** — reescrever como ação com preview + bindings; authorize;
      corrigir `$this->error`.
- [ ] **M5.5 Vendaativa / Promotor / Vendasmensais / Promoções** — query services
      parametrizados (sem whereRaw/$_GET); manter regras (giro, meta×realizado, visita).

**Portão M5:** pedido/caixa refatorados com testes; venda nova validada; legado de venda
aposentado por flag.

---

## FASE M6 — SATÉLITES (em paralelo, conforme capacidade) · risco BAIXO

- [ ] RH/Colaboradores (ficha + RelationManagers: família, exames, comissões).
- [ ] Frota (ficha por veículo + RelationManagers: abastecimento/óleo/pneu/documento).
- [ ] Vale-Gás/Convênio (ciclo de status + charts via query service).
- [ ] Relatórios (área dedicada + dashboards com widgets Filament).
- [ ] Monitoramento GPS (painel/mapa; alinhar permissões ao RBAC).
- [ ] Integrações/Notificações (encapsular em Services; descartar obsoleto).

---

## FASE M7 — LIMPEZA & HARDENING · risco BAIXO

- [ ] Remover legado AdminLTE/`menus`/`menuusers`/`Menu::menus()`/AuthorizeCustom quando o
      último módulo migrar; deletar scaffolds vazios (Clientecontato, Colaboradorfamilia,
      Veiculodocumento, MenuController, exceção morta do Authenticate).
- [ ] Varredura final de SQLi/whereRaw interpolado e `unique`-PG em todos os FormRequests.
- [ ] Auditoria de acessos; política de senha/2FA; cobertura de testes; OpenAPI da API.

**Portão M7:** legado removido; UI 100% nova; segurança e testes consolidados.

---

## FASE M8 — MULTI-TENANT (adiada por decisão) · só depois de tudo

Conforme decisão registrada: tenant_id + RLS, após M1–M7. Fora do escopo atual.

---

## Regras de execução (todas as fases)
1. Branch por incremento; CI verde; PR; deploy; **verificação na VPS (paramiko)**.
2. Feature flag por módulo (rota nova × tela antiga) — coexistência + rollback.
3. Não regredir regra de negócio (testes de caracterização como baseline).
4. Código via GitHub Actions; `.env`/ambiente na VPS só quando autorizado (backup antes).
5. Cada entidade migrada: validar dev→prod → ligar flag → DELETAR tela legada.

## Diagrama de dependências
```
M1 Fundação(RBAC+nav) ─► M2 Cadastros ─► M3 Motores ─► M4 Fiscal ─► M5 Vendas/Pedido
                                   └► M6 Satélites (paralelo) ─► M7 Limpeza ─► M8 Multi-tenant
```
