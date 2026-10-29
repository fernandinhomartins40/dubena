# Briefing comum da banca — Auditoria PIE V2

**Data:** 2026-10-28 · **Commit auditado:** `48b29f72` (main) · **Modo:** AUDITORIA + CORREÇÃO
**Protocolo:** `PROTOCOLO_UNIVERSAL_AUDITORIA_INTEGRAL_PIE_V2 (1).md` (raiz do repositório)

Este documento é o **mesmo** inventário inicial entregue aos cinco revisores. Não
contém conclusões — cada revisor forma as suas sem ver as dos outros.

## Escopo

| Área | Caminho | Stack | No escopo? |
|---|---|---|---|
| ERP novo (backend) | `erp-novo/app`, `routes/api.php` (≈627 rotas), `database/migrations` (171) | Laravel 12 / PHP 8.3 / PostgreSQL 15 com RLS | **sim** |
| SPA administrativa | `erp-novo/frontend/src/features/*` (34 módulos) | React + TS + React Query + Vite | **sim** |
| App do cliente | `app-gas-em-casa/` | React Native / Expo | sim |
| App do entregador | `app-entregador/` | React Native / Expo | sim |
| Código mobile compartilhado | `mobile-shared/` | TS | sim |
| Deploy / infraestrutura | `deploy/`, `.github/workflows/erp-novo.yml`, `erp-novo/docker*`, VPS `ssh dubena-vps` (homologação) | Docker, nginx, GHCR, GitHub Actions | sim |
| Legado | `ctrl-web/`, `legado-*` | Laravel 5.8 / Android | **fora** — só referência, nunca alterar |

**Domínios backend** (`erp-novo/app/Domain`): Acesso, Alerta, Apoio, Auditoria, Caixa,
Cliente, Cobranca, Contrato, Crm, Empresa, Estoque, Financeiro, Fiscal, Frota,
Geografico, Gestao, Identidade, Integracao, Legado, Logistica, Missao, Mobile,
Monitora, Pagamento, Pedido, Produto, Relatorio, Rh, Saas, Satelite, Seguranca,
Shared, Telefonia, Tenant, Venda.

**Controllers:** `Api/Admin` (60), `Api/Mobile` (9 + Concerns), `Api/SuperAdmin` (5),
`Api/Legado` (pontes MovelApp/Nfweb), `AuthController`, `PixWebhookController`,
`PabxWebhookController`, `SegurancaController`, `HealthController`.

**Agendamentos** (`routes/console.php`): alertas, sync de posições a cada 30 s,
expiração de PIX a cada minuto, notificação de vencidos, vendas diárias,
vigilância de certificado, IBPT, geração de missões a cada 10 min, expurgo de
posições, vigilância de comodato. Job: `ExecutarMigracaoJob`.

**Multi-tenant:** RLS no Postgres (`erp_app` NOSUPERUSER NOBYPASSRLS; migrations
como `pgsql_owner`), trait `BelongsToTenant`, chaves `empresa_id` e
`tenant_account_id`. Plano SaaS com licença/plano/módulos (domínio `Saas`).

**Leitura obrigatória antes de concluir qualquer coisa:** `CLAUDE.md` da raiz
(seção "Armadilhas reais" — lista o que já foi descoberto e corrigido; não
reportar como novo o que já está documentado e mitigado, a menos que se prove
que voltou).

## Ambiente disponível para provas

- **Suíte backend:** sqlite `:memory:` (`phpunit.xml`). PHP portátil:
  `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`
  (não está no PATH; prefixar o PATH no PowerShell). Testes de RLS são pulados em sqlite.
  Uma execução integral de baseline já está rodando — **não** rode a suíte inteira;
  rode arquivos/filtros específicos.
- **SPA:** `cd erp-novo/frontend && npx vitest run` / `npx tsc --noEmit`.
- **Postgres local** portátil disponível (ver memória do projeto) para o gate RLS.
- **VPS de homologação:** `ssh dubena-vps` — **somente leitura** (logs, `docker ps`,
  `docker stats`, `SELECT`). Nenhuma escrita, restart ou alteração. Mexer só nos
  contêineres desta aplicação.
- **Sem Docker local**, sem Python. Shell: Git Bash e PowerShell 5.1.

## Regras para os revisores

1. **Não alterar nenhum arquivo do projeto.** A única escrita permitida é o seu
   próprio `docs/pie/BANCA_Rn_*.md`.
2. Toda afirmação aponta para arquivo:linha verificado, comando executado com
   saída real, ou é marcada como HIPÓTESE. Nunca inventar execução, métrica ou linha.
3. Classifique cada achado: CONFIRMADO / FORTE INDÍCIO / HIPÓTESE / NÃO REPRODUZIDO,
   e severidade CRÍTICA / ALTA / MÉDIA / BAIXA com justificativa.
4. Formato por achado: ID (`Rn-NN`), título, categoria, severidade, estado, arquivo:linha,
   rota/serviço, reprodução segura, esperado × observado, evidência, impacto,
   correção proposta, teste de aceite.
5. Ao final: o que foi inspecionado, testado, **não** testado, limitações, e parecer
   (APROVADO / REPROVADO / NÃO COMPROVADO) para a sua área.
6. Não exponha segredos, tokens ou dados pessoais (dados reais estão na homologação).
