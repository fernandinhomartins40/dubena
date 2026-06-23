# Comparativo ctrl-web (legado) × erp-novo — estado ATUAL

> Reavaliação após as fases C1–C12 (não a foto da auditoria forense original).
> Baseado no código de hoje. O legado segue mais completo na **cauda longa**; o
> núcleo transacional do novo está fechado e testado.

## Métricas (medidas)

| | ctrl-web (legado) | erp-novo (hoje) |
|---|---:|---:|
| Controllers | 271 | 40 |
| Processors / Domain Services | 144 | 30 |
| Models | 203 | 83 |
| Tabelas (`Schema::create`) | 213 | 89 |
| LOC `app/` | ~139.000 | ~15.400 |
| Testes | 1 (Inspire) | 51 arquivos / 228 testes |

O novo cresceu ~40% desde a auditoria (era 69 tabelas / 31 controllers / 11,6k LOC).
A distância de LOC (8% → ~11%) é enganosa: o legado carrega muito código repetido
e formatação manual (`*Oracle`, arrays posicionais) que no novo viraram Services +
Resources enxutos. A diferença REAL é de **escopo funcional**, abaixo.

---

## Matriz por domínio (✅ existe · 🟡 parcial · ❌ ausente)

### Núcleo transacional — paridade boa
| Domínio | Novo | Observação |
|---|:--:|---|
| Cliente (+ telefones, contatos, convênio, preços, histórico) | ✅ | Service + sub-recursos |
| Produto (+ classes, unidades, origens, preços em lote) | ✅ | |
| Estoque (saldo auditável, entrada/saída/transferência/acerto/fechamento) | ✅ | Σ histórico = saldo |
| Estoque: requisição, inventário, estoque físico | ✅ | C11 |
| Pedido / venda (máquina de estados) | ✅ | |
| Financeiro (a pagar/receber, parcelas, rateio, agrupamento) | ✅ | |
| Caixa / Conta / Cheque (saldo auditável, baixa em lote, caixa fechado) | ✅ | C4 |
| Condição de pagamento (parcelamento real) | ✅ | C4 |
| Empresa / Grupo / Config / Certificado A1 / NFC-e token | ✅ | C2 |
| RBAC (roles/permissions reais) | ✅ | C1 |
| Convênio / Vale-gás / Comodato | ✅ | |
| Cobrança: Boleto / PIX (gates) | 🟡 | fluxo + gate fake; banco/PSP reais por homologação |
| Mobile (app cliente/entregador) | 🟡 | base; PedidoMobile/Pagamento gates |
| Monitora (GPS) | 🟡 | service + sync gate SGCasa |

### RH / Frota — implementados (C5/C6)
| Domínio | Novo | Observação |
|---|:--:|---|
| Colaborador (+ família, recessos, comissões) | ✅ | C5 |
| Comissão (cálculo portado do legado) | ✅ | baseline caso de ouro |
| Cargos | ✅ | |
| Veículos (+ abastecimento, troca óleo, pneus, consumo, alerta) | ✅ | C6 |
| Colaborador: exames, turnos, ponto | ❌ | tabelas do legado sem equivalente |

### Fiscal — cálculo completo; emissão é gate
| Domínio | Novo | Observação |
|---|:--:|---|
| Cálculo de imposto (ICMS/ST/DIFAL/FCP/redução/PIS/COFINS/IPI) | ✅ | C7a, porte fiel + casos de ouro |
| Operações fiscais / malha (CFOP/CST) | ✅ | C12 |
| XML NF-e/NFC-e + transmissão SEFAZ | 🟡 | C7b: lib NFePHP + driver real, mas **só valida com certificado+homologação** |
| NF de entrada (import XML → estoque/financeiro) | ✅ | C7c |
| SPED Fiscal (EFD ICMS/IPI) | 🟡 | C7d: gera arquivo; validar no PVA |
| **SPED Contribuições (PIS/COFINS) + créditos** | ❌ | legado tem; novo não |
| **IBPT** (lei de olho no imposto) | ❌ | |
| Cupom fiscal SAT/CFe | ❌ | |
| DANFE / carta de correção / inutilização | ❌ | dependem do gate fiscal |

### Conciliação / Relatórios
| Domínio | Novo | Observação |
|---|:--:|---|
| Conciliação bancária (OFX) | ✅ | C8 (parser próprio) |
| DRE + relatórios essenciais (vendas/financeiro/estoque/comissão/caixa) | 🟡 | ~6 dos ~26 do legado; export CSV |
| Dashboard (resumo) | ✅ | |
| **~20 relatórios restantes** (aniversariantes, questionários, malote, logs, promotor, etc.) | ❌ | |
| Export PDF/Excel (além de CSV) | ❌ | |

### Automação
| Domínio | Novo | Observação |
|---|:--:|---|
| Cron: alertas, pix expirar, vencidos, GPS | ✅ | C9 |
| **Cron restantes** (IBPT diário, e-mails venda/remember, order:send, inconsistências) | 🟡 | lógica parcial; envio SMTP/externo é gate |
| Lookups (29 dropdowns) | ✅ | recém-criado |

### Módulos satélite AUSENTES (❌ — confirmados por grep = 0)
- **Pós-venda / questionário** (`posvenda*`, `vendaativa*`)
- **Promoção** (`promocao`, `clientepromocao`)
- **Sorteio** (`sorteio`)
- **Metas de venda** (`metavenda`, `motivonaovenda`)
- **Checklist** (7 tabelas: `checklist*`)
- **Mala-direta** (e-mail em massa)
- **MCMM** (`mcmm*`)
- **Gestão de bens / depreciação** (`empresabens`, `empresabemdepreciacao`)
- **Gestão documental** (`documentos`, `documentotipos`, versões)
- **Feriados / eventos**
- **Telas "gestão" consolidadas** (Comodatogestao, Documentogestao, Conveniogbgestao,
  Financeirogestao, Vendasmensaisgestao, Fechamentomensalgestao)
- **Agências bancárias**, layouts de banco/retorno (conciliação CNAB completa)

---

## Resposta direta

**Sim, o legado ainda é mais completo** — mas a diferença concentrou-se na **cauda
longa** (módulos satélite + relatórios + fiscal-gate), não mais no núcleo:

- **Núcleo de operação (vender, estocar, cobrar, caixa, cliente, produto, RH, frota):**
  paridade funcional **boa** — o que faltava de regra (comissão, parcelamento,
  caixa fechado, cálculo de imposto) foi portado com baseline.
- **O que ainda falta de verdade (~12 módulos satélite + ~20 relatórios + SPED
  Contribuições/IBPT/SAT):** são funções periféricas e de gate fiscal.

**Aderência estimada hoje:** ~55–65% do escopo do legado (era ~30–40% na auditoria).
Núcleo ~85%; cauda longa ~15%.

## Próximos blocos para fechar a lacuna (ordem sugerida)
1. **Relatórios restantes + export PDF/Excel** (maior valor percebido pelo usuário).
2. **Pós-venda / Metas / Promoção / Checklist** (módulos satélite de CRM/gestão).
3. **Fiscal: SPED Contribuições + IBPT + (avaliar) SAT/CFe.**
4. **Gestão documental / bens / feriados** (cadastros de apoio restantes).
5. **Cron/e-mails reais** (quando o SMTP por empresa estiver configurado — C2 já tem a base).
