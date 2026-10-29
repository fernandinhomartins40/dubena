# Relatório de segurança, LGPD e proteção de receita

**Commit:** `48b29f72` + correções · **Data:** 2026-10-29 · **Base:** [BANCA_R1_SEGURANCA.md](BANCA_R1_SEGURANCA.md), reverificação do Auditor-Chefe

> Este relatório **reduz e documenta risco**. Não afirma que o sistema é seguro, nem
> que está em conformidade com a LGPD. Conformidade jurídica exige o encarregado e
> avaliação organizacional, que estão fora do alcance de uma inspeção técnica.

## 1. Cenários negativos executados

Todos com fixtures isoladas em sqlite e PoCs do R1 convertidas em regressão. Nenhum
tráfego de ataque foi enviado à homologação: a única tentativa foi bloqueada pela
permissão e não executada.

| Cenário | Antes | Depois | Prova |
|---|---|---|---|
| Cadastro do app com o e-mail de um admin | Devolvia o usuário do admin; o token abria `/api/admin` (200) | Usuário novo, e-mail sintético; o token do app em `/api/admin` dá 403 | `test_r1_01_cadastro_*` |
| Token de app (`role:cliente`) de quem tem RBAC no painel | `/api/admin/clientes` 200 | 403; o token do painel (`*`) continua 200 | `test_r1_01_token_*` |
| Staff sem permissão lê perfil de cliente por `cliente_id` | 200 com CPF | 422, sem CPF no corpo | `test_r1_02_*` |
| Empresa suspensa usa token emitido antes da suspensão | 200 | 403; reativada, volta a 200 | `test_r1_03_*` |
| `X-Forwarded-For` forjado pelo cliente | Irrelevante (todo IP era o do proxy) | Valor forjado à esquerda é ignorado; conexão direta não confia no cabeçalho | `test_r1_04_*` |
| Isolamento entre tenants sob RLS real | — | 26/26 no Postgres local, inclusive `AppLoginSobRlsTest` | Portão RLS |

Os cinco testes de segurança **falham** quando as correções são retiradas
(`git stash`): saída registrada em [RELATORIO_VALIDACAO_FINAL.md](RELATORIO_VALIDACAO_FINAL.md).

## 2. Controles verificados como sólidos (R1, conferidos por leitura)

- Webhook PIX: HMAC por empresa sobre o corpo cru, vínculo de valor e idempotência com `lockForUpdate`.
- Policies RLS `app_tenant_can_read/operate` por associação e concessão aprovada; `FORCE ROW LEVEL SECURITY` nas tabelas amostradas.
- Login do app pela conexão de owner (commits `d1b2878b`/`b2f151bb`): devolve só ids da empresa pedida; o cliente é relido sob RLS.
- Não foi encontrado SQL dinâmico explorável, nem `dangerouslySetInnerHTML`, nem segredo vivo versionado nos arquivos em escopo.

## 3. Proteção de receita

Fluxo mapeado: identidade → tenant → plano/licença → pagamento → autorização → contabilização.

| Ponto | Situação |
|---|---|
| Empresa suspensa | **Corrigido**: bloqueia no `ResolveTenant` (painel, apps, pontes) |
| Módulo não contratado | **Aberto em homologação**: `SAAS_ENFORCE_LICENCA` ausente, o padrão é `false`. O mecanismo (`LicencaService`, `licenca.rota`) está testado com a flag ligada. **Precisa estar ligado em produção** (PIE-21) |
| Pagamento online do app | **Aberto**: PIX/cartão sem vínculo com o título, sem trava de pagamento duplo, sem estorno no cancelamento (PIE-11) |
| Recebimento em dobro por título agrupado/reparcelado | **Corrigido** (PIE-06/07/08) |
| Preço calculado no cliente | Não reproduzido: o app ignora `preco_unitario` (anti-fraude F3c, conferido por leitura) |

## 4. LGPD — observações técnicas

| Tema | Observação | Ação |
|---|---|---|
| Acesso indevido a PII | IDOR do perfil (CPF, nascimento, telefones) | **Corrigido** |
| Ambiente de homologação com PII real | 92 mil clientes reais **e** login do app por verificador `fake`, em que qualquer telefone entra (PIE-22). Combinação de alto risco | Decisão do dono: Firebase real na homologação ou anonimizar a base |
| Backup | Não existe backup da base com PII real (PIE-13). Quando existir, precisa de retenção e local de guarda definidos | Depende do dono |
| Logs | Um log novo de conciliação (`BaixaService`) grava só ids, valor e origem, sem dado pessoal | — |
| Retenção de posição GPS | Há expurgo agendado (`monitora:expurgar-posicoes`) | Prazo é decisão do controlador |
| Direitos do titular, base legal, encarregado | Fora do alcance técnico | **Questão para o responsável jurídico** |

## 5. Infraestrutura (riscos não corrigidos)

SSH root por senha, ufw e fail2ban inativos, CI entrando como root com `sshpass`
(PIE-14). A regra registrada pelo dono é não desligar o login por senha; fail2ban e
ufw **não** contradizem essa regra e são o passo de menor atrito. Aguarda decisão.

## 6. Não verificado

Dependências (`composer audit` / `npm audit` não rodados), DAST contra a
homologação, e Reverb com autorização de canal sob carga.
