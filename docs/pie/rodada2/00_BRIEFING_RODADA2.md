# Briefing comum — Auditoria PIE V2, Rodada 2

**Data:** 2026-10-30 · **Commit de partida:** `133f588d` (main) · **Modo:** AUDITORIA + CORREÇÃO
**Protocolo:** `PROTOCOLO_UNIVERSAL_AUDITORIA_INTEGRAL_PIE_V2 (1).md` (raiz)

## Por que existe uma Rodada 2

A Rodada 1 (`docs/pie/*.md`, 28–29/10) cobriu bem segurança, dinheiro, fiscal e
backup, mas deixou três lacunas que o dono apontou como superficiais:

1. **Produto.** `OPORTUNIDADES_PRODUTO.md` tem 11 itens, quase todos correção de
   defeito. Ninguém avaliou o sistema **como produto SaaS para N revendas de GLP**:
   proposta de valor, onboarding de uma revenda nova, planos e licenças, o que
   ainda é convenção da Dubena, o que um dono de revenda precisa no dia e não acha.
2. **Apps nativos.** `app-gas-em-casa` e `app-entregador` foram avaliados só por
   leitura de código, dentro do recorte de segurança. Faltou o app como produto
   (jornada do consumidor, do entregador em campo), prontidão para as lojas,
   permissões, offline, bateria, acessibilidade e testes.
3. **erp-novo.** O R2 da Rodada 1 foi **estático** (nenhum clique real). Os módulos
   INV-13..22 (produto/preço, logística/missões, monitora/frota, geográfico, RH,
   CRM/gestão/metas, relatórios/dashboard, auditoria, telefonia, satélites) e o
   superadmin ficaram "sem revisão adversarial específica".

**Não repetir a Rodada 1.** Ler `docs/pie/ACHADOS_CONSOLIDADOS.md` e
`docs/pie/PLANO_MESTRE_AUDITORIA.md` antes: o que lá está CORRIGIDO não é achado
novo, a menos que se prove que voltou ou que a correção é incompleta. O que está
PENDENTE pode ser citado pelo ID (PIE-nn), sem redescrever.

## Escopo

| Área | Caminho | Stack |
|---|---|---|
| API | `erp-novo/app`, `erp-novo/routes/api.php` | Laravel 12 / PHP 8.3 / PostgreSQL 15 + RLS |
| SPA | `erp-novo/frontend/src` (34 features) | React + TS + React Query + Vite |
| App do consumidor | `app-gas-em-casa/` | Expo 53 / expo-router (tem vitest) |
| App do entregador | `app-entregador/` | Expo 53 / expo-router (sem runner de teste) |
| Compartilhado | `mobile-shared/` | TS + plugins Expo |
| Lojas | `store-assets/` (não versionado ainda) | material da Play Store |
| Fora | `ctrl-web/`, `legado-*` | nunca alterar |

Documentos de produto: `docs/01-vigente/PLANO_TRANSFORMACAO_SAAS.md`,
`APPS_DE_CAMPO_E_CENTRAL_DE_VENDAS.md`, `MAPA_NAVEGACAO_ALVO.md`, `SPEC_CLIENTE.md`,
`implementacao-saas/F*_PROGRESSO.md`. `CLAUDE.md` da raiz é leitura obrigatória
(seção "Armadilhas reais").

## Ambiente para provas

- **PHP** portátil fora do PATH: no PowerShell,
  `$env:Path = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;$env:Path"`.
  Suíte em sqlite `:memory:`. **Não rode a suíte inteira** (passa de 15 min); rode
  arquivos ou `--filter`.
- **PostgreSQL 15 portátil** em `%LOCALAPPDATA%\dubena-tools\pg15x\pgsql`, dados em
  `%LOCALAPPDATA%\dubena-tools\pgdata`, porta 55432, auth trust. `pg_ctl ... start`
  não retorna na ferramenta: rode com timeout curto e confira com `psql`.
- **SPA:** `cd erp-novo/frontend && npx vitest run` / `npx tsc --noEmit`.
- **App do cliente:** `cd app-gas-em-casa && npx vitest run` / `npx tsc --noEmit`.
- **Navegador:** os binários do Playwright estão em `%LOCALAPPDATA%\ms-playwright`
  (chromium-1248 e outros). O pacote não está instalado em nenhum projeto: instale
  numa pasta do **scratchpad** (`npm i playwright@<versão compatível>`), nunca no repo.
- **Proibido:** emulador Android, Gradle, build de APK, `adb` (derrubam o login desta
  máquina). Nada de push, deploy, escrita na VPS ou no banco de homologação.
- **VPS** (`ssh dubena-vps`): só leitura, e só se indispensável. A homologação tem
  **dados pessoais reais**: não copie para relatório, não tire captura dela.
- Shell: Git Bash e PowerShell 5.1. PHP em heredoc ou `node -e` corrompe `$` e `\`:
  use a ferramenta Write.

## Regras para os revisores

1. **Não alterar nenhum arquivo do projeto.** Escreva só o seu relatório em
   `docs/pie/rodada2/`. Sondas, scripts e capturas vão para o scratchpad indicado
   no seu prompt.
2. Toda afirmação aponta para arquivo:linha verificado, comando executado com saída
   real, captura de tela real, ou é marcada **HIPÓTESE**. Nunca inventar execução,
   métrica, clique ou linha.
3. Estado: CONFIRMADO / FORTE INDÍCIO / HIPÓTESE / NÃO REPRODUZIDO. Severidade:
   CRÍTICA / ALTA / MÉDIA / BAIXA, com justificativa.
4. Formato por achado: ID (`P2-<frente>-NN`), título, categoria, severidade, estado,
   arquivo:linha, rota/tela, reprodução, esperado × observado, evidência, impacto,
   correção proposta, teste de aceite, **esforço (P/M/G)**.
5. Oportunidades de produto seguem ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR →
   CRIAR e separam **fundamentada** (evidência no código/dado) de **hipótese a validar
   com revendas**.
6. Ao final: inspecionado, executado, **não** testado, limitações e parecer
   (APROVADO / REPROVADO / NÃO COMPROVADO) para a sua frente.
7. Sem segredos, tokens ou PII nos relatórios.
