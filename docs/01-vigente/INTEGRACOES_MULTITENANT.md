# Integrações & Segredos Multi-Tenant

> Como cada empresa (tenant) usa suas PRÓPRIAS credenciais para fiscal, financeiro,
> pagamento e mapas — sem que um segredo vaze entre empresas. Spec + estado de
> implementação.

## 1. As três camadas de credencial

Nem todo segredo é por empresa. Há três escopos, e cada integração pertence a um:

| Camada | Onde mora | Exemplos | Por quê |
|---|---|---|---|
| **Plataforma** | `.env` do servidor (uma vez) | `APP_KEY`, banco, Firebase/FCM (service account), SGCasa (contrato mestre) | Infra da plataforma; não varia por cliente |
| **Grupo (rede)** | `config_globais` (1 linha por grupo, `BelongsToGrupo`) | Google Maps key, SMTP da rede, RT/CSRT, SAT | Uma rede compartilha; escopado por `grupo_id` |
| **Empresa (revenda)** | `empresa_configs` (1 linha por empresa) | Certificado A1 + CSC (fiscal), conta CNAB por banco, **PIX (PSP)**, **cartão (gateway)**, SMTP próprio | Cada revenda fatura/cobra com o SEU credenciamento |

Regra de resolução de todo segredo: **empresa → grupo → plataforma (env)**, parando no
primeiro que existir. Assim uma empresa sem credencial própria pode herdar a do
grupo (ex.: Maps) ou cair no default da plataforma (dev/homolog), mas dinheiro e
fiscal **exigem** o nível empresa (não faz sentido faturar com o CNPJ de outra).

## 2. Onde cada segredo é cifrado

- **Empresa**: colunas dedicadas com cast `encrypted` (`cert_senha`, `nfce_csc_token`, `email_password`) OU dentro do JSON `empresa_configs.dados` para as chaves promovidas por fase (cobrança CNAB, e agora PIX e cartão). O JSON inteiro é gravado via service que cifra os campos sensíveis antes de persistir.
- **Grupo**: `config_globais` com cast `encrypted` (`google_maps_key`, `email_senha`, `rt_csrt`, `sat_signac_*`).
- Nunca retornar segredo em GET (write-only): a API devolve só um booleano `configurado` + campos públicos; a SPA mostra "•••• configurado" e um campo para re-enviar.

## 3. Resolvedor central — `IntegracaoTenant`

`App\Domain\Integracao\IntegracaoTenant` é o ponto único que resolve credencial da
empresa/grupo ativos (do `TenantContext`), com fallback para env. Os drivers
(`PixService`, `EredeDriver`, `GoogleRoutesDriver`, …) passam a perguntar a ele em
vez de ler `config()` direto. Métodos:

- `pix(empresaId?)` → `{ psp, client_id, client_secret, chave, webhook_hmac_secret, … }` da empresa; null se não configurada.
- `cartao(empresaId?)` → `{ gateway, pv, token, url }` da empresa.
- `googleMapsKey(grupoId?)` → key do grupo (`config_globais`) OU env (`GOOGLE_MAPS_KEY`).

## 4. Estado de implementação

| Integração | Escopo | Estado |
|---|---|---|
| Fiscal (cert A1 + CSC) | empresa | ✅ já era por empresa (`NFePHPSefazDriver` lê `empresa_configs`) |
| Boleto/CNAB (conta por banco) | empresa | ✅ já era por empresa (`ContaCobranca::daEmpresa`) |
| SMTP | empresa e grupo | ✅ existe nos dois níveis |
| **PIX (PSP)** | empresa | ✅ **implementado** — `dados['pix']` por empresa; webhook resolve a empresa pelo `txid` da cobrança; HMAC por empresa (fallback env) |
| **Cartão (eRede)** | empresa | ✅ **implementado** — `EredeDriver` lê PV/token da empresa (fallback env) |
| **Google Maps/Routes** | grupo | ✅ **implementado** — drivers usam `config_globais.google_maps_key` do grupo ativo (fallback env) |
| Firebase/FCM, SGCasa, DB | plataforma | ✅ env (correto) |

## 5. Isolamento — garantias

- As credenciais por empresa vivem em `empresa_configs`, que é **allowlisted na RLS** (resolvido por `empresa_id` explícito no controller/service) mas sempre lido pela empresa ATIVA do `TenantContext` — nunca por id vindo do cliente.
- O webhook PIX é público, então resolve a empresa pelo `txid` (que é da cobrança, logo da empresa) e valida o HMAC **daquela empresa** — um webhook não confirma cobrança de outra.
- `golive:check` passa a validar, quando o gate está em modo real, que **toda empresa** tem a credencial do nível exigido (fiscal, cobrança, PIX), não só que o env global existe.
