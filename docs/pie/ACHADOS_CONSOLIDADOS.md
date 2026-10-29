# Achados consolidados — Auditor-Chefe

**Commit auditado:** `48b29f72` · **Data:** 2026-10-29 · **Fontes:** `BANCA_R1..R5_*.md` + reverificação ([CONTESTACOES_E_CONFLITOS.md](CONTESTACOES_E_CONFLITOS.md))

Consolidado por causa raiz, sem duplicatas. Cada linha aponta para o achado original
no parecer da banca, onde estão reprodução, evidência e arquivo:linha completos.
**Situação** indica o que foi feito nesta execução. O detalhe da correção e do teste
de aceite está no [PLANO_MESTRE_AUDITORIA.md](PLANO_MESTRE_AUDITORIA.md).

Comprovação: **C** = CONFIRMADO · **FI** = FORTE INDÍCIO · **H** = HIPÓTESE.

## Críticos e altos

| ID | Origem | Problema | Sev. | Comp. | Situação |
|---|---|---|---|---|---|
| PIE-01 | R1-01 | **Tomada de conta pelo cadastro do app:** informar o e-mail de um administrador devolvia o usuário dele e um token que abria `/api/admin`. Basta um telefone próprio verificado; não é preciso senha. | CRÍTICA | C (PoC) | **CORRIGIDO** (2 camadas) |
| PIE-02 | R3-06 | O driver real da SEFAZ lê `cStat` na raiz do envelope SOAP: toda autorização, cancelamento, inutilização e CC-e seria gravada como REJEITADA. O driver hoje está **desligado** (homologação usa `fake`). | CRÍTICA (latente) | FI | PENDENTE — bloqueia ligar `FISCAL_DRIVER=nfephp` |
| PIE-03 | R1-02 | IDOR intra-tenant: qualquer autenticado da empresa lia CPF, e-mail e telefones de qualquer cliente por `GET /app/v1/perfil?cliente_id=`. | ALTA | C (PoC) | **CORRIGIDO** |
| PIE-04 | R1-03 | Empresa suspensa no superadmin continuava logando e operando. | ALTA | C (PoC) | **CORRIGIDO** |
| PIE-05 | R3-01, R3-10 | Máquina de estados do pedido contornável: o `PUT` gravava a situação sem baixa nem título, alterava setor e taxa de pedido concretizado, e as guardas do app ficavam fora do lock. | ALTA | C (teste) | **CORRIGIDO** (admin, app do cliente e entregador); a ponte MovelApp ficou sem restrição de origem |
| PIE-06 | R3-02 | Baixa aceitava parcela de título AGRUPADO, REPARCELADO ou cancelado: o caixa recebia 200 por uma dívida de 100. Vale para caixa, PIX e CNAB. | ALTA | C (teste) | **CORRIGIDO** na porta única |
| PIE-07 | R3-03 | Desagrupar com o agrupador já baixado reabria a dívida de quem pagou. | ALTA | C (teste) | **CORRIGIDO** |
| PIE-08 | R3-04 | Reparcelar duas vezes duplicava a dívida. | ALTA | C (teste) | **CORRIGIDO** |
| PIE-09 | R3-05 | Duas NF-e AUTORIZADAS para o mesmo pedido; dois "transmitir" simultâneos sobrescreviam a nota autorizada. | ALTA | C (teste) | **CORRIGIDO** (lock + recusa) |
| PIE-10 | R3-07 | Cancelar pedido com NF autorizada devolvia estoque e estornava o financeiro, e a nota continuava AUTORIZADA. | ALTA | C (teste) | **CORRIGIDO** (exige cancelar a nota antes) |
| PIE-11 | R3-08, R2-02, R2-13, R3-15 | Pagamento online do app desligado do título: o PIX pago não baixa o título do pedido, permite pagar duas vezes, não estorna no cancelamento, o app gera PIX até para pedido em dinheiro, e as rotas do cliente não usam idempotência. | ALTA | C (estático) | PENDENTE — exige decisão de regra comercial |
| PIE-12 | R5-01 | O "backup obrigatório" do deploy de produção copiava o banco da **homologação**. | ALTA | C | **CORRIGIDO** (script); a execução real na produção não foi testada (a produção não existe na VPS) |
| PIE-13 | R5-02 | **Não há backup** do banco de homologação, que guarda dados reais (92 mil clientes). Não há cron, e o `instalar-cron.sh` apontava para um diretório inexistente. O restore nunca foi ensaiado. | ALTA | C | **CORRIGIDO em 29/10** com o OK do dono: cron diário às 04:30 UTC, primeiro dump (227 MB) e restore ensaiado com sucesso (RTO 223 s). Resta: **cópia só neste host** (`BACKUP_REMOTO` não definido) |
| PIE-14 | R1-05, R5-09 | SSH root por senha, ufw e fail2ban inativos, CI entra como root com `sshpass`. | ALTA | C | PENDENTE — decisão do dono (a regra registrada é "não desligar o login por senha") |
| PIE-15 | R2-01 | O atendente não acha o cliente pelo telefone, embora o campo diga "nome ou telefone". | ALTA | C | **CORRIGIDO** no lookup do Novo pedido; a listagem de clientes ainda não busca por telefone |
| PIE-16 | R2-03, R2-04 | Fila offline do entregador: o reenvio usava chave nova (duplicava ações), um 401 apagava todas as pendências e o logout não limpava a fila. | ALTA | C | **CORRIGIDO** (provado só por typecheck: o app não tem test runner) |
| PIE-17 | R2-07 | "5%", "R$ 5" ou "1.000,00" viravam `null`: a contraproposta aprovava o desconto inteiro e o teto de alçada sumia. | ALTA | C | **CORRIGIDO** |
| PIE-18 | R4-01, R4-02 | Typecheck dos apps não rodava (TS5098), e o perfil do app nunca mostrava o telefone. | ALTA | C | **CORRIGIDO** (`tsc` exit 0 nos dois apps); os apps continuam fora do CI |
| PIE-19 | R4-03, R4-04 | 6 testes só-Postgres fora de qualquer portão; o guardião da dedup nunca tinha rodado. | ALTA | C | **CORRIGIDO** (26/26 no Postgres local, 0 pulados) |

## Médios

| ID | Origem | Problema | Comp. | Situação |
|---|---|---|---|---|
| PIE-20 | R1-04 | Sem `trustProxies`: o throttle de login (10/min por IP) era global e o eixo-IP do lockout não distinguia ninguém. | C | **CORRIGIDO** |
| PIE-21 | R1-06, R4-07 | Licença SaaS desligada (`SAAS_ENFORCE_LICENCA` ausente): módulo não contratado passa. | C | PENDENTE — decisão comercial e de ambiente |
| PIE-22 | R1-07 | Firebase `fake` aceito em homologação, que tem PII real: qualquer um entra como qualquer telefone. | C | PENDENTE — decisão de ambiente |
| PIE-23 | R3-09 | Chamada à SEFAZ e ao gateway de cartão dentro de `DB::transaction`. | C (estático) | PENDENTE — refatoração em fases |
| PIE-24 | R3-11 | Webhook PIX que chega depois da expiração local recebe 422 sem rastro. | FI | PENDENTE — exige estado novo |
| PIE-25 | R3-12 | Numeração de remessa CNAB e de CC-e por `max+1`, sem lock. | C (estático) | PENDENTE |
| PIE-26 | R3-14 | Job de geocodificação despachado dentro da transação (redis `after_commit=false`). | FI | PENDENTE |
| PIE-27 | R5-03 | Corrida no `bootstrap/cache` compartilhado: 45 erros `routes-v7.php` e 1 ParseError nos logs. | C | PENDENTE — mexe no entrypoint da imagem |
| PIE-28 | R5-04 | `paginate()` conta a tabela inteira sob RLS: pedidos 3,5 s, parcelas 7,8 s como `erp_app`. | C (medido) | PENDENTE |
| PIE-29 | R5-05..08 | Postgres no default e sem observabilidade; contêineres sem limite de memória; sem healthcheck; 135 `failed_jobs` sem alerta. | C | PENDENTE — infraestrutura |
| PIE-30 | R4-09 | Fallback mudo do `ConexaoDeOwner`. | C | **CORRIGIDO** (log de alerta) |
| PIE-31 | R4-05 | Três guardiões sem piso de contagem. | C | **CORRIGIDO** (os pisos não foram provados por regressão plantada) |
| PIE-32 | R2-05, R2-06 | Caixa abria e fechava em UTC (3 h adiantado); "hoje" virava amanhã depois das 21h em 13 telas. | C | **CORRIGIDO** |
| PIE-33 | R2-09, R2-10, R2-12, R2-14, R2-15 | Novo pedido descarta item inválido e o diálogo sem perguntar; a SPA não trata 401; o carrinho finaliza com total R$ 0; o perfil do app tem campos que não salvam. | C | PENDENTE |
| PIE-34 | R2-11 | Mensagens de validação em inglês ou como chave crua (não existe `lang/`). | C | PENDENTE |
| PIE-35 | R2-17 | CPF/CNPJ sem máscara nem dígito verificador no cadastro admin. | C | PENDENTE |
| PIE-36 | R4-06, R4-08 | Correções recentes sem teste de regressão; não há teste encadeado pedido → entrega → caixa. | C | PARCIAL — o login sob RLS entrou no portão |
| PIE-37 | R5-10 | Sem worker `queue-longo` na homologação; sem trava técnica contra push durante o cutover. | FI | PENDENTE |

## Baixos e hipóteses

R2-08 (rebaixado: só UX), R1-08, R3-13, R3-16 (risco aceito), R2-16, R2-18 (H),
R2-19, R2-20 (H), R4-10, R4-11 (H), R5-11, R5-12 — registrados nos pareceres, sem
ação nesta execução, salvo a mensagem explícita do R2-08 no `CaixaService`.
