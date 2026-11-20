# O que falta, e quem faz — plano de agentes

Data: 2026-11-20. Coordenador: sessão principal (Claude).

## 1. Onde estamos, em duas frases

O plano formal (`PLANO_TRANSFORMACAO_SAAS.md`, F0–F10, 106 tarefas) tem o
**código esgotado** desde 01/09 (`ESTADO_DAS_106_TAREFAS.md`): o que sobra nele é
operação — ensaio com dados reais, homologação fiscal, segundo cliente.

Mas as três rodadas da auditoria PIE (out–nov) mediram o produto rodando e
acharam o que o plano não pedia explicitamente: **não existe como criar a revenda
nº 2 pelo sistema**. O isolamento entre revendas se sustenta (617 rotas, zero
vazamento); o que falta é o caminho de produto — provisionar, catálogo inicial,
licença por tenant, ciclo de vida da assinatura — e as regras de negócio dos apps
que esperam decisão do dono.

## 2. O que já está feito

| Bloco | Estado | Evidência |
|---|---|---|
| F0–F7, F9 (código) | fechado | `F*_FECHAMENTO.md`, `F*_PROGRESSO.md`, `ESTADO_DAS_106_TAREFAS.md` |
| Auditoria PIE rodadas 1–3 | painel **aprovado com ressalvas** | `docs/pie/rodada3/CONSOLIDADO_RODADA3.md` |
| Suíte | 2105 passed / 0 falhas; Pg/RLS 33/33; SPA 181; apps 44/44; E2E 34 | 18/11 |
| Pós-rodada 3 (18/11) | venda de missão idempotente; axios/Laravel/Symfony sem aviso; lock do consumidor | commits `7feed6b9` … `273bfd34` (**sem push**) |

## 3. O que falta — por natureza

### 3.1 Código que não depende de decisão (ondas 1 e 2)

| ID | O quê |
|---|---|
| P2-A-01 | Criar revenda pelo superadmin (tenant + empresa + dono + papéis + plano de contas + assinatura inicial) |
| P2-A-07, P2-D-13, R3-A-11, R3-G-22 | Catálogo inicial (preço **zero**), condições de pagamento e situações padrão, checklist "pronto para vender" |
| P2-A-06 | Assinatura do **tenant**, não da empresa (D2 do plano); filial herda |
| P2-A-05 (1, 2, 4) | Ciclo de vida trial → ativa → em atraso (carência) → suspensa → cancelada; job diário; tela "Meu plano". **Sem** fatura (ver 3.2) |
| P2-A-03 (resto), decisão 8 | Porta de licença em `marketplace`, `app_consumidor`, `app_entregador`, `tempo_real`; guardião: toda rota do manifesto tem decisão de licença |
| P2-A-04 (contrato) | Teste menu × `RecursoPorRota` (o menu não promete o que a licença nega) |
| R3-F-06 | Subrecurso de pai invisível responde 404, não 200 vazio |
| P2-E-13 | RLS das filhas `checklist_perguntas`/`sorteio_numeros` por migration |
| P2-E-10 | Trilha dos atos da plataforma (migração, cidades, CSV de descartes) |
| PIE-26 | Job de geocodificação despachado depois do commit |
| R3-G (sugestão) | Registradores que engolem falha passam a `report($e)` |
| E2E | Projeto celular (Pixel 7) e axe completo, que estão escritos e nunca rodaram |

### 3.2 Código que espera decisão do dono (onda 3)

Cada linha tem a **recomendação** já escrita nas rodadas; com o "ok" do dono o
agente implementa a recomendação.

| Decisão (§5 do consolidado R3) | Recomendação registrada | Agente |
|---|---|---|
| 1. Pagamento na entrega (P2-C-03, C-02) | conclusão registra forma, recebido, troco; resgata Vale Gás na mesma transação | C1 |
| 2. "Fechar o dia" (P2-C-13) | tela com entregas abertas, ações sem sinal, dinheiro esperado por forma, km final | C1 |
| 3. Exclusão de conta (P2-B-05) | anonimizar; manter CPF/nome só onde há documento fiscal | C2 (exige validação jurídica) |
| 4. Formas de pagamento no app (P2-B-06) | campo `disponivel_app`, desligado por padrão para a prazo | C2 |
| 5. Termos por revenda (P2-B-07) | texto por tenant servido pelo ERP; "Aceitar" bloqueado até carregar | C2 |
| 6. Cancelar com entregador a caminho | só confirmar, sem esconder | C2 |
| Faturamento da plataforma (P2-A-05 parte 3) | fatura mensal pelo domínio `Cobranca` com a conta da plataforma; escolher PSP | C3 |
| 9. Papéis da plataforma e nome comercial | — | C3 |
| 10. Contraste da marca (`#FF6200`) | cor de texto/fundo por tenant com piso WCAG AA | C3 |
| 11. RH: permissões de saúde e remuneração | `colaborador.saude`/`.remuneracao` para o Administrador, não para o Gerente | C3 |
| Modelo dos apps (P2-A-09) | marketplace único × white-label por revenda | C2 (decide pacote, termos, LGPD) |
| PABX multi-revenda (P2-E-07) | credencial por empresa em `IntegrationAccount`; tenant pelo token | C4 |

### 3.3 Não é código (fica com o dono / operação)

- **Push** dos commits locais (dispara deploy).
- **Rotacionar a chave Google** versionada (R3-F-09).
- **Jornada dos apps em aparelho** (Maestro ou roteiro manual): nesta máquina não pode haver emulador.
- **F5-09** homologação fiscal com contador e SEFAZ; **F8** ensaio da conversão com dados reais e quatro aprovações; **F7-12** runbook com RTO/RPO e responsáveis; **F10** piloto com uma segunda revenda real; **F9-07** remover pontes quando `ponte:uso` mostrar zero em produção.
- **F7-03 (parte)**: área de staging do ETL — decisão de arquitetura.

## 4. Os agentes

Regras comuns a todos (repetidas no prompt de cada um):

- Ler `CLAUDE.md` inteiro antes; português do Brasil; comentário explica o porquê.
- **Não commitar** — o coordenador revisa e faz o commit por fase. Não fazer push. Não tocar `ctrl-web/`. Nada de escrita na VPS. Nada de emulador, adb, Gradle ou build de APK.
- Cada correção com teste que **falha sem ela** (provar removendo a correção e restaurando).
- Postgres local :55432 é compartilhado: cada agente usa bancos com o próprio prefixo; não alterar a senha de `erp_app`.
- Relatório final em `docs/pie/rodada4/<AGENTE>.md`.

### Onda 1 — lançada em 20/11

| Agente | Escopo | Arquivos que são dele | Não pode tocar |
|---|---|---|---|
| **A1 — Provisionamento de revenda** | P2-A-01, P2-A-07, P2-D-13, R3-A-11, R3-G-22 | `Domain/Saas/Provisionamento*` (novo), `CatalogoInicialRevenda` (novo), `SuperAdmin*Controller` (rota nova), `frontend/src/features/superadmin/*`, checklist no Início, `routes/api.php` (único que roda `api:manifest`) | modelo `Assinatura` (é da B1): cria a assinatura inicial pelo serviço que já existe |
| **A2 — Endurecimento residual** | R3-F-06, P2-E-13, P2-E-10, PIE-26, `report($e)` nos registradores | controllers dos subrecursos, migration nova de RLS, `SuperAdminMigracaoController`/cidades (só trilha), registradores de trilha | rotas (não cria nem renomeia), `features/superadmin` |
| **A3 — E2E celular e acessibilidade** | rodar o projeto Pixel 7 e o axe completo do `erp-novo/e2e`; corrigir o que for defeito de tela | `erp-novo/e2e/**`; correções pontuais na SPA fora de `features/superadmin` | backend, exceto para relatar |

### Onda 2 — depois da A1

| Agente | Escopo |
|---|---|
| **B1 — Licença e assinatura** | P2-A-06 (assinatura do tenant, migration reversível que leva as atuais para o tenant da empresa), P2-A-05 partes 1, 2 e 4 (máquina de estados, job diário, "Meu plano", 402 explicativo; nota fiscal já iniciada nunca é bloqueada), P2-A-03 resto (portas dos apps, marketplace, tempo real), guardião de licença por rota no manifesto, contrato menu × mapa (P2-A-04). Adapta o provisionamento da A1 para criar a assinatura do tenant |

### Onda 3 — cada uma lançada quando o dono responder a decisão

| Agente | Escopo |
|---|---|
| **C1 — Entregador: dinheiro na rua** | decisões 1 e 2 |
| **C2 — Consumidor: LGPD, pagamento e termos** | decisões 3, 4, 5, 6 e o modelo dos apps |
| **C3 — Plataforma comercial** | faturamento, papéis da plataforma, contraste, permissões de RH |
| **C4 — Telefonia multi-revenda** | P2-E-07 |

### Fechamento de cada onda (coordenador)

1. Ler o relatório e o diff de cada agente; rejeitar o que não tiver prova.
2. Suíte PHP completa, portão Pg/RLS, vitest da SPA e dos apps, `tsc`, `pie-costura`.
3. Commit por agente; atualizar `CLAUDE.md` (números) e este documento.
