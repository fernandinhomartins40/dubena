# A3 — E2E no celular (Pixel 7) e axe completo

Rodada 4, 20/11/2026. Escopo em `docs/01-vigente/implementacao-saas/PLANO_AGENTES_RESTANTE.md` (onda 1, A3).

## 1. Ambiente

| Item | Valor |
|---|---|
| Banco | `pie_a3_e2e`, clone do `pie_r3_base`, **recriado antes de cada execução** (o 2FA do `sa@` e a conta "Sem abertura" só existem no banco limpo). Migrations aplicadas como owner, incluindo as da A2 (`2026_11_20_000100`) e da B1 (`2026_11_20_000200`) |
| API | porta 8000, como `erp_app`, `SAAS_ENFORCE_LICENCA` e `SAAS_ENFORCE_TENANT_ENVELOPE` ligados |
| SPA | Vite na 5173 (proxy fixo para :8000) |
| Navegador | Chromium **1243** (`PIE_CHROMIUM`). O 1248 citado na rodada 3 não está mais nesta máquina. O padrão do `playwright.config.ts` aponta para ele, por isso a variável é obrigatória aqui |
| Projetos | `desktop` (1366×768) e `celular` (`devices['Pixel 7']`: 412×915, `isMobile`, toque) |

### Mudança de ambiente que decidiu metade das falhas

`php artisan serve` no Windows roda **sem opcache**: o `php.ini` do winget não carrega a extensão. Cada requisição levava de 1 a 2,5 s, `/api/me` 2,5 s. Uma tela dispara 4 ou 5 chamadas, e o servidor atende uma por vez. Resultado: o login passava dos 10 s do `expect` e a tabela ficava no esqueleto.

A API passou a subir com o mesmo roteador do `artisan serve` e opcache ligado:

```bash
cd erp-novo/public
php -d zend_extension=opcache -d opcache.enable_cli=1 -S 127.0.0.1:8000 \
  ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

`/api/me` caiu de 2,5 s para 0,25 s. Mesma aplicação, mesmo `.env` por variável: só o PHP deixou de recompilar o framework a cada requisição. Produção roda com opcache, então este é o ambiente mais fiel.

Mesmo assim, o **primeiro** `/api/me` e o primeiro login de um servidor recém-subido levam de 13 a 18 s (cache frio). O roteiro de execução faz dois logins de aquecimento por `curl` antes do Playwright. Isso fica no harness: spec e prazo não mudaram.

Sugestão para o README do E2E, não aplicada: documentar a subida com opcache e o aquecimento.

## 2. Execuções

| # | Projeto | Banco | Passed | Failed | Skipped | Observação |
|---|---|---|---|---|---|---|
| 1 | celular | clone novo, `artisan serve` sem opcache | 21 | 8 | 7 | 17,4 min. 5 falhas de latência, 3 de spec (menu em gaveta) |
| 2 | celular | clone reusado, opcache | 28 | 1 | 7 | 6,7 min. Só sobrou a gaveta no 2º contexto (spec) |
| 3 | celular | clone limpo, opcache, servidor frio | 27 | 2 | 7 | as 2 primeiras jornadas: login frio de 13 s (ver §1) |
| 4 | desktop | clone limpo | **34** | 0 | 2 | regressão dos ajustes: igual à rodada 3 |
| 5 | celular | clone limpo + aquecimento | **29** | **0** | 7 | 5,1 min |
| 6 | desktop | clone limpo + aquecimento | **34** | **0** | 2 | 10,1 min |
| 7 | celular | clone limpo, depois de `ClientesListPage`/`data-table` | 19 | 11 | 6 | **inválida**: a B1 alterava o backend durante a execução (ver §6) |
| 8 | desktop | clone limpo, depois de `ClientesListPage`/`data-table` | **34** | **0** | 2 | 18 min (máquina carregada) |
| 9 | celular | clone limpo com a migration da B1 + aquecimento | **29** | **0** | 7 | 8,4 min. **Execução de referência do celular** |

### 2.1 Execução final do celular

**Celular: 29 passed, 0 failed, 7 skipped** (execução 9, depois de todas as correções da SPA e dos specs). **Desktop: 34 passed, 0 failed, 2 skipped** (execução 8, mesmo código). Nenhum flaky, nenhum retry.

**Skips do celular (7):**
- 3 da matriz do menu: `a matriz é a mesma no celular`;
- 2 das tarefas do atendente: `medido no desktop do atendente`;
- entregador no painel: proposital;
- `caixa — autorização`, empresa 2: o banco-base não tem título na empresa 2.

São os mesmos 2 skips do desktop mais os 5 que os specs declaram só-desktop.

## 3. Triagem das falhas

| Falha (celular) | Tipo | Causa | Ação |
|---|---|---|---|
| `admin/gerente/operador entra no painel` | **Spec desatualizado** | No celular o menu é gaveta fechada (`ResponsiveSidebar`). `getByRole('navigation').first()` não existe até tocar em "Abrir menu". A tela está certa: o botão existe, tem nome e abre o menu | `e2e/smoke/login.spec.ts:5-10`: com `isMobile`, toca em "Abrir menu" e exige `navigation "Módulos"` visível. A asserção ficou mais estrita: antes aceitava qualquer `nav`, como a trilha "Você está em" |
| `admin cria usuário; ele entra e vê só o que o papel dá` | **Spec desatualizado** | Mesmo motivo, no 2º contexto (o novo usuário). Pior: com a gaveta fechada, os dois `toHaveCount(0)` do negativo passariam no vazio | `e2e/plataforma/acessos.spec.ts:103-104`: abre a gaveta antes de conferir. Agora o negativo compara itens montados |
| `o caixa vê saldo…`, `P2-D-09 conta sem abertura`, `o título mostra o nome do cliente`, `rota inexistente mostra 404`, `admin cria usuário` (login) | **Flaky de ambiente** | Latência do PHP sem opcache. Nos `error-context.md` o elemento esperado **está** na página, só chegou depois dos 10 s: "Sem abertura", o 404 e a linha do título. O login ficava em "Entrando…" | Opcache + aquecimento (§1). Spec e prazo intactos. Nas execuções 5, 6 e 8, zero falhas |
| Execução 3: 2 primeiras jornadas do caixa | **Flaky de ambiente** | 1º `/api/me` 18 s e 1º login 13 s no servidor recém-subido (log da API) | aquecimento no roteiro |

Nenhuma falha foi defeito de layout em 412 px. Nenhum retry foi usado (`retries` segue 0).

## 4. Acessibilidade (axe)

A fixture `acessibilidade` roda `wcag2a`, `wcag2aa`, `wcag21aa` e `wcag22aa` e **só anexa** o resultado. Ela não reprova, e não foi alterada. Os anexos foram lidos do relatório JSON (`--reporter=list,json`).

Os specs cobrem 5 telas: login, contas do caixa, diálogo de recebimento, confirmação do malote e cadastro do 2FA do superadmin. Para cobrir o painel, rodei o mesmo axe e a mesma emulação Pixel 7 em mais 10 rotas, com o perfil admin: início, pedidos, clientes, lançamentos, caixa, acessos, produtos, estoque, central e configurações.

### 4.1 Violações encontradas e destino

| Tela | Projeto | Gravidade | Regra | Detalhe | Ação |
|---|---|---|---|---|---|
| diálogo de recebimento | celular | **crítica** | `aria-allowed-attr` | `aria-required="true"` no gatilho do `AsyncSelect` ("Entrou em qual conta? *"), que é `button`. O papel `button` não admite o atributo | **Corrigido** em `components/ui/async-select.tsx:35-36` e `async-multi-select.tsx:31-32`: o atributo sai do gatilho. O "*" do rótulo já está no nome acessível (`aria-labelledby`), então a obrigatoriedade segue anunciada |
| contas do caixa | celular | séria | `color-contrast` | badge verde "Aberto": `#21733c` sobre `success/15` numa linha destacada, 4,33:1 | **Corrigido** em `index.css:61-63`: `--success` claro de 29% para 26% de luminosidade. Fica em ~4,9:1 sobre a linha destacada e ~5,5:1 sobre branco. Texto branco sobre o verde sobe de 5,9 para 6,9:1 |
| contas do caixa, recebimento, malote | desktop | séria | `color-contrast` | títulos de seção do menu lateral (10 px), `sidebar-foreground/60` sobre `#1f1f1f`, 3,55:1 | **Corrigido**: `/60` → `/80` (5,28:1) em `layouts/AppShell.tsx:229,237` e `layouts/ModuleFinder.tsx:30`. A dica da busca de módulos (11 px, `/70`, 4,34:1) foi junto, em `ModuleFinder.tsx:32` |
| contas do caixa, malote | desktop | séria | `color-contrast` | rótulo do grupo de abas (`TabsGroup`, 10 px), `muted-foreground/80` sobre `#f5f6f3`, 3,47:1 | **Corrigido** em `components/ui/tabs.tsx:88`: sem a opacidade, ~5,3:1 |
| clientes | celular | **crítica** | `aria-valid-attr-value` | as abas Ativos/Desativados/Todos eram um `Tabs` sem `TabsContent`. O `aria-controls` da aba ativa apontava para um id inexistente | **Corrigido** em `features/clientes/ClientesListPage.tsx:189-224`: a busca e a tabela viram o `TabsContent` da aba ativa (`mt-0`, layout igual) |
| estoque | celular | séria | `scrollable-region-focusable` | a tabela rola na horizontal no celular e não tinha nada focável: pelo teclado, a parte de fora da tela era inalcançável | **Corrigido** em `components/ui/data-table.tsx:54-56`: o contêiner rolável recebe `tabIndex=0` com anel de foco quando a tabela não tem linha clicável. Com linha clicável já há o botão "Abrir" focável |
| cadastro do 2FA (superadmin) | ambos | séria | `color-contrast` | link "Abrir no app autenticador deste aparelho" com `text-primary` (`#FF6200`) sobre branco, 3,0:1 (`features/superadmin/SaLoginPage.tsx:157`) | **Não corrigido: fora do escopo** (`features/superadmin` é da A1). Não precisa da decisão 10: o repositório já tem o token `text-marcaTexto` (`--primary-texto`, 38%), feito para texto na cor da marca. Trocar a classe resolve sem mudar `#FF6200`. O mesmo vale para `SaLayout.tsx:98` (`sidebar-foreground/60`, mesmo padrão do menu corrigido) |

### 4.2 Estado depois das correções

| Tela | celular | desktop |
|---|---|---|
| login | 0 | 0 |
| contas do caixa | 0 | 0 |
| diálogo de recebimento | 0 | 0 |
| confirmação do malote | 0 | 0 |
| cadastro do 2FA (superadmin) | 1 séria (pendente, A1) | 1 séria (pendente, A1) |
| início, pedidos, clientes, lançamentos, caixa, acessos, produtos, estoque, central, configurações | 0 cada | não varrido à parte (o menu lateral, único elemento só-desktop, já está nas telas acima) |

Contraste da cor da marca com texto branco (decisão 10): **nenhuma violação nas telas varridas**. Os botões laranja com texto branco não foram acusados nesta passada. O único laranja acusado é o link do 2FA, que é texto laranja sobre branco e tem token próprio.

## 5. Layout em 412 px e alvos de toque

Varredura no Pixel 7 nas 10 rotas do §4: `scrollWidth` do documento, elementos que vazam a viewport fora de contêiner rolável e alvos com menos de 24 px.

- **Rolagem horizontal da página: nenhuma.** `scrollWidth = 412` nas 10. Tabelas largas rolam dentro do próprio contêiner, que agora é focável.
- **Alvos < 24 px:**
  - "Abrir registro N" e "Abrir pedido #N" (27×16) nas tabelas;
  - "Início" da trilha (28×16);
  - "Resolver: …" no início (53×20).

  O axe (`target-size`, WCAG 2.2 AA) não os acusa: valem a exceção de espaçamento e, nas tabelas, a linha inteira é clicável. **Não é defeito WCAG.** Fica como melhoria de ergonomia: o Android recomenda 48 dp, e no celular esses botões ficam pequenos para o dedo.

## 6. Interferência de outra onda (execução 7)

Durante a execução 7, a B1 (assinatura do tenant) estava alterando o backend no mesmo diretório e criou a migration `2026_11_20_000200_assinatura_do_tenant`, ainda sem commit. O clone daquela execução tinha sido migrado antes dela existir. O código novo já consultava `assinaturas.tenant_account_id`. Resultado:

- `500 GET /api/superadmin/empresas` (`column "tenant_account_id" does not exist`);
- `500` no login do entregador;
- logins lentos com o opcache revalidando arquivos que mudavam.

11 falhas, nenhuma da SPA. O desktop seguinte (execução 8), com o clone já migrado com a `000200`, passou 34/0. A execução 9 repete o celular nas mesmas condições.

**Isto não é defeito**, é o ambiente compartilhado. Mas fica o registro: o E2E desta onda mede o backend do momento, inclusive mudança não commitada de outro agente.

## 7. Verificação da SPA

- `npx tsc --noEmit`: sem erros.
- `npx vitest run`: **191 passed / 50 arquivos**. Numa das execuções 1 teste falhou sob carga, com o Playwright e o PHP rodando ao mesmo tempo. As três execuções seguintes passaram 191/191, a última depois de todas as correções. Não consegui isolar qual foi, porque a saída daquela execução não foi guardada. Fica como possível flaky do vitest sob carga, não investigado.

## 8. Pendências

1. **A1:** `features/superadmin/SaLoginPage.tsx:157`, `text-primary` → `text-marcaTexto`, e `SaLayout.tsx:98`, `/60` → `/80`. São as duas últimas violações axe sérias conhecidas.
2. **Decisão 10 (dono):** nada novo a decidir por esta passada. Nenhuma violação de `#FF6200` com texto branco apareceu nas 15 telas varridas.
3. **Harness:** documentar no `e2e/README.md` a subida da API com opcache e o aquecimento. Opcional: um `globalSetup` que aqueça a API.
4. **Fixture `acessibilidade`:** segue só anexando. Recomendo reprovar em `critical`/`serious` agora que o painel está em zero. Não mudei porque mudaria o comportamento esperado do harness.
5. **Ergonomia de toque:** botões "Abrir" das tabelas e a trilha com menos de 24 px no celular (não é violação WCAG).
6. **Não coberto:** tema escuro (o axe rodou só no claro); telas além das 15 listadas; apps nativos (fora do escopo, sem emulador).
