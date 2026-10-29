# Relatório de validação final — PIE V2

**Commit de partida:** `48b29f72` · **Data:** 2026-10-29 · **Máquina:** Windows 11, PHP 8.3 portátil, Node 24, PostgreSQL 15 portátil (porta 55432)

## 1. Comandos executados e resultados reais

| # | Comando | Quando | Resultado |
|---|---|---|---|
| 1 | `php artisan test --compact` (suíte integral, sqlite) | Baseline, antes das correções | **1951 passaram, 26 pulados, 0 falhas** (992 s) |
| 2 | `phpunit --configuration=phpunit.xml --testdox` (suíte integral, sqlite) | Depois das correções | **1993 testes, 0 falhas, 26 pulados**, 7258 asserções, 6 avisos de depreciação do PHPUnit (preexistentes) |
| 3 | Portão RLS no Postgres local, como `erp_app`, com o filtro novo de `composer.json` (`RlsCoberturaTest`, `GrantsSomenteLeituraTest`, `EscritaDePlataformaSobRlsTest`, `AutomacaoSobRlsTest`, `ChaveDeTenantOperacionalTest`, `AppLoginSobRlsTest`, `DedupClientesFksTest`) com `--fail-on-skipped` | Depois | **26 passaram, 0 pulados** (483 asserções) |
| 4 | `npx vitest run` (SPA) | Depois | **46 arquivos, 171/171** (eram 169: +2 novos) |
| 5 | `npx tsc --noEmit` (SPA) | Depois | exit 0 |
| 6 | `npx tsc --noEmit` (app-entregador) | Antes / depois | TS5098 (não rodava) → **exit 0** |
| 7 | `npx tsc --noEmit` (app-gas-em-casa) | Antes / depois | TS5098 → 2 erros reais (TS2551) → **exit 0** |
| 8 | `npx vitest run` (app-gas-em-casa) | Depois | 17/17 |
| 9 | Arquivos afetados (Pedido, Financeiro, Caixa, Fiscal, Mobile, PIX, Central, Malote…, 39 arquivos) | Depois do lote 2 | 297 passaram |
| 10 | `MobileTest`, `SuperAdminTest`, `AppAuthHardeningTest`, `PieSegurancaRegressaoTest`, `ApiContratoDriftTest` | Depois do R1-08 | 62 passaram |

A diferença entre as execuções 1 e 2 é de **+16 testes**, todos novos de regressão:
`PieSegurancaRegressaoTest` (5), `PieDinheiroRegressaoTest` (10) e
`LookupTest::test_lookup_de_clientes_acha_pelo_telefone_sem_mascara` (1). Nenhum teste
existente foi removido. Cinco asserções do `MobileTest` foram **adaptadas**: elas
dependiam do fallback por `cliente_id`, que era o IDOR. O cliente agora é vinculado ao
usuário do token, como no app real.

**Ocorrência a registrar.** A primeira tentativa da execução 2 (`php artisan test
--compact`) parou: depois de ~4 min o processo deixou de consumir CPU, o log não
avançou e não havia conexão no Postgres local. Encerrei o processo, que era meu, e
rodei pelo `phpunit` direto, que terminou sem falhas. Não identifiquei a causa (o
wrapper do artisan mais saída em buffer é hipótese). Fica registrada como **não
explicada**.

## 2. Prova de que os testes novos detectam a regressão

Com as correções retiradas por `git stash` e os testes novos mantidos:

| Arquivo | Sem a correção | Com a correção |
|---|---|---|
| `PieSegurancaRegressaoTest` | **5 falharam** de 5 | 5 passaram |
| `PieDinheiroRegressaoTest` | **9 falharam** de 10 (o `test_r2_08` passou: o backend já recusava, ver contestações) | 10 passaram |

Os pisos de contagem dos guardiões (PIE-31) **não** foram provados por regressão
plantada; eles só passam com as contagens reais.

## 3. Arquivos alterados

**Backend:** `ClienteAuthService`, `SomenteTokenDoPainel` (novo), `bootstrap/app.php`
(alias `painel` + `trustProxies`), `routes/api.php` (`admin` → `painel`),
`ResolveClienteDoApp`, `ResolveTenant`, `PedidoService`, `PedidoMobileService`,
`EntregaService`, `AppEntregadorController`, `BaixaService`, `CaixaService`,
`FinanceiroService`, `FiscalService`, `LookupController`, `ConexaoDeOwner`,
`AppAuthController` e `SuperAdmin/AuthController` (logout), `composer.json` (filtro do
portão Postgres).

**Testes:** `PieSegurancaRegressaoTest` (novo), `PieDinheiroRegressaoTest` (novo),
`LookupTest`, `MobileTest`, `DedupClientesFksTest`, `FkTenantAwareTest`,
`EnforcementCentralTest`, `RbacContratoTest`.

**SPA:** `lib/format.ts` (+ `decimalBR`, `hojeISO`, `competenciaISO`, `agoraLocal`),
`lib/format.test.ts`, `CentralVendasPage`, `AlcadasPage` e 13 telas com data UTC.

**Apps:** `app-entregador` (`tsconfig.json`, `filaOffline.ts`, `envioResiliente.ts`,
`perfil.tsx`), `app-gas-em-casa` (`tsconfig.json`, `perfil/index.tsx`).

**Deploy:** `deploy/remote-deploy.sh`, `deploy/backup/backup.sh`, `deploy/backup/instalar-cron.sh`.

Nenhuma migration nova, nenhuma rota nova (o manifesto não muda; `ApiContratoDriftTest`
verde), nenhum dado alterado, nenhuma escrita na VPS feita pelo Auditor-Chefe.

## 4. Mudanças de comportamento visíveis a usuários

| Mudança | Quem percebe |
|---|---|
| Token de app em `/api/admin` → 403 | Ninguém legítimo (nenhum app chama `/api/admin`, conferido) |
| Empresa com `ativo=false` → 403 em todo request | Revenda suspensa pelo superadmin (hoje nenhuma: 7/7 ativas na homologação) |
| `PUT /pedidos/{id}` com outra situação → 422 | Nenhuma tela (a SPA usa `/situacao`) |
| Pedido concretizado não troca setor, taxa, condição nem cliente → 422 | Operador: precisa cancelar e relançar |
| Cancelar pedido com NF autorizada → 422 | Operador: cancela a nota primeiro |
| Entregador não move pedido concluído ou cancelado | Correção passa a ser feita pelo painel |
| Baixa de parcela de título agrupado/reparcelado/cancelado → 422 | Operador: recebe pelo título que o substituiu |
| Contraproposta ou teto com texto inválido → mensagem, nada é enviado | Supervisor da Central de Vendas |
| Logout do entregador com pendências → confirmação | Entregador |

## 5. Revisão adversarial das áreas afetadas

Convoquei um revisor independente para tentar quebrar o diff. Ele foi **interrompido
pelo limite de uso da API** antes de entregar o parecer, e o que produziu até ali não
foi aproveitado como prova. Conferi que não deixou arquivo no projeto: o teste
exploratório dele ficou só no scratchpad. O Auditor-Chefe fez a revisão sobre a
mesma lista de perguntas. Por ser o mesmo contexto que escreveu as correções, ela
**não** é independente.

| Pergunta | Verificação | Resultado |
|---|---|---|
| O middleware `painel` barra cliente legítimo? | A SPA usa cookie stateful ou o token `spa` (`['*']`) (`frontend/src/lib/api.ts`, `AuthController:93`); os únicos tokens restritos são os dos apps, que não chamam `/api/admin` | Não barra |
| O e-mail sintético quebra fluxo que lê `user->email`? | `grep` em `app/`: só break-glass, TOTP e ponte MovelApp, nenhum para cliente do app | Não quebra |
| Restaram vínculos `cliente → staff` de antes da correção? | Homologação, só leitura: 2 clientes ligados a e-mail não sintético; os dois usuários têm **0 papéis**, não são suporte e foram criados em 13/10 | Sem resto de exploração |
| Os locks novos podem dar deadlock? | Todos os `lockForUpdate` dos domínios afetados: a baixa trava parcela → título; agrupar, desagrupar e reparcelar travam o título e só **leem** parcelas; o fiscal trava pedido → nota → sequência em todos os caminhos | Sem ciclo encontrado |
| A trava de empresa suspensa vale no Postgres? | Policy `tenant_isolation` de `empresas` filtra por `app.grupo_id`, gravado antes da leitura | Linha visível (dedução pela policy, **não executado** no Pg) |
| **Fila offline: preservar no 401 cria risco novo?** | O interceptor de 401 (`http.ts:19`) faz logout sem passar pela confirmação; outro entregador no mesmo aparelho enviaria a fila do anterior com o próprio token | **Regressão encontrada e corrigida**: cada item guarda o `dono`, e a sincronização só envia os do usuário logado (`tsc` exit 0) |
| `decimalBR("1.000")` | Sem vírgula, o ponto é decimal: vira 1, igual ao comportamento anterior (`Number`). No teto de alçada isso restringe, não libera | Aceito, documentado |

## 7. Operações na VPS em 29/10 (com o OK do dono: "pode fazer o que for preciso")

| Ação | Resultado | Rollback |
|---|---|---|
| Removida de `/root/.ssh/authorized_keys` uma chave desconhecida rotulada `claude-code dubena` (`SHA256:fjaBC4ec…OKHI`), que não corresponde a nenhuma chave deste PC | 14 → 13 linhas; acesso do Claude Code reconferido | `authorized_keys.bak-20261029135959` |
| Primeiro backup (`backup.sh`, `VOLUME_STORAGE=erpnovo-homolog_app_storage`) | Dump de 227 MB (3.876 objetos) em 3 min 08 s; checksums OK | — |
| Ensaio de restore no modo seguro, com a porta presa em `127.0.0.1` e senha aleatória | **RESTORE VERIFICADO**: 443.714 títulos (soma R$ 250.029.904,80), 400.070 pedidos, 241.021 NF, 0 órfãos, 0 duplicatas, 231 tabelas; **RTO 223 s** | Contêiner e volume do teste removidos (só o volume criado às 14:04:58; os soltos de outras aplicações ficaram) |
| Cron `30 4 * * *` (01:30 em Brasília), fora dos horários de backup de outras aplicações (03:15 e 03:20) | Instalado com a marca do `instalar-cron.sh`; as linhas das outras aplicações foram preservadas | `/root/crontab.bak-*` |
| `/etc/logrotate.d/erpnovo-backup` (semanal, 8 rotações) | `logrotate -d` sem erros | Apagar o arquivo |

Achados novos durante a operação, corrigidos no repositório:
- `restore.sh` publicava a cópia da base em **todas as interfaces** com a senha fixa `restore_teste`. O padrão passou a ser `127.0.0.1` e senha aleatória.
- `docker rm -f` deixava o volume anônimo com a cópia inteira da base; trocado por `-fv`.
- O horário padrão do cron (03:15) coincidia com o backup de outra aplicação.

Sem certificado A1 no storage da homologação (0 `.pfx`), o backup de storage ficou
com 4 KB. Isso é coerente com o ambiente, não é falha.

**O push dos commits foi bloqueado pela permissão do Claude Code** (classificado como
deploy em produção). Não fiz nenhum contorno. Por isso os scripts publicados na VPS
ainda são a versão anterior, e o cron funciona com ela porque o nome do volume vai
na própria linha do cron.

## 6. Riscos residuais e bloqueios

Ver [VEREDITO_PRE_DEPLOY.md](VEREDITO_PRE_DEPLOY.md) §3 e [PLANO_MESTRE_AUDITORIA.md](PLANO_MESTRE_AUDITORIA.md) (pendentes).

**Não foi feito:** deploy, push, escrita na VPS, teste contra a SEFAZ ou PSP reais, teste
em navegador ou aparelho real, `composer audit` / `npm audit`.
