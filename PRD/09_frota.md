# PRD — Frota / Veículos  ·  D09

- **Status:** ✅ pronto
- **Criticidade:** 🟡 (apoio operacional; não fiscal)
- **Decisão:** **REESCREVER** (CRUDs limpos, bom candidato ao padrão novo)

---

## 1. Escopo
- **Controllers:** `Veiculo` (313), `Veiculoentradasaida` (247), `Veiculotrocaoleo` (190),
  `Veiculoabastecimento` (149), `Veiculotipo` (144), `Veiculopneu` (110), `Veiculodocumento` (86).
- **Tabelas (public):** `veiculos`, `veiculotipos`, `veiculoabastecimentos`,
  `veiculodocumentos`, `veiculoentradasaidas`, `veiculopneus`, `veiculotrocaoleos`.
- **Rotas:** resources homônimos.

## 2. O que o módulo FAZ
- **Cadastro de veículos** da frota (placa, tipo, km atual, motorista, ativo).
  Liga a `colaborador` (motorista) e ao **monitoramento** via `veiculoerp_id`
  (vínculo ERP↔GPS, criado na migration do monitora).
- **Abastecimento**: registra km anterior/atual, litros, **calcula km rodado e
  média de consumo**; pode atualizar o km atual do veículo e o motorista.
- **Troca de óleo / pneu / entrada-saída / documentos**: controles de manutenção e
  documentação, alguns com vencimento/alerta.

## 3. Como FAZ hoje
- CRUDs Laravel resource limpos (0 `$_GET`/whereRaw interpolado/HTML em PHP).
- Cálculo de consumo no controller (`mediaconsumo`, `kmrodado`) com helpers de
  conversão de número (`converterLitrosBanco`).

## 4. Gambiarras / dívida técnica
- [ ] Cálculo de consumo embutido no controller (deveria ser Service/Action).
- [ ] Conversões numéricas via helpers globais (`converterLitrosBanco`) — ok, mas
      candidato a Value Object/cast no modelo novo.
- [ ] Sem alerta automatizado de vencimento (documento/óleo) aparente — verificar
      se há job; se não, é oportunidade no novo.
- Nenhuma gambiarra grave; domínio bem comportado.

## 5. Riscos de tocar
- **Baixo.** Não é fiscal. Único acoplamento relevante: `veiculoerp_id` ↔ módulo
  Monitora (preservar o vínculo) e `colaborador_id` (motorista).

## 6. Estado de compatibilidade Postgres
- ✅ Limpo; validado na varredura (200). Lat/long e numéricos já tratados na Fase 3.

## 7. Visão REESCRITA (Laravel 12)
- Recurso `Veiculo` + sub-recursos (abastecimento, óleo, pneu, documento, entrada/saída)
  com FormRequest + Resource + Policy.
- **Cálculo de consumo em Service/Action** testável (entrada km/litros → média).
- **Alertas de manutenção/documento** como feature nova (job + notificação).
- UI moderna: ficha do veículo com timeline de manutenção e consumo (gráfico).
- Preservar o vínculo `veiculoerp_id` com o módulo Monitora.

## 8. DECISÃO e justificativa
- **Decisão: REESCREVER.**
- **Por quê:** baixo risco (não fiscal), código já razoável, e ganho de UX/feature
  alto (timeline, alertas, gráfico de consumo). Excelente módulo para consolidar o
  padrão novo depois dos cadastros de apoio.
- **Pré-requisitos:** D11 (navegação nova); definir Service de consumo.
- **Esforço:** baixo-médio.
- **Ordem:** após cadastros de apoio do D10; antes dos transacionais pesados.
