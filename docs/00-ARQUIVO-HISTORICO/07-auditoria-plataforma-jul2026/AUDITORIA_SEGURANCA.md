# AUDITORIA — SEGURANÇA

> Auditoria técnica com base no código. Achados classificados por prioridade (P1 crítico → P4 informativo).

## 1. Autenticação

| Superfície | Mecanismo | Evidência |
|---|---|---|
| SPA | Sanctum stateful (cookie) **e** Bearer, mesmo endpoint | [AuthController](../../../erp-novo/app/Http/Controllers/Api/AuthController.php) |
| Apps (colaborador/entregador) | e-mail+senha → token Sanctum | [AppAuthController::login](../../../erp-novo/app/Http/Controllers/Api/Mobile/AppAuthController.php) |
| App (cliente) | phone-auth Firebase (SMS) + empresa_id → token | [ClienteAuthService](../../../erp-novo/app/Domain/Mobile/ClienteAuthService.php) |
| SuperAdmin | guard `platform` (tabela `platform_admins`) isolado | [SuperAdmin/AuthController](../../../erp-novo/app/Http/Controllers/Api/SuperAdmin/AuthController.php) |

**Hardening presente (forte):**
- **Lockout** por e-mail E por IP (5 falhas/15 min) além do rate-limit — [LoginSeguranca](../../../erp-novo/app/Domain/Seguranca/LoginSeguranca.php), aplicado no web e no app (paridade).
- **2FA TOTP** nativo (RFC 6238, sem dependência externa) com recovery codes de uso único — [Totp](../../../erp-novo/app/Domain/Seguranca/Totp.php); obrigatório-quando-habilitado no SuperAdmin.
- **Rate-limit** estreito no login (`throttle:login`) e por usuário na API (`throttle:api`); pings de GPS com throttle alto dedicado ([api.php](../../../erp-novo/routes/api.php) L663/674).
- **Trilha de login** em `login_logs` (sucesso/falha + motivo + IP + UA) — [LoginSeguranca](../../../erp-novo/app/Domain/Seguranca/LoginSeguranca.php).
- **Token de app expira** (`SANCTUM_EXPIRATION`, 30d default) + rotação `token/refresh` sem novo login.
- **Política de senha por empresa** ([PasswordPolicyService](../../../erp-novo/app/Domain/Seguranca/PasswordPolicyService.php)); reset de senha revoga tokens.

## 2. Autorização / controle de acesso

Modelo RBAC+ABAC próprio (substitui menuusers+spatie do legado):
- **RBAC**: catálogo único de permissões `modulo.acao` ([PermissaoCatalogo](../../../erp-novo/app/Domain/Shared/PermissaoCatalogo.php)) → Gate por chave ([AuthServiceProvider](../../../erp-novo/app/Providers/AuthServiceProvider.php)). Teste de contrato garante que toda chave usada existe no catálogo.
- **ABAC + hierarquia** ([PolicyEvaluator](../../../erp-novo/app/Domain/Acesso/PolicyEvaluator.php)): quando o Gate recebe um recurso, aplica escopo organizacional (unidade→depto→setor) + condições (limite/ownership/horário).
- **Field-level (A7)**: [CamposPermitidos](../../../erp-novo/app/Domain/Acesso/CamposPermitidos.php) remove campos sensíveis do payload sem `modulo.campo.{nome}.view` (ex.: `credito_limite` no [ClienteResource](../../../erp-novo/app/Http/Resources/ClienteResource.php)).
- **Dupla camada**: middleware `permissao:` (borda) + trait `autorizar()` (fino) delegam ao mesmo Gate. `support` = bypass total (regra herdada do legado, em `Gate::before`).
- **Licença (SaaS)** ortogonal ao RBAC: middleware `recurso:` → 402 se a empresa não tem o feature-flag ([Recurso](../../../erp-novo/app/Http/Middleware/Recurso.php)).

**Cobertura verificada**: todos os controllers `Api/Admin` chamam `autorizar()` exceto `AssinaturaController` e `LookupController` (leitura de listas {id,label} — aceitável, mas ver P3-2).

## 3. Isolamento multi-tenant (resumo; detalhe em AUDITORIA_MULTI_TENANT)

Três barreiras: global scope [BelongsToTenant](../../../erp-novo/app/Domain/Tenant/BelongsToTenant.php), **RLS Postgres** com role restrita `erp_app` NOSUPERUSER/NOBYPASSRLS ([rls_role_app_sem_bypass](../../../erp-novo/database/migrations/2026_06_26_000400_rls_role_app_sem_bypass.php)), e autorização de **canais de broadcast** por posse ([channels.php](../../../erp-novo/routes/channels.php)). IDOR já endereçado em pontos-chave (baixa de parcela revalida o tenant do título-pai — [CaixaService::baixarParcela](../../../erp-novo/app/Domain/Caixa/CaixaService.php); endereço/pedido do app derivam do token — `clienteDoUsuario`).

## 4. Superfícies públicas

| Rota pública | Proteção | Achado |
|---|---|---|
| `POST /login` | throttle:login + lockout | OK |
| `POST /pix/webhook` | segredo compartilhado `X-Webhook-Token` (hash_equals) + validação de estado/valor/idempotência no [PixService](../../../erp-novo/app/Domain/Cobranca/PixService.php) | **P2**: falta assinatura HMAC/mTLS do PSP (o próprio código anota "somar em produção") |
| `POST /app/v1/cliente/login\|cadastro` | Firebase verify + throttle implícito | OK; verificar `FirebaseVerifier` real em prod (fake no CI) |
| `POST /app/v1/marketplace/*`, `GET cidades` | throttle:60,1 | OK |

## 5. Dados sensíveis, uploads, downloads

- **Uploads** (certificado A1, comprovações de entrega, evidências de missão) validados por `file|max:` e gravados em **disco privado** `local` — [EmpresaConfigController](../../../erp-novo/app/Http/Controllers/Api/Admin/EmpresaConfigController.php), [MissaoService](../../../erp-novo/app/Domain/Missao/MissaoService.php), [EntregaService](../../../erp-novo/app/Domain/Mobile/EntregaService.php).
- **Download** de evidência é streaming autenticado com `abort_unless(exists)` ([MissaoController::evidencia](../../../erp-novo/app/Http/Controllers/Api/Admin/MissaoController.php)) — **P3**: não revalida que a evidência pertence ao tenant do requisitante antes de servir o arquivo (o id vem da rota; a query deve escopar por empresa).
- **Segredos em auditoria**: [Auditavel](../../../erp-novo/app/Domain/Shared/Auditavel.php) exclui automaticamente campos com cast `encrypted` da trilha — bom.
- **Storage mobile cifrado** (MMKV + chave no keystore) — bom.
- **P3**: uploads validam tamanho, mas **não validam MIME/conteúdo real** de imagens (só `file`); um arquivo arbitrário renomeado passa.

## 6. Auditoria e observabilidade

- **Auditoria de dados**: trait [Auditavel](../../../erp-novo/app/Domain/Shared/Auditavel.php) grava created/updated/deleted em `audit_logs` (antes/depois, user, IP, empresa). Aplicado em 8 models — **P3**: cobertura seletiva; models financeiros críticos (Financeiro, ContaMovimento) devem entrar.
- **Auditoria de segurança**: [AuditoriaSeguranca](../../../erp-novo/app/Domain/Seguranca/AuditoriaSeguranca.php) registra negações de autorização, mudanças de papel, 2FA, sessões em `security_events`.
- **Auditoria de plataforma** (cross-tenant): toda mutação do SuperAdmin em `platform_audit_logs` ([SuperAdminService](../../../erp-novo/app/Domain/Saas/SuperAdminService.php)).

## 7. Achados classificados

| ID | Prio | Achado | Evidência | Recomendação |
|---|---|---|---|---|
| S-1 | **P1** | Webhook PIX sem verificação de assinatura do PSP (só segredo compartilhado na URL/header) | [PixWebhookController](../../../erp-novo/app/Http/Controllers/Api/PixWebhookController.php) | Somar HMAC/mTLS antes do go-live com PSP real; hoje aceitável só porque o `processarWebhook` valida estado+valor+idempotência |
| S-2 | **P2** | Download de evidência/comprovação não reescopa o arquivo por tenant | [MissaoController::evidencia](../../../erp-novo/app/Http/Controllers/Api/Admin/MissaoController.php) | `->where('empresa_id', tenant)` na busca antes de servir |
| S-3 | **P2** | Uploads sem validação de MIME/imagem real | EmpresaConfig/Missao/Entrega | Adicionar `mimes:` / `image` e validar magic bytes |
| S-4 | **P2** | Cobertura de `Auditavel` seletiva (financeiro crítico fora) | 8 de N models | Incluir Financeiro, FinanceiroParcela, ContaMovimento, NotaFiscal |
| S-5 | **P3** | `LookupController`/`AssinaturaController` sem `autorizar()` explícito | [routes/api.php](../../../erp-novo/routes/api.php) L130-155 | Confirmar que expõem só dados não sensíveis; adicionar gate leve |
| S-6 | **P3** | Fallback `cliente_id` no app ainda aceito (transição) | `clienteDoUsuario` em [AppClienteController](../../../erp-novo/app/Http/Controllers/Api/Mobile/AppClienteController.php) | Remover após todos os clientes vinculados a user_id |
| S-7 | **P3** | Bypass total de `support` sem trilha dedicada de uso | `Gate::before` em [AuthServiceProvider](../../../erp-novo/app/Providers/AuthServiceProvider.php) | Registrar em `security_events` quando o bypass é exercido |
| S-8 | **P4** | CORS usa defaults do framework (sem `config/cors.php`) | ausência do arquivo | Publicar e restringir `allowed_origins` explicitamente para produção |

Nenhum achado P1 é bloqueante para **homologação** (dado o desenho fail-safe do webhook), mas S-1 é bloqueante para **go-live transacional com PSP real**.

## 8. Conclusão

Postura de segurança **madura para o estágio**: lockout, 2FA nativo, RBAC+ABAC com field-level, RLS com role restrita, auditoria em três níveis. Os gaps são pontuais e endereçáveis antes da produção. Prioridade: S-1 (webhook), depois S-2/S-3 (arquivos).

→ Plano: [PLANO_SEGURANCA.md](PLANO_SEGURANCA.md)
