# Plano mestre da auditoria — PIE V2

**Commit de partida:** `48b29f72` · **Data:** 2026-10-29 · **Modo:** AUDITORIA + CORREÇÃO

Ordem seguida: vulnerabilidade e isolamento → receita → corrupção de dinheiro,
estoque e fiscal → jornadas essenciais → regressões e portões → operação. Cada
correção tem um teste de aceite que **falhava antes**: os testes foram rodados com a
correção retirada por `git stash` (exceto onde indicado).

## Executado nesta auditoria

| ID | Categoria | Problema comprovado | Evidência | Impacto | Prob. | Prior. | Correção | Teste de aceite | Estado |
|---|---|---|---|---|---|---|---|---|---|
| PIE-01 | Segurança | Tomada de conta pelo cadastro do app | PoC do R1; `ClienteAuthService.php` (`firstOrCreate` por e-mail) | Painel inteiro com o RBAC da vítima | Alta (só exige telefone próprio) | P0 | (1) e-mail do usuário do app sempre sintético; (2) middleware `painel` (`SomenteTokenDoPainel`) recusa token sem `*` em `/api/admin` | `PieSegurancaRegressaoTest::test_r1_01_*` (2) — falham sem a correção | **Feito** |
| PIE-03 | Segurança/LGPD | IDOR do perfil por `cliente_id` | PoC do R1; `ResolveClienteDoApp.php` | PII de qualquer cliente | Média | P0 | Remoção do fallback por `cliente_id` | `test_r1_02_*` | **Feito** |
| PIE-04 | Receita | Empresa suspensa operava | PoC do R1; `SuperAdminService::suspenderEmpresa` | Revenda suspensa usa o sistema | Alta | P0 | `ResolveTenant` devolve 403 quando a empresa ativa tem `ativo=false`; dados reais conferidos (7/7 ativas) | `test_r1_03_*` (bloqueia e reativa) | **Feito** |
| PIE-20 | Segurança | IP do proxy em todo request | `bootstrap/app.php` sem `trustProxies` | Throttle global; lockout cego | Alta | P1 | `trustProxies` nas faixas privadas (web só em 127.0.0.1) | `test_r1_04_*` (forjado à esquerda é ignorado) | **Feito** |
| PIE-05 | Dados | `PUT /pedidos/{id}` contorna a máquina de estados; guardas fora do lock | Prova do R3 | Pedido concluído sem baixa; estoque devolvido ao setor errado | Média | P0 | Lock + recusa de mudança de situação no `atualizar`; concretizado não troca setor, taxa, condição nem cliente; `mudarSituacao(..., $origensPermitidas)` conferido sob lock no app do cliente, na conclusão e no status do entregador | `PieDinheiroRegressaoTest::test_r3_01_*` (2), `test_r3_10_*` | **Feito** |
| PIE-06 | Dinheiro | Baixa de parcela de título morto | Prova do R3 | Recebimento em dobro | Média | P0 | `BaixaService::baixar` trava o título e recusa cancelado/AGRUPADO/REPARCELADO; canal externo registra `Log::error` para conciliação | `test_r3_02_*` (2), `test_r3_04_parcela_*` | **Feito** |
| PIE-07 | Dinheiro | Desagrupar agrupador recebido | Prova do R3 | Dívida reaberta | Baixa | P0 | Lock + recusa com parcela baixada | `test_r3_03_*` | **Feito** |
| PIE-08 | Dinheiro | Reparcelar em dobro | Prova do R3 | Dívida duplicada | Alta (duplo clique) | P0 | Lock, releitura e recusa de estado morto; AGRUPADOR continua reparcelável | `test_r3_04_reparcelar_*` | **Feito** |
| PIE-09 | Fiscal | Duas NF-e autorizadas por pedido | Prova do R3 (fake) | Imposto em dobro; nota autorizada sobrescrita | Média | P0 | `montarDoPedido` e `emitir` travam pedido → nota → sequência e recusam nota EMITIDA/AUTORIZADA do mesmo modelo | `test_r3_05_e_r3_07_*` | **Feito** |
| PIE-10 | Fiscal | Cancelar pedido com NF viva | Prova do R3 | Venda declarada sem lastro | Média | P0 | `mudarSituacao` recusa efeito de cancelamento com NF EMITIDA/AUTORIZADA | `test_r3_05_e_r3_07_*` | **Feito** |
| PIE-12 | Operação | Backup de produção copiava a homologação | `remote-deploy.sh:79-82`, `backup.sh:35-39` | Perda de dados no primeiro incidente de produção | Certa no primeiro deploy | P0 | `remote-deploy.sh` passa contêiner, volume e destino da produção; `backup.sh` lê usuário e banco do próprio contêiner | Revisão + nomes conferidos na VPS. **Não executado**: a produção não existe na VPS | **Feito (não executado)** |
| PIE-13 | Operação | Cron de backup apontava para diretório inexistente | `instalar-cron.sh:13`; `ls` na VPS | Sem backup | Certa | P0 | Default → `releases/homolog-current/deploy` (symlink conferido) | Revisão. **Instalar** depende do dono | **Parcial** |
| PIE-15 | UX | Busca de cliente por telefone | `LookupController` | Atendimento lento na jornada principal | Certa | P1 | `lookups/clientes` também casa os dígitos do telefone (a partir de 4 dígitos, SQL portátil) | `LookupTest::test_lookup_de_clientes_acha_pelo_telefone_sem_mascara` | **Feito** |
| PIE-16 | Dados (app) | Fila offline: chave nova, 401 apaga, logout não limpa | `envioResiliente.ts`, `filaOffline.ts` | Ação duplicada ou perdida | Média | P1 | Reusa a chave da tentativa; 401 para sem descartar; logout pergunta e limpa | `tsc` exit 0. **Sem teste automatizado** (o app não tem runner) | **Feito** |
| PIE-17 | Dinheiro (UX) | "5%" vira `null` | `CentralVendasPage.tsx`, `AlcadasPage.tsx` | Desconto integral aprovado; teto removido | Média | P1 | `decimalBR()` em `lib/format.ts`; entrada inválida para com mensagem | `format.test.ts` (2 novos) | **Feito** |
| PIE-32 | Dados (UX) | Datas UTC | 13 telas | Caixa e malote no dia errado | Certa à noite | P1 | `hojeISO`, `competenciaISO`, `agoraLocal` em `lib/format.ts` | `format.test.ts` | **Feito** |
| PIE-18 | Qualidade | Typecheck dos apps morto | TS5098 | Defeitos escondidos | Certa | P1 | `tsconfig` herda module/resolution do Expo; telefone do perfil | `npx tsc --noEmit` exit 0 nos dois apps | **Feito** |
| PIE-19 | Qualidade | Testes só-Postgres fora do portão | `composer.json:62` | Login sob RLS sem guarda | Certa | P1 | Filtro ampliado; `DedupClientesFksTest` sem `RefreshDatabase` | Postgres local: 26 passaram, 0 pulados | **Feito** |
| PIE-30 | Observabilidade | Fallback mudo do owner | `ConexaoDeOwner.php:41-45` | Incidente sem rastro | Baixa | P2 | `Log::warning` no fallback | Revisão | **Feito** |
| PIE-31 | Qualidade | Guardiões sem piso | 3 testes | Falso verde | Baixa | P2 | `assertGreaterThan` | Passam com as contagens reais; **sem** regressão plantada | **Feito** |
| R2-08 | UX | Botão "Receber -R$ 50" | `BaixaDialog.tsx:26` | Mensagem confusa (o backend já recusava) | — | P3 | Recusa explícita no `CaixaService`; a tela segue mostrando o valor negativo | `test_r2_08_*` | **Parcial** |

## Pendente — ordem recomendada e o que cada item exige

| Prior. | ID | Por que não foi feito agora | O que destrava |
|---|---|---|---|
| ~~P0~~ | PIE-13 | **Feito em 29/10** (ver validação §7). Pendente só a cópia fora do host | Destino externo para `BACKUP_REMOTO` (custo/serviço: decisão do dono) |
| P0 | PIE-11 | Exige **regra comercial**: o PIX pago baixa o título no webhook ou na conclusão? Pedido em dinheiro gera PIX? Quem estorna no cancelamento? | Decisão do dono; depois, correção com teste encadeado |
| P0 antes de ligar NF real | PIE-02 | Corrigir sem respostas SOAP reais gravadas seria trocar um palpite por outro; o driver está desligado, sem dano hoje | Gravar respostas da SEFAZ de homologação com o certificado A1 e escrever o teste do driver com elas |
| P1 | PIE-14 | A regra do dono é "não desligar o login por senha da VPS" | Decisão do dono: fail2ban + ufw não contradizem a regra e podem vir primeiro |
| P1 | PIE-21, PIE-22 | Ligar licença ou Firebase real muda o comportamento para os usuários da homologação | Decisão de ambiente |
| P1 | PIE-28 | Trocar `paginate` por `simplePaginate` ou contagem em cache muda o contrato da paginação da SPA | Ajustar SPA + backend juntos, medindo antes e depois como `erp_app` |
| P1 | PIE-27 | Mexe no `entrypoint.sh` da imagem, que roda em todos os contêineres | Mudança isolada + deploy observado |
| P2 | PIE-23, 24, 25, 26 | Refatorações de transação e estados novos | Lote próprio, com testes de falha injetada |
| P2 | PIE-33, 34, 35 | UX sem risco de dado | Lote de UX |
| P2 | PIE-29, 37 | Infraestrutura compartilhada | Janela e aprovação |
