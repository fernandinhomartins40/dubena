# Briefing comum — Rodada 3: varredura, correção e validação em runtime

**Data:** 2026-10-31 · **Commit de partida:** `9fcfde5a` (main) · **Protocolo:** `PROTOCOLO_VARREDURA_PONTA_A_PONTA.md` (raiz) — leitura **obrigatória e integral**, junto com o `CLAUDE.md`.
**Antecedentes:** `docs/pie/rodada2/CONSOLIDADO_RODADA2.md` (o que já foi achado, corrigido e o que está pendente — não redescreva; cite o ID).

Esta rodada **corrige**, não só audita. Cada agente é dono de uma área, executa as jornadas dela no runtime real, reproduz, corrige pelo ciclo do protocolo §8 e prova.

## 1. Infraestrutura pronta (não reconstrua)

- **Banco-base** `pie_r3_base` no Postgres local (porta 55432): migrado no commit atual, demonstração completa (2 empresas no grupo 1, ~490 pedidos, clientes, financeiro, CRM), titularidade SaaS importada, pós-carga aplicado, licença `legacy-full`, usuários por perfil e superadmin. Senhas no `erp-novo/e2e/README.md`.
- **Seu banco:** `create database pie_r3_<sua letra> template pie_r3_base`. **Nunca** escreva no `pie_r3_base` nem no `erp_teste` (o portão RLS usa este último; pode rodar testes Pg nele, mas não grave dado manual).
  O `template` falha se alguém estiver conectado ao `pie_r3_base`: tente de novo em alguns segundos e não deixe conexões abertas nele.
- **Harness E2E:** `erp-novo/e2e/` (Playwright 1.56.1 + axe, `npm install` já feito). Siga o README: fixtures `entrar(perfil)`, `evidencia`, `acessibilidade`, `semErroGrave`, `consultar(sql)` para prova de persistência. O smoke passou contra o banco-base.
- **Portas por agente** (API / SPA): A 8211/5211 · B 8212/5212 · C 8213/5213 · D 8214/5214 · E 8215/5215 · F 8216/5216 · G 8217/5217.
- **Costura:** `node scripts/pie-costura.mjs` (617 rotas, 451 chamadas, 0 sem rota, 89 rotas sem consumidor visível).
- PHP fora do PATH (ver README do e2e). Suíte em sqlite: rode **só** arquivos ou `--filter` — a suíte inteira roda no fim, pelo coordenador.

## 2. Regras de convivência (sete agentes no MESMO working tree)

1. **Só edite arquivos da sua área** (tabela §3). Defeito fora dela: registre no seu relatório, na seção "Para outros donos", com arquivo:linha e reprodução. O coordenador repassa.
2. **Git proibido para escrita:** nada de `git add`, `commit`, `stash`, `checkout`, `reset`, `restore`, `clean`. Commit é do coordenador.
3. **Provar que o teste falha sem a correção** sem `git stash`: copie o arquivo corrigido para o seu scratchpad, grave a versão do commit (`git show HEAD:<caminho> > <caminho>`), rode o teste, **restaure a sua cópia** e confira com `git diff <caminho>`. Só faça isso em arquivo da sua área.
4. **Arquivos compartilhados:**
   - `routes/api.php`: só nas linhas do seu módulo. Mexeu em rota → `php artisan api:manifest` (é determinístico, quem rodar por último grava o estado inteiro).
   - `composer.json`, `CLAUDE.md`, `PROTOCOLO_*`: **não edite**. Teste Postgres novo → informe o nome da classe; o coordenador põe no portão.
   - `@/components/ui` e `@/lib/format.ts`: só mudança **aditiva** (prop nova, função nova), sem mudar comportamento existente.
   - Migrations: nome com o seu prefixo (`2026_10_31_1<letra>…`), não destrutivas.
5. **Seus processos:** API, Vite, navegador, Postgres de teste — encerre tudo o que você subiu, ao final. Não mate processo de outro agente (filtre pela sua porta).
6. **Proibido:** emulador, `adb`, Gradle, build de APK, push, deploy, escrita na VPS, dado real, `ctrl-web/`.
7. **Decisão de negócio** (protocolo §9): não decida. Implemente só o que é independente da decisão e registre a pergunta com a sua recomendação.

## 3. Áreas

| Agente | Área / perfis | É dono de |
|---|---|---|
| **A — Atendimento** | Operador/atendente, gerente comercial | `frontend/src/features/{pedidos,clientes,central-vendas,central}`, componente de busca de cliente; `app/Domain/{Pedido,Cliente,Venda,Identidade}`; controllers `Pedido*`, `Cliente*`, `CentralVendas*`, `Central*`, `Lookup*`, `AlcadaDesconto*` |
| **B — Caixa e financeiro** | Caixa, gerente financeiro | `features/{financeiro,pagamentos}`; `Domain/{Caixa,Financeiro,Cobranca,Pagamento}`; controllers `Caixa*`, `Financeiro*`, `Cheque*`, `Malote*`, `Boleto*`, `Pix*` (admin), `Cartao*`, `Cobranca*` |
| **C — Plataforma e acessos** | Dono/admin, superadmin, onboarding | `features/{acessos,superadmin,empresas,configuracoes,auth,seguranca}`, layout/menu, `lib/api.ts`, `lang/` (novo); `Domain/{Acesso,Saas,Tenant,Seguranca,Empresa}`; controllers `SuperAdmin/*`, `Usuario*`, `Papel*`, `Seguranca*`, `Empresa*`, `ConfigGlobal*`, `AuthController`; `RecursoPorRota`, `PermissaoCatalogo`, `RbacSeeder`, `DeployAdminSeeder` |
| **D — App do entregador** | Entregador | `app-entregador/**`, `mobile-shared/**` (inclui a sessão dos DOIS apps); `Api/Mobile/{AppEntregador,AppMissao,AppFiscal,AppSolicitacao}Controller`, `Domain/Mobile/{EntregaService,…entregador}` |
| **E — App do consumidor** | Consumidor | `app-gas-em-casa/**` (exceto a sessão/token, que vem do `mobile-shared` do D); `Api/Mobile/{AppPedido,AppPerfil,AppAuth,AppCliente,Marketplace}Controller`, `Domain/Mobile/{PedidoMobileService,EnderecoMobileService,…cliente}` |
| **F — Segurança, LGPD e demais módulos** | Anônimo/atacante, matriz entre revendas | testes de autorização novos; correção nos módulos sem dono acima: `Rh`, `Monitora`, `Frota`, `Geografico`, `Crm`/`Gestao`, `Relatorio`, `Estoque`, `Fiscal`, `Contrato`/comodato, `Produto`, `Telefonia`, `Satelite`, `Auditoria` (domínio, controllers e features correspondentes) |
| **G — Inventário cruzado e produto** | Todos (medição) | **não corrige código**; escreve só em `docs/pie/rodada3/` e em specs de medição em `erp-novo/e2e/produto/` |

## 4. Entregável de cada agente

`docs/pie/rodada3/<LETRA>_<AREA>.md` com:
1. **Ambiente** usado (banco, portas, comandos reais).
2. **Matriz perfil × jornada** da sua área: estado (VERIFICADA / PARCIAL / FALHA REPRODUZIDA / IMPLEMENTADA NÃO VERIFICADA / BLOQUEADA) e o elo do protocolo §2 que falta.
3. **Achados** (ID `R3-<LETRA>-NN`): formato do protocolo §10 (arquivo:linha, reprodução, esperado × observado, evidência real, severidade, estado).
4. **Correções:** arquivos, teste de aceite, **saída com e sem a correção**, jornada E2E que passou depois.
5. **Para outros donos** e **Decisões pendentes** (com a sua recomendação).
6. **Não testado** e por quê.

Mensagem final ao coordenador (até 30 linhas): os achados mais graves, o que corrigiu com prova, arquivos alterados, testes Postgres novos para o portão, e pendências.
