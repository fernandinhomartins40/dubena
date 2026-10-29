# Veredito — Auditoria integral PIE V2

**Escopo:** `erp-novo` (API, SPA), `app-gas-em-casa`, `app-entregador`, `mobile-shared`, deploy e VPS de homologação. Fora do escopo: `ctrl-web/` e `legado-*`.
**Commit de partida:** `48b29f72` · **Data:** 2026-10-29 · **Ambiente de prova:** sqlite (suíte), PostgreSQL 15 local com RLS, VPS de homologação só leitura.

## 1. Veredito inicial (antes das correções) — **REPROVADO**

Havia bloqueadores confirmados:
- **PIE-01, CRÍTICA, PoC verde:** tomada de conta administrativa pelo cadastro público do app.
- **PIE-06/07/08, ALTA, teste:** recebimento e dívida em dobro no financeiro.
- **PIE-09/10, ALTA, teste:** duas NF-e autorizadas por pedido; pedido cancelado com nota autorizada.
- **PIE-05, ALTA, teste:** pedido concluído sem baixa de estoque nem título.
- **PIE-03/04, ALTA, PoC:** IDOR de PII; empresa suspensa operando.
- **PIE-12/13, ALTA:** backup de produção apontando para a homologação; nenhum backup da base com dados reais.

Pareceres da banca: R1 REPROVADO · R2 REPROVADO · R3 REPROVADO · R4 NÃO COMPROVADO · R5 REPROVADO.

## 2. Veredito pós-correção — **REPROVADO para produção / cutover**

As correções desta execução **resolvem e provam** os bloqueadores de segurança,
dinheiro, estoque e fiscal listados acima:
- suíte de 1993 testes sem falha;
- portão RLS 26/26 no Postgres;
- 15 testes novos que falham sem a correção.

**Sobre o deploy na homologação:** as alterações estão prontas e verificadas
localmente. O deploy não foi feito, porque push na `main` dispara deploy e isso
aguarda o seu OK.

## 2b. Veredito atualizado (29/10, após as decisões do dono) — **NÃO COMPROVADO para produção**

Fechados nesta rodada, com evidência:
- **PIE-11**, pagamento online × título: implementado pelas regras do dono. `PiePagamentoOnlineTest` tem 9 testes, e 8 falham sem a correção.
- **PIE-13**, backup: cron diário, dump e restore verificado (RTO 223 s).
- **PIE-21**, licença ligada na homologação, depois de provar como `erp_app` que nenhuma empresa fica bloqueada.

Suíte: **2002 testes, 0 falhas** (26 pulados, Pg); portão RLS 26/26; SPA 171/171.

Não resta **bloqueador confirmado** de código. Resta prova que só existe com
credencial ou ambiente que ainda não há. Pelo protocolo, isso é **NÃO COMPROVADO**,
não aprovado:

| Pendente | Natureza | O que destrava |
|---|---|---|
| Login real do app (PIE-22) | Credencial | Projeto Firebase do dono (roteiro em `docs/06-runbooks/FIREBASE_LOGIN_POR_TELEFONE.md`; pacotes novos `br.com.dubena.gasemcasa[.entregador]`) |
| NF real (PIE-02, FORTE INDÍCIO de defeito no driver) | Credencial | Certificado A1 + respostas SOAP gravadas, antes de ligar `FISCAL_DRIVER=nfephp` |
| PIX e cartão reais | Credencial | PSP contratado; hoje só provados com driver fake |
| Ambiente de produção | Ambiente | Não existe na VPS; o deploy e o backup de produção foram revisados, não executados |
| Cópia do backup fora da VPS | Risco **aceito pelo dono** (29/10) | — |
| SSH root por senha (PIE-14) | Risco não tratado (fail2ban não escolhido) | Decisão do dono |

### Histórico — veredito pós-correção da primeira rodada: REPROVADO

Antes das decisões do dono, restavam bloqueadores confirmados: PIE-11 (pagamento
duplo e título em aberto de pedido pago), PIE-13 (sem backup) e PIE-21 (licença
desligada). Todos foram fechados acima.

## 3. Riscos residuais aceitos nesta execução

- PIE-28 (contagem cara sob RLS), PIE-27 (corrida de cache), PIE-29 (limites, healthcheck, alertas): degradam, não corrompem. Pendentes com plano.
- PIE-16 (fila offline): corrigida sem teste automatizado (o app do entregador não tem runner).
- A ponte MovelApp continua mudando a situação do pedido sem restrição de origem. O app legado sai no cutover.
- A suíte roda em sqlite: corrida real (locks) foi conferida por leitura do código, não por teste concorrente no Postgres.

## 4. O que este veredito **não** afirma

Não afirma que o sistema é seguro, que está em conformidade com a LGPD, que a VPS está
otimizada, nem que "tudo funciona". Afirma o que cada evidência listada em
[RELATORIO_VALIDACAO_FINAL.md](RELATORIO_VALIDACAO_FINAL.md) e
[MATRIZ_VERIFICACAO.md](MATRIZ_VERIFICACAO.md) sustenta, nos ambientes indicados.

## Índice dos entregáveis

[00_BRIEFING_BANCA](00_BRIEFING_BANCA.md) · [INVENTARIO_APLICACAO](INVENTARIO_APLICACAO.md) ·
[BANCA_R1_SEGURANCA](BANCA_R1_SEGURANCA.md) · [BANCA_R2_USUARIO](BANCA_R2_USUARIO.md) ·
[BANCA_R3_ARQUITETURA](BANCA_R3_ARQUITETURA.md) · [BANCA_R4_QUALIDADE](BANCA_R4_QUALIDADE.md) ·
[BANCA_R5_SRE](BANCA_R5_SRE.md) · [CONTESTACOES_E_CONFLITOS](CONTESTACOES_E_CONFLITOS.md) ·
[ACHADOS_CONSOLIDADOS](ACHADOS_CONSOLIDADOS.md) · [MATRIZ_VERIFICACAO](MATRIZ_VERIFICACAO.md) ·
[RELATORIO_SEGURANCA_LGPD_RECEITA](RELATORIO_SEGURANCA_LGPD_RECEITA.md) ·
[RELATORIO_PERFORMANCE_VPS](RELATORIO_PERFORMANCE_VPS.md) · [OPORTUNIDADES_PRODUTO](OPORTUNIDADES_PRODUTO.md) ·
[PLANO_MESTRE_AUDITORIA](PLANO_MESTRE_AUDITORIA.md) · [RELATORIO_VALIDACAO_FINAL](RELATORIO_VALIDACAO_FINAL.md)
