# Plano de Conclusão da Reescrita — erp-novo

> **Por que este plano existe.** O `PLANO_REESCRITA_BACKEND.md` (N0–N12) estava
> **subespecificado**: cada fase descrevia o módulo em uma linha ("N9 — porta NF-e/SPED",
> "N6 — reescreve caixa"), e a implementação real entregou **apenas o núcleo testável** de
> cada fase. A auditoria forense (`AUDITORIA_FORENSE_MIGRACAO.md`) mediu o delta: o erp-novo
> tem **11.673 LOC** vs **139.048 LOC** do legado (8,4%), **0 processors** vs 144,
> **fiscal fake**, **48% dos endpoints da SPA quebrados** e **RBAC inoperante**.
>
> Este documento é o plano **completo e verificável** para fechar esse delta. Cada fase
> parte do **que já existe (medido no código)** e especifica **o que falta** com um
> **DoD checável por `grep`/teste**, não por nome de arquivo.

> **Regra de ouro do plano:** uma fase só está "pronta" quando o **fluxo completo** existe
> (controller → service → banco → efeitos colaterais), tem **teste**, **ETL com invariante**,
> e o **endpoint que a SPA chama responde** (sem 404, sem contrato divergente).

---

## 0. Estado real medido (linha de base deste plano)

**Já existe e está sólido (núcleo):**
- 63 Models, 22 Services de domínio, 16 ETL Migrators, 23 migrations (69 tabelas), 32 testes.
- Fluxo fechado: Cliente, Produto, Estoque (saldo auditável), Pedido (máquina de estados),
  Financeiro (parcelas/rateio/agrupamento), Caixa (saldo auditável), Convênio/Vale-gás/Comodato,
  Monitora/GPS, Mobile (base).

**Lacunas estruturais (medidas):**
| Lacuna | Evidência |
|---|---|
| 36/75 endpoints da SPA não existem | cruzamento `features/*/api.ts` × `routes/api.php` |
| `/me` não retorna roles/permissions → RBAC só por `support` | `AuthController:53`; `auth.tsx:48` |
| Fiscal não emite (driver fake, cálculo `base*aliq`) | `FakeSefazDriver.php`; `CalculoImpostoService.php:40` |
| 0 jobs de fila; 2/10 cron | `app/Jobs` vazio; `routes/console.php` |
| ~12 domínios sem tabela | RH, Frota, SPED, NF recebida, Conciliação, Pós-venda, Checklist, etc. |
| Pedido ignora condição de pagamento, cartão, Gás do Povo | `FinanceiroService::gerarDoPedido:78` |
| Caixa não trava movimento em caixa fechado | `CaixaService::movimentar:35` |

---

## 1. Sequenciamento (por risco e dependência)

```
C1 Contrato SPA↔backend + RBAC real     (DESTRAVA TUDO — pré-requisito)
  ├─ C2 Empresa/Config + uploads (cert A1, NFC-e token, e-mail)
  ├─ C3 Seeds + ETL contra dump real     (sem isto nada é homologável)
  ├─ C4 Pedido/Financeiro/Caixa — fechar regras divergentes (risco contábil)
  ├─ C5 RH / Colaborador / Comissão       (módulo novo)
  ├─ C6 Frota / Veículos                  (módulo novo)
  ├─ C7 FISCAL REAL (porte 1:1)           (caminho crítico — obrigação legal)
  │     ├─ C7a Cálculo de imposto completo (ST/DIFAL/FCP/IBPT/IBS-CBS)
  │     ├─ C7b XML + SEFAZ + certificado + DANFE
  │     ├─ C7c NF recebida (entrada)
  │     └─ C7d SPED Fiscal/Contribuições
  ├─ C8 Conciliação/OFX + Relatórios + Export
  ├─ C9 Jobs de fila + cron completos
  ├─ C10 Pós-venda/CRM + Promoção/Sorteio/Metas + Checklist
  ├─ C11 Cupom fiscal (SAT/CFe) + Inventário + Documentos/Bens
  └─ C12 Cauda + cadastros de apoio restantes
C13 CUTOVER (dump real → ETL → invariantes 100% verdes)
```

**Caminho crítico:** C1 → C4 → C7. São contrato, dinheiro e obrigação legal.

---

## FASE C1 — Contrato SPA × backend + RBAC real  ·  CRÍTICO · ~2–3 sem

**Por quê:** 48% dos endpoints quebram; sem isto as telas do próprio núcleo não abrem.

### O que falta (medido)
Endpoints chamados pela SPA sem rota no backend:
`fiscal/nfe*`, `fiscal/operacoes`, `fiscal/malha/:t`, `fiscal/sped`, `colaboradores*`,
`veiculos*`, `satelites/*`, `grupos*`, `empresas/:id/certificado|nfce-token|testar-email`,
`financeiro/dre`, `financeiro/conciliacao`, `produto-config/*`, `produtos-precos/*`,
`produtos/:id/estoque`, `estoque/requisicoes|fisico/:id/efetivar|fechamentos/abrir`,
`cheques/recebidos/:id`.

### Entregas
1. **Matriz contrato** (script que cruza `features/*/api.ts` × `routes/api.php` — versionar como teste).
2. **Aliases imediatos** (função já existe, só o path diverge):
   - `fiscal/nfe` → `NotaFiscalController` (`notas`); `.../transmitir` → `emitir`; `.../cancelar`.
   - `financeiro/dre` → `RelatorioController::dre` (hoje em `relatorios/dre`).
   - `produtos/:id/estoque` → `EstoqueController` filtrado por produto.
3. **Endpoints novos de núcleo** (controller fino sobre service existente):
   `produto-config/classes|unidades` (CRUD de ProdutoClasse/UnidadeMedida — models já existem),
   `produtos-precos/preview|aplicar` (reajuste em lote — novo ProdutoPrecoService),
   `estoque/requisicoes`, `estoque/fechamentos/abrir`, `grupos*` (GrupoController — model existe),
   `cheques/recebidos/:id` PUT/DELETE (ChequeService já tem `atualizar`/`excluir`).
4. **RBAC fim-a-fim** — o conserto estrutural:
   - `AuthController::me` passa a retornar `roles` + `permissions` (derivados de `role_user`/`permission_role`, que já têm tabela).
   - Seed de roles/permissions por módulo (`pedido.criar`, `caixa.estornar`, …).
   - `temPermissao()` aplicado nos controllers (bypass `support`).
   - `can()` da SPA passa a funcionar para usuário não-support.

### DoD (verificável)
- `php artisan test --filter=ContratoSpaTest` verde: **0 endpoint da SPA sem rota**.
- Login como usuário **não-support** com role limitada → SPA esconde o que não pode, mostra o que pode.
- Abrir cada uma das 12 features logado → **nenhuma chamada 404 no Network**.

---

## FASE C2 — Empresa / Config + Uploads  ·  CRÍTICO · ~1–2 sem

### O que falta
`empresas/:id/certificado` (upload A1 + senha), `nfce-token`, `config/testar-email`.
Infra de upload/download segura inexistente.

### Entregas
- `EmpresaConfigController`: upload de **certificado A1** (`.pfx`), senha **re-encriptada** (não em texto), validade lida do certificado; `nfce-token` (CSC NFC-e); `testar-email` (SMTP real).
- Service de storage seguro (disco privado, fora de `public/`), download autenticado.
- Pré-requisito de C7 (o fiscal precisa do certificado).

### DoD
- Subir um A1 de teste em homologação; ler validade; senha não trafega/armazena em claro.
- `testar-email` envia e retorna sucesso/erro real do SMTP.

---

## FASE C3 — Seeds + ETL contra dump real  ·  CRÍTICO · ~1–2 sem

### O que falta
Só `DeployAdminSeeder` (admin/empresa). Nenhuma tabela de negócio populada. ETL nunca rodou contra dump real (homologação vazia).

### Entregas
- Seeds de homologação de **todas as tabelas de negócio** (ver `PLANO_SEEDS.md`), via Services (saldos nascem consistentes).
- Obter **dump anonimizado de produção**; rodar `etl:run` no banco novo; `cutover:check`.
- Corrigir o que o ETL real quebrar (tipos, FKs, valores string-BR).

### DoD
- `php artisan migrate:fresh --seed` popula todas as ~55 tabelas; nenhuma vazia.
- `etl:run --check` e `cutover:check` **verdes contra dump real** (Σ movimentos = saldo, contagens, integridade).

---

## FASE C4 — Fechar regras divergentes: Pedido / Financeiro / Caixa  ·  ALTO · ~2–3 sem

### O que falta (medido na auditoria, §1.1–1.2)
- Pedido: movimentação por **matriz operação×setor**; **parcelamento por condição de pagamento**; cartão (autorização/NSU); **Gás do Povo** (financeiro paralelo); vale-gás como condição; estorno de pagamento online ao cancelar.
- Caixa: **bloqueio de movimento em caixa fechado**; **CP/taxa de cartão** na baixa; baixa de títulos **em lote**; permissões por operação.
- Cheque: encontro de contas, troco/adiantamento, devolução.

### Entregas
- `PedidoService`: ler `pedidooperacao.movimenta_estoque/financeiro` (decidir efeito por config, não hardcode); `gerarDoPedido` respeitar `condicaopagamento_id` → N parcelas; persistir cartão/NSU; ramo Gás do Povo (financeiro próprio + parcelas).
- `CaixaService::movimentar`: recusar se `conta.fechado` (salvo operação explícita de lançamento-fechado com permissão); `baixarParcela` gera CP de taxa de cartão; `baixarTitulos` (lote).
- `ChequeService`: encontro de contas, transferência troco/adiantamento, devolução.
- Tabelas novas: `condicaopagamentos` (+parcelas), `pedidooperacoes` (campos de movimentação).

### DoD
- Teste de baseline: pedido a prazo gera **N parcelas** conforme condição (não 1).
- Teste: movimento em caixa fechado **é recusado**.
- Teste: cancelar pedido com pagamento online **estorna** (gate fake em CI).

---

## FASE C5 — RH / Colaborador / Comissão  ·  ALTO · ~3–4 sem

### O que falta
Domínio inteiro: `colaboradores`, `cargos`, `colaboradorfamilias`, `colaboradorferias`,
`recessos`/`recessotipos`, `turnos`, `colaboradorexames`/`tipoexames`,
`colaboradorcomissaos`, `comissaoexcecoes`. Endpoints `colaboradores/*` (SPA já chama).

### Entregas
- Migrations + Models + `ColaboradorService` (CRUD, família, recessos, exames).
- **`ComissaoService`** — porta o cálculo de comissão do legado (regra de dinheiro → **baseline obrigatório**).
- Endpoints `colaboradores`, `.../familia`, `.../recessos`, `.../comissoes` (a SPA já os consome).
- ETL: `ColaboradoresMigrator`.

### DoD
- Telas de colaborador da SPA funcionais (0 404).
- **Comissão calculada confere com o legado** (caso de ouro, ±0,00).

---

## FASE C6 — Frota / Veículos  ·  ALTO · ~2–3 sem

### O que falta
`veiculos` (de negócio — distinto de `monitora_veiculos`), `veiculoabastecimentos`,
`veiculopneus`, `veiculotrocaoleos`, `veiculodocumentos`, `veiculoentradasaidas`,
`tipocombustivels`. Endpoints `veiculos/*`.

### Entregas
- Migrations + Models + `VeiculoService` (consumo médio km/l, alerta de troca por km/data).
- Endpoints `veiculos`, `.../abastecimentos`, `.../trocas-oleo`, `.../pneus`.
- ETL: `FrotaMigrator`.

### DoD
- Telas de frota funcionais; consumo médio e alertas calculados; 0 404.

---

## FASE C7 — FISCAL REAL (porte 1:1)  ·  CRÍTICO/LEGAL · ~6–10 sem

> **A maior lacuna.** Legado: ~19.250 LOC (Nfe 8.729 + Sped 10.523). Novo: 395 LOC + driver fake.
> **Estratégia: PORTAR, não reescrever** — risco fiscal. Subdividido em C7a–C7d.

### C7a — Cálculo de imposto completo  (~2–3 sem)
**Falta (auditoria §1.3):** ICMS-ST, DIFAL, FCP/FCP-ST, MVA, redução de base, diferimento, IBPT (aliq nac/est/mun), IBS/CBS (reforma 2026), CST/CSOSN por regime.
- Portar `NfeImpostoProcessor`/`IcmsBase`/`CalculoImposto` do legado.
- Tabelas: `nfimpostos`, `nfimpostoestados`, `produtoleiimpostos` (IBPT), config por operação×grupo×UF.
- **Baseline:** mesma entrada → mesmo imposto que o legado (casos de ouro por CST/UF).

### C7b — XML + SEFAZ + certificado + DANFE  (~3–4 sem)
**Falta:** geração de XML (TagMaker/MakeXml), comunicação SEFAZ, assinatura, DANFE, CCe, inutilização. Driver atual é **fake**.
- Portar via **NFePHP** (`sped-nfe`): transmitir/consultar/cancelar/inutilizar/CCe.
- `SefazDriver` **real** (mantém o `Fake` para CI); usa certificado A1 da C2.
- **Numeração com lock** (já existe `NumeroSequencialService` — integrar).
- DANFE/DANFCe (PDF) e envio por e-mail.
- **Gate de homologação SEFAZ** (fora do CI).

### C7c — NF recebida (entrada)  (~1–2 sem)
**Falta:** `nfrecebidas`/itens/parcelas/volumes; import de XML; movimentação de estoque/financeiro pela entrada.
- `NfEntradaService` (importa XML via Standardize → estoque (EstoqueService) + financeiro (FinanceiroService)).

### C7d — SPED Fiscal/Contribuições  (~2–3 sem)
**Falta:** 10.523 LOC (119 Reg*). Geração dos blocos por leiaute Receita.
- Portar `SpedProcessor` + registros; `spedfiscals`, `spedcontribuicaos`, `creditopiscofins`.

### DoD da C7
- NF-e/NFC-e **autorizada na SEFAZ de homologação**, XML batendo com o legado (caso de ouro).
- SPED gera arquivo **validado** pelo PVA da Receita.
- NF de entrada movimenta estoque/financeiro.

---

## FASE C8 — Conciliação/OFX + Relatórios + Export  ·  ALTO · ~5–7 sem

### O que falta
`financeiro/conciliacao` (endpoint), import OFX (`contaextratoconfigs`, `layoutbancos`),
**~31 dos 35 relatórios** (só 4 existem: vendas/financeiro/dre/estoque-baixo), export PDF/Excel/CSV, CNAB/DANFE como download.

### Entregas
- `ConciliacaoService` (import OFX via `beccha/ofxparser`, casamento com movimentos).
- Relatórios restantes como Query Services parametrizados; **export** (lib PDF/Excel).
- Dashboards gerenciais.

### DoD
- Conciliação importa OFX e casa lançamentos; relatórios essenciais com export; 0 404 em `financeiro/conciliacao`.

---

## FASE C9 — Jobs de fila + cron completos  ·  MÉDIO · ~1–2 sem

### O que falta (auditoria §1.4)
0 jobs de fila; 2 de 10 cron. Faltam: `pix:expired`, `ibpt:update`, `vendadiaria:send`,
`remembermail:send`, `documentosvencidosmail:send`, `order:send`, `notify:inconsistencies`,
`notify:delete`, `ProcessAppVideo`, `ProcessPixPedido`, emissão fiscal assíncrona.

### DoD
- `php artisan schedule:list` lista todos; jobs enfileiram e processam; PIX expira sozinho.

---

## FASE C10 — Pós-venda/CRM + Promoção/Sorteio/Metas + Checklist  ·  MÉDIO · ~3–5 sem

### O que falta
`posvendas*`, `vendaativas*`, `promocaos`/`clientepromocaos`, `sorteios`, `metavendas`,
`motivonaovendas`, `checklists*` (7 tabelas).

### Entregas
- Migrations + Models + Services + endpoints + telas SPA correspondentes.
- Mala-direta/e-mail em massa via Job (depende de C9).

### DoD
- Telas correspondentes funcionais; campanhas/checklists operando.

---

## FASE C11 — Cupom fiscal (SAT/CFe) + Inventário + Documentos/Bens  ·  MÉDIO · ~3–4 sem

### O que falta
`cuponsfiscais*`, `nfcests`, `nfclastribs` (SAT/CFe SP); `inventarios`/`inventarioitems`,
`mcmms*`; `documentos*`, `empresabems`/`empresabemdepreciacaos`.

### Entregas
- `CupomFiscalService` (SAT/CFe — gate regional); `InventarioService`; `DocumentoService` (gestão documental + depreciação).

### DoD
- Conforme uso real (avaliar escopo do SAT/CFe pelo dump); inventário concilia com estoque.

---

## FASE C12 — Cauda + cadastros de apoio restantes  ·  BAIXO · ~2–3 sem

### O que falta
Cadastros de apoio de 7 → ~40 (agências, bancos completos, contatipos, etc.); relatórios raros; remoção de endpoints ociosos; menu/RBAC dinâmico se necessário.

### DoD
- Nenhum cadastro de apoio do legado sem equivalente; superfície completa.

---

## FASE C13 — CUTOVER  ·  após paridade aceitável

1. **Freeze** de escrita no legado (janela combinada).
2. **Dump** de produção → **`etl:run` completo** (todos os Migrators).
3. **`cutover:check` 100% verde**: contagens; `Σ contamovimentos = saldo`; `Σ estoquehistorico = saldo`; Σ títulos; FK órfã = 0; certificados + numeração de NF migrados.
4. Apontar SPA/apps para o erp-novo; legado em **standby N dias** (rollback possível).
5. Acompanhamento pós-cutover (saldos, emissão fiscal, pagamentos).

**DoD do projeto:** app nova em produção; invariantes 100% verdes no dump real; legado desligável.

---

## 2. Linha do tempo

| Marco | Fases | Acumulado (1 dev sênior) |
|---|---|---|
| **Núcleo homologável** (telas abrem, RBAC, seeds, regras de dinheiro) | C1+C2+C3+C4 | **~6–10 sem** |
| + RH + Frota | C5+C6 | +5–7 sem |
| + **Fiscal real** (o grande bloco) | C7 | +6–10 sem |
| + Conciliação/Relatórios | C8 | +5–7 sem |
| + Jobs + satélites + cauda | C9+C10+C11+C12 | +9–14 sem |
| **Paridade total + cutover** | C13 | **~8–12 meses** |

Com **2–3 devs em paralelo** (C5/C6/C7/C8 são independentes após C1): **~4–5 meses**.

> Correção de expectativa vs. o plano original: o N9 ("Fiscal") sozinho é **C7a–C7d = ~6–10 semanas**,
> não uma fase de uma linha. Foi essa subespecificação que deixou o plano antigo "incompleto".

---

## 3. Como este plano evita repetir o erro

1. **Cada fase tem DoD checável por `grep`/teste**, não por "arquivo existe".
2. **C7 (fiscal) está subdividido** em 4 entregas reais com baseline e gate — não mais uma linha.
3. **C1 trata o contrato SPA↔backend como pré-requisito** (a auditoria provou que 48% quebra).
4. **C3 exige ETL contra dump real** antes de declarar homologação — não banco vazio.
5. **Regras divergentes (C4)** entram explicitamente, com o nome da regra e a linha do legado.
6. O teste de contrato (`ContratoSpaTest`) e o `cutover:check` viram **portões automáticos**.
