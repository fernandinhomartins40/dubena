# Rede, filiais e tenants — como o multi-tenant se organiza

Referência curta para responder a pergunta que sempre volta: *"uma empresa pode
ter várias filiais?"*. Sim — e este documento diz como, com os testes que
provam o comportamento.

## Os dois níveis

| Nível | Tabela | O que é | Exemplo |
|---|---|---|---|
| **Rede** | `grupos` | O cliente do SaaS — o dono da revenda | Rede Dubena |
| **Estabelecimento** | `empresas` | O tenant operacional; cada CNPJ | Dubena Matriz, Dubena Filial Centro |

Quase todo dado é escopado por `empresa_id` (cliente, pedido, estoque,
financeiro). Cadastros de apoio compartilhados na rede são escopados por
`grupo_id` (segmentos, bancos, papéis).

Há **duas barreiras** de isolamento, e as duas valem ao mesmo tempo:

1. *Global scope* do Eloquent (`BelongsToTenant` / `BelongsToGrupo`);
2. *Row Level Security* no Postgres (134 tabelas), com a role de runtime
   `erp_app` sem `BYPASSRLS`.

## Como um usuário opera mais de uma filial

Três peças independentes — e é o esquecimento de uma delas que produz o usuário
que "entra mas não vê" ou "vê o que não deveria":

1. **Empresa padrão**: `users.empresa_id`, onde ele cai ao entrar.
2. **Acesso às demais**: pivot `empresa_user`. Sem isso, pedir outra empresa é
   ignorado.
3. **Permissão naquela empresa**: pivot `role_user`, que carrega `empresa_id`.
   - `role_user.empresa_id = X` → o papel vale só na empresa X.
   - `role_user.empresa_id = NULL` → papel **global na rede**, vale em qualquer
     filial que o usuário acesse. É o caso do dono.

A troca em runtime é feita pelo header **`X-Empresa-Id`**, validado contra
`podeAcessarEmpresa()` — pedir uma empresa não permitida não troca o tenant
(silenciosamente permanece na atual), então não há como escalar acesso por aí.

> Detalhe de modelagem: `role_user` é único por `(user_id, role_id)` e o papel
> pertence ao **grupo**. Por isso não dá para anexar o mesmo papel uma vez por
> filial — para o dono usa-se o papel global; para quem opera uma unidade só,
> o papel escopado naquela empresa.

## Papéis prontos (RbacSeeder)

Criados por rede: **Administrador** (tudo), **Gerente** (tudo menos exclusões,
config de empresa/grupo e Central de Acessos), **Operador** (operação do dia a
dia), **Entregador** (espelha o app: pedido + monitora).

## O caso Dubena (dados migrados)

- As **7 empresas** vindas do Oracle têm CNPJ de mesma raiz
  (`04.190.715/000X-XX`): matriz + filiais da **mesma rede**.
- **Central Gás**, **QTI** e **Dubena Particular** vieram do Monitora e são
  **frota de terceiros** que a Dubena monitora. Ficam na mesma rede e **não**
  são clientes do SaaS — não têm cliente nem pedido, só veículos rastreados.
- Um dono de revenda diferente seria um **grupo** novo, com suas empresas.

## Onde isso está testado

- `tests/Feature/RedeFiliaisTest.php` — o dono alterna entre filiais e vê os
  dados de cada uma; quem é de uma filial não alcança a irmã nem forçando o
  header; outra rede é inacessível.
- `tests/Feature/AcessoRedeDubenaSeederTest.php` — o cenário real montado pelo
  seeder (dono + gerente de filial), incluindo idempotência.
- `tests/Feature/SegurancaMultiTenantTest.php` e a suíte de RLS — as duas
  barreiras de isolamento.

## Quem administra a plataforma

O **SuperAdmin** (`platform_admins`, guard `platform`) é identidade separada:
não pertence a nenhuma empresa e é o único que cruza tenants — sempre auditado.
É quem enxerga todas as redes e opera a ferramenta de migração. Um token de
tenant recebe **401** em `/superadmin`, e vice-versa.
