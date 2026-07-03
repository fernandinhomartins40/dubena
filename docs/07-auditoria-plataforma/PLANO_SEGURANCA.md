# PLANO DE EVOLUÇÃO — SEGURANÇA

> Corresponde a [AUDITORIA_SEGURANCA.md](AUDITORIA_SEGURANCA.md).

## Contexto
Postura já madura (lockout, 2FA, RBAC+ABAC, RLS, auditoria). Restam gaps pontuais antes do go-live transacional.

## Objetivo
Fechar os achados S-1…S-8 priorizando o que bloqueia o go-live com PSP real e o acesso a arquivos.

## Benefícios
Elimina risco de fraude no webhook, IDOR em arquivos, uploads maliciosos e cegueira de auditoria financeira.

## Riscos
Baixos. S-1 exige coordenação com o PSP (assinatura/mTLS). Mudanças de auditoria podem aumentar volume em `audit_logs` (dimensionar).

## Estratégia e fases

**Fase 1 — Bloqueadores de go-live (S-1)**
- Implementar verificação de assinatura HMAC (ou mTLS) do PSP no [PixWebhookController](../../erp-novo/app/Http/Controllers/Api/PixWebhookController.php) antes do `processarWebhook`.
- Backend: `config/services.php` (chave pública/segredo do PSP), validação de assinatura sobre o corpo cru.

**Fase 2 — Arquivos e uploads (S-2, S-3)**
- Reescopar por tenant a busca de evidência/comprovação em [MissaoController::evidencia](../../erp-novo/app/Http/Controllers/Api/Admin/MissaoController.php) (`->where('empresa_id', $tenant)`), e no download de comprovações de entrega.
- Adicionar `image`/`mimes:jpg,png,pdf` + validação de magic bytes nos uploads (certificado, comprovações, evidências).

**Fase 3 — Auditoria e endurecimento (S-4, S-7)**
- Aplicar `Auditavel` a Financeiro, FinanceiroParcela, ContaMovimento, NotaFiscal.
- Registrar em `security_events` quando o bypass `support` for exercido (interceptar no `Gate::before`).

**Fase 4 — Higiene (S-5, S-6, S-8)**
- Gate leve em `LookupController`/`AssinaturaController`.
- Remover fallback `cliente_id` no app após vinculação total a `user_id`.
- Publicar `config/cors.php` com `allowed_origins` restritos.

## Dependências
- S-1 depende do PSP (documentação de assinatura). S-6 depende de forçar update dos apps.

## Checklist técnico
- [ ] HMAC/mTLS no webhook PIX
- [ ] Reescopo de tenant em downloads de arquivo
- [ ] Validação de MIME/magic bytes nos uploads
- [ ] `Auditavel` nos models financeiros
- [ ] Trilha de uso do bypass `support`
- [ ] Gate em Lookup/Assinatura
- [ ] Remover fallback `cliente_id`
- [ ] `config/cors.php` restritivo

## Critérios de aceite
- Webhook rejeita payload com assinatura inválida (teste).
- Download de arquivo de outro tenant → 404/403 (teste).
- Upload de arquivo não-imagem → 422 (teste).
- Mutação financeira gera linha em `audit_logs`.

## Estratégia de testes
- Testes de feature para webhook (assinatura), IDOR de arquivo, upload inválido, auditoria financeira. Rodar em **Postgres** (a RLS participa do escopo).
