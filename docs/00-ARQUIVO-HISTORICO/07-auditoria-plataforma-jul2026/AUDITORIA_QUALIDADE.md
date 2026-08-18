# AUDITORIA — QUALIDADE DO CÓDIGO

> Base: leitura integral do backend, SPA e apps; execução da suíte de testes.

## 1. Legibilidade e organização

**Muito acima da média:**
- Comentários explicam **o porquê** (regra de negócio, decisão, armadilha), não o óbvio. Ex.: cada service abre com a invariante que preserva; migrations de RLS explicam o furo que fecham.
- Nomes em pt-br consistentes com o domínio (revenda/tenant/situação/efeito).
- Estrutura previsível (Controller→Service→Model; feature no front; service por domínio no mobile).

## 2. Duplicação

| ID | Prio | Duplicação | Evidência | Recomendação |
|---|---|---|---|---|
| Q-1 | **P2** | Infra dos dois apps mobile quase idêntica | `http.ts`/`realtime.ts`/`storage.ts` em ambos | Pacote compartilhado (monorepo) |
| Q-2 | **P3** | Verificação de 2FA duplicada (web × app) | [AuthController::verificar2fa](../../../erp-novo/app/Http/Controllers/Api/AuthController.php) e [AppAuthController::verificar2fa](../../../erp-novo/app/Http/Controllers/Api/Mobile/AppAuthController.php) idênticos | Extrair para o domínio Seguranca (um método) |
| Q-3 | **P3** | Aliases de rota = dois caminhos por função | [api.php](../../../erp-novo/routes/api.php) | Canonizar + depreciar |
| Q-4 | **P4** | Haversine reimplementado em 5+ services | Pedido/Missao/Distribuidor/Monitora/Roteirizador | Um helper `Geo::haversineKm` |

A duplicação anterior de controllers (`autorizar()` em 36 lugares) **já foi eliminada** pela trait — bom sinal de refatoração ativa.

## 3. Responsabilidades e complexidade

- Services coesos (uma responsabilidade por classe). Poucos "god objects".
- **Q-5 (P3)**: pontos de alta complexidade concentrada: [CalculoImpostoService](../../../erp-novo/app/Domain/Fiscal/CalculoImpostoService.php) (inevitável — regra fiscal), [AppClienteController](../../../erp-novo/app/Http/Controllers/Api/Mobile/AppClienteController.php) 572L (evitável — extrair), [PedidosPage.tsx](../../../erp-novo/frontend/src/features/pedidos/PedidosPage.tsx) 455L (extrair sub-componentes).

## 4. Testes

- **Backend forte**: 106 arquivos, **568 testes / 1859 assertions verdes** (3 skips, rodados nesta auditoria). Cobrem tenant cross, segurança, financeiro/caixa/cheque, fiscal, RBAC/ABAC, performance, entrega.
- **Q-6 (P1 para o dump)**: testes em **sqlite**, produção em **Postgres**. RLS e `ilike` são no-op/divergentes no sqlite → a camada mais crítica (isolamento) e a busca não são exercidas de verdade. Adicionar job de CI com serviço Postgres.
- **Q-7 (P2)**: **frontend e apps sem testes** (SPA só `tsc --noEmit`; apps sem suíte).

## 5. Código não utilizado / dívida

- **Q-8 (P4)**: `HomologSeeder` e `GuarapuavaMapaSeeder` coexistindo com `DemoGuarapuavaSeeder` — verificar se algum é obsoleto.
- **Q-9 (P4)**: diretório `app/Policies` vazio (Gate central assumiu) — pode confundir; documentar.
- **Q-10 (P4)**: `pedidos.financeiro_id` sem FK formal ("quando N5 chegar" — chegou).
- Comentários de fase (F02/N4/L6…) espalhados são úteis como trilha, mas com o tempo viram ruído; considerar mover a narrativa histórica para os docs.

## 6. Padronização

- **PHP**: Laravel Pint disponível (`require-dev`), tipos de retorno e PHPDoc consistentes, `declare`/enums usados. Bom.
- **TS**: strict TS, ESLint implícito via padrões; falta script de lint real no CI do front.
- **Convenções de tenant/auditoria** aplicadas uniformemente por trait — alta consistência.

## 7. Achados priorizados

| ID | Prio | Tema |
|---|---|---|
| Q-6 | **P1** | CI/testes em Postgres (RLS/ilike não testados) |
| Q-1 | **P2** | Duplicação de infra dos apps |
| Q-7 | **P2** | Sem testes de front/mobile |
| Q-2/Q-3/Q-5 | **P3** | Dedupe 2FA, aliases, quebrar arquivos grandes |
| Q-4/Q-8/Q-9/Q-10 | **P4** | Higiene (helper geo, seeders, Policies, FK) |

## 8. Conclusão

Qualidade **notavelmente alta** para um sistema deste tamanho: código legível, services coesos, refatorações já aplicadas, suíte robusta. O ponto de maior risco é **testar em sqlite o que roda em Postgres** (Q-6) — mascara justamente a camada de isolamento. Depois, reduzir duplicação (apps, 2FA) e cobrir front/mobile com testes.

→ Plano: [PLANO_QUALIDADE.md](PLANO_QUALIDADE.md)
