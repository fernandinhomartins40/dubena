# PRD DE IMPLEMENTAÇÃO — Fiscal (NF-e / NFC-e / SAT / Malha Fiscal / SPED) · auditado

> Auditado: NfemitidaController (núcleo maduro), Nfrecebida, NfwebController (token),
> malha fiscal (Nficms/Nfipi/Nfpis/Nfcofins/Nfcst/Nfclasstrib/Nfgrupofiscal/NfOperacao/Impostonf/
> Nfsituacao), Sped (fiscal/contribuição/créditos), CupomFiscal. Decisão: REFATORAR (preservar motor).

## 1. NÚCLEO NF-e/NFC-e (PRESERVAR — código mais maduro)
- **NfemitidaController**: emissão/transmissão/cancelamento/carta de correção/consulta/importação
  XML; status da NF; geração a partir do pedido. NfeImpostoProcessor (init encadeia ICMS/ST/IPI/PIS/
  COFINS). **NfwebController::getToken** já corrigido (config('integracoes.app_token_key') + hash_equals, F0).
- **Nfrecebida**: importação/lançamento de NF de entrada (XML). NfRequest: `unique chaveacesso` já trata
  id vazio (não tem o bug PG).
- **SAT/NFC-e**: CupomFiscal, configuração NFC-e.

## 2. MALHA FISCAL (consolidar — hoje ~8 telas dispersas em "Administração")
- Nfgrupofiscal (grupo fiscal), NfOperacao (operações), Impostonf, e CSTs: Nficms (CST/CSOSN ICMS),
  Nfipi (CST IPI), Nfpis (CST PIS), Nfcofins (CST COFINS), Nfcst, Nfclasstrib, Nfsituacao, IBPT.
- Cada um é cadastro com regras tributárias; juntos formam a malha que o produto/pedido usa.

## 3. SPED
- Spedfiscal (EFD ICMS/IPI), Spedcontribuicao (PIS/COFINS), Spedcreditos. Motor de blocos/registros
  (SpedProcessor). Geração de TXT por período. (F0: typo catch corrigido.)

## 4. 🔴 PENDÊNCIA BLOQUEANTE
- **VALIDAR EMISSÃO EM HOMOLOGAÇÃO SEFAZ** (Carbon 3 + PHP 8.3 podem afetar datas/decimais da NF-e).
  Oráculo = SEFAZ aceita/rejeita. SPED: validar no PVA da Receita. Ação que depende de certificado/ambiente.

## 5. REORGANIZAÇÃO / UX (MAPA_NAVEGACAO_ALVO)
| Telas legadas | Página-alvo |
|---|---|
| nfemitida (emissão/status), Gerais>NFe | **NF-e / NFC-e** (lista com status: rascunho/transmitida/autorizada/cancelada + ações) |
| nfrecebida | **NF Recebida** (importação XML + lançamento) |
| Grupo Fiscal + CST ICMS/IPI/PIS/COFINS + Nfcst/Nfclasstrib + Situação NF + IBPT + Operação + Impostonf | **Malha Fiscal** (1 página com abas por imposto) — fim das ~8 telas soltas |
| Spedfiscal + Spedcontribuição + créditos | **SPED** (gerar com preview de blocos/contagem + validações + histórico) |
| CupomFiscal / config NFC-e | **NFC-e / SAT** |
**Visão nova:** NF com status visível e ações no lugar; malha fiscal coesa (configurar tributação num
fluxo, não caçando 8 telas); SPED com preview antes do download.

## 6. API ADMIN + Service
- Refatorar motor fiscal em Service testável (oráculo SEFAZ homolog). API: /fiscal/nfe (emitir/transmitir/
  cancelar/cce/consultar), /fiscal/nf-recebida (importar), /fiscal/malha/* (CRUD dos cadastros tributários),
  /fiscal/sped (gerar). Aplicar fix unique-PG onde houver concatenação de id em FormRequests fiscais.

## 7. DoD
1. NF-e/NFC-e emissão/transmissão/cancelamento/CCe/importação preservados.
2. Malha fiscal consolidada (abas) — todos os CSTs/grupo/operação/IBPT presentes.
3. SPED gerável (fiscal + contribuições) com preview.
4. **Emissão validada em homologação SEFAZ** (bloqueante p/ ligar em produção real).
5. Testes + suíte verde; sem regressão fiscal (golden quando houver dados reais).
