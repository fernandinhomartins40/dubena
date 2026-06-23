# Plano de Conclusão da Migração — ctrl-web → erp-novo

> Plano de implementação derivado da auditoria (`AUDITORIA_ADERENCIA_LEGADO_VS_NOVO.md`).
> Sequenciado por risco e dependência. Cada fase = uma branch, com testes + ETL +
> validação na homologação (gasemcasa.com/novo). Regra: o legado segue intocado até o cutover.

---

## Fase A — Alinhamento de contrato SPA × backend (CRÍTICO) · ~2–3 sem
**Por quê:** sem isso, as telas da SPA quebram mesmo nos módulos já prontos.

1. **Levantar o contrato real** (`frontend/src/features/*/api.ts`) e gerar uma matriz
   endpoint-SPA → rota-backend (a auditoria já lista os divergentes).
2. **Resolver divergências** (escolher: ajustar backend OU ajustar a SPA):
   - Fiscal: SPA usa `/fiscal/nfe*`; backend `/notas*` → **adicionar aliases**
     `/fiscal/nfe`, `/fiscal/nfe/{id}/transmitir|cancelar` apontando ao NotaFiscalController.
   - `/financeiro/dre` e `/financeiro/conciliacao` → expor no FinanceiroController
     (DRE pode delegar ao RelatorioService).
   - `/produto-config/classes|unidades`, `/produtos-precos/*`, `/produtos/{id}/estoque`.
   - `/estoque/requisicoes`, `/estoque/fisico/{id}/efetivar`, `/estoque/fechamentos/abrir`.
   - `/grupos*` (criar GrupoController).
   - `/cheques/recebidos/{id}` (PUT/DELETE).
3. **`/me` com roles/permissions reais** — popular do RBAC (não depender de `support`).
4. **DoD:** abrir cada tela da SPA logado como `teste@` e nenhuma chamada 404/contrato.

## Fase B — Empresa completa + uploads (CRÍTICO) · ~1–2 sem
- `EmpresaConfigController`: `certificado` (upload A1 + senha re-encriptada), `nfce-token`,
  `config/testar-email`.
- Infra de **upload** (storage seguro) e **download** (arquivos) reutilizável.
- **DoD:** subir certificado A1 em homologação; testar e-mail SMTP.

## Fase C — Seeds + ETL real (CRÍTICO) · ~1–2 sem
- Implementar os seeds de `PLANO_SEEDS.md` (todas as tabelas de negócio).
- Obter **dump anonimizado** de produção; rodar `etl:run` no banco novo; `cutover:check` verde.
- **DoD:** homologação populada; invariantes 100% verdes; nenhuma tabela vazia.

## Fase D — Colaborador / RH (ALTO) · ~3–4 sem
- Schema: colaboradores, cargos, família, recessos (+tipo), turnos, exames, comissões.
- ColaboradorService (cálculo de comissão portado do legado), endpoints `/colaboradores/*`.
- ETL + testes baseline de comissão.
- **DoD:** telas de colaborador da SPA funcionais; comissão confere com o legado (caso de ouro).

## Fase E — Frota / Veículos (ALTO) · ~2–3 sem
- Schema: veiculos (negócio, separado do monitora_veiculos), abastecimentos, pneus,
  trocas-oleo, documentos de veículo, entrada/saída.
- VeiculoService (consumo médio, alerta de troca por km/data), endpoints `/veiculos/*`.
- **DoD:** telas de frota funcionais.

## Fase F — Fiscal completo (ALTO) · ~4–6 sem
- **NF recebida** (entrada) + import de XML (Standardize) → estoque/financeiro via Services.
- **SPED** Fiscal/Contribuições/Créditos (portar `SpedProcessor` + Reg*).
- **IBPT** (UpdateTabelaIbpt + ProcessIbptFiles).
- Operações fiscais / malha fiscal (config Imposto por operação×grupo×UF).
- **DoD:** SPED gera arquivo válido; NF de entrada movimenta estoque/financeiro.

## Fase G — Conciliação / OFX + Relatórios + Export (ALTO) · ~5–7 sem
- Importação de extrato (OFX) e conciliação bancária.
- **~31 relatórios** restantes como Query Services + **export PDF/Excel/CSV**.
- DANFE/boleto-PDF/remessa-CNAB como download.
- Dashboards gerenciais.
- **DoD:** relatórios essenciais com export; conciliação funcional.

## Fase H — Cron jobs + automação (MÉDIO) · ~1–2 sem
- Recriar os 16 jobs faltantes: documentos vencidos, remember mail, venda diária,
  inconsistências, pix cancel expired, order status, CFe WS, etc.
- **DoD:** `schedule:list` com todos; jobs disparam e enfileiram.

## Fase I — Módulos satélite secundários (MÉDIO) · ~3–5 sem
- Pós-venda + questionário, promoção, sorteio, mala-direta (e-mail em massa via Job),
  metas de venda, checklist, eventos/feriados, gestões consolidadas.
- **DoD:** telas correspondentes na SPA.

## Fase J — Cauda + limpeza (BAIXO) · ~2–3 sem
- Relatórios raros, consolidação de contratos, remoção de endpoints ociosos,
  cadastros de apoio restantes (de 7 para ~40).

## CUTOVER (após paridade aceitável)
1. Freeze de escrita no legado (janela combinada).
2. Dump de produção → `etl:run` completo → **`cutover:check` 100% verde**.
3. Apontar SPA/apps para o erp-novo; legado em standby N dias (rollback possível).
4. Acompanhamento pós-cutover (saldos, emissão fiscal, pagamentos).

---

## Linha do tempo (1 dev sênior, sequencial)
| Marco | Acumulado |
|---|---|
| Homologação do **núcleo** (Fases A+B+C) | ~4–6 semanas |
| + RH + Frota (D+E) | ~+6–7 semanas |
| + Fiscal completo (F) | ~+4–6 semanas |
| + Conciliação/Relatórios (G) | ~+5–7 semanas |
| + Jobs + satélites + cauda (H+I+J) | ~+6–10 semanas |
| **Paridade total + cutover** | **~6–9 meses** |

Paralelizar com 2–3 devs reduz para ~3–4 meses (RH/Frota/Fiscal/Relatórios são
independentes entre si após a Fase A).

---

## Riscos
- **Fiscal (SPED/NF-e/IBPT):** obrigação legal — portar, não reescrever; baseline obrigatório.
- **Dump real:** sem ele, o ETL e os relatórios não são validáveis de verdade.
- **Contrato SPA:** a SPA veio do legado; tratar a Fase A como pré-requisito de tudo.
- **Gates externos** (SEFAZ/banco/PIX/Rede/SGCasa): exigem credencial/homologação,
  fora do CI — planejar janelas com cada provedor.
