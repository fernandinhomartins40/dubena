# F1-17 — Credenciais por tenant e reclassificação de catálogos

Data: 2026-08-29 (America/Sao_Paulo)

Motivado por uma observação do operador: cada revenda tem chaves e credenciais
próprias da sua operação, e **duas revendas concorrentes** (Guarapuava e Pitanga,
no exemplo dado) não podem ver a da outra.

## O furo nas credenciais

`config_globais` guarda exatamente o que separa concorrentes: `rt_csrt` (CSRT do
responsável técnico), `email_senha`, `sat_signac_prod`/`sat_signac_homolog` e
`google_maps_key`.

Duas falhas, ambas provadas na homologação com a role `erp_app`:

**1. Policy legada.** A tabela ainda usava `app.grupo_id`. O contraste na mesma
sessão, sem envelope nenhum:

```
com app.grupo_id=2 e SEM envelope
  -> config_globais visiveis: 1     (google_maps_key presente: SIM)
  -> clientes visiveis: 0
```

`clientes` já estava fechado pela fronteira nova; `config_globais`, não. Agrava
que `IntegracaoTenant::googleMapsKey()` usa `withoutGrupo()`, o que derruba o
escopo do Eloquent — a RLS era a única barreira, e era a antiga.

**2. Chave em claro.** `google_maps_key` era a única credencial da tabela que
não era `encrypted` nem `hidden`. Ela é cobrada por uso: uma revenda podia
gastar a cota da outra.

### Correção

- policy canônica de configuração de grupo;
- `google_maps_key` vira `encrypted` + `hidden`;
- a coluna era `varchar(120)` — o payload cifrado passa disso e seria truncado
  em silêncio, então vira `text` **antes** de cifrar;
- a migration é idempotente: detecta payload já cifrado e não cifra duas vezes;
- `googleMapsKey()` passa a exigir que o grupo pedido pertença ao tenant do
  envelope quando o enforcement está ligado; sem envelope, nega e cai no
  fallback da plataforma (que já registra warning).

Provado em PostgreSQL: valor em claro pré-existente é cifrado, o cast decifra de
volta, a chave **não aparece** em `toArray()`, e reexecutar a migration não
corrompe o valor.

## A reclassificação das 19

Estavam como PLATFORM sob a justificativa *"não contém dado operacional de
empresa"*. Investigando, três evidências apontaram o contrário:

| Evidência | Achado |
|---|---|
| Schema | todas têm `grupo_id` e unicidade `(grupo_id, descricao)` |
| Código | todos os models usam `BelongsToGrupo` |
| Dados | `tipos_documento_veiculo` já duplicado **7 + 7** entre os dois grupos |

E o decisivo: **todas são editáveis pela revenda**, via `CadastroApoioRegistry`
ou controller próprio — inclusive `bancos` e `estados_civis`.

Se a revenda edita, o dado é dela: uma renomeando um banco ou desativando uma
unidade de medida não pode afetar a concorrente.

O PLATFORM de verdade já existe no repositório e serviu de régua:
`municipios_ibge`, documentado como *"catálogo público e imutável da União: sem
grupo_id"*. Junto de `logradouros_oficiais`, seguem PLATFORM — não têm
`grupo_id` nem tela de edição.

A expansão de chave de F1-03 não as alcançava (rodou quando ainda eram
plataforma), então elas recebem `tenant_account_id` aditivo e entram na ponte
documental, que passa de 18 para **37 tabelas**. Sem backfill: o dono continua
vindo da ponte aprovada por grupo.

## Enforcement

Correção de um registro anterior: **`tenant.saas` nunca esteve fora das rotas.**
Ele já estava aplicado a todas as rotas `auth:sanctum` (`routes/api.php` linhas
126, 939, 960) — o docblock do middleware é que estava desatualizado, dizendo o
contrário. Foi corrigido.

E, ao inspecionar a VPS, `SAAS_ENFORCE_TENANT_ENVELOPE=true` **já estava no
ambiente** e ativo (`config` efetiva `true`, sem cache de config). O enforcement
está ligado em homologação, e a aplicação responde HTTP 200.

### O que isso custa

Com o enforcement ligado, a suíte local vai a **566 falhas de 1.346**. A causa é
uma só: as factories criam empresa sem `TenantCompany`/membership/grant, e o
resolver corretamente nega. É dívida do ambiente de teste, não da aplicação.

Em homologação o quadro é outro, porque a fronteira real existe:

| | |
|---|---|
| Usuários ativos | 82 |
| Com membership ACTIVE | **81** |
| Sem | 1 — `admin@gasemcasa.com`, empresa 139 |

O único afetado é o admin de teste do "Grupo Padrão", a empresa deliberadamente
deixada fora da fronteira. Ele tem `support=true`, e perder o acesso é o
comportamento **pretendido**: `support` deixa de ser bypass universal.

## Evidência

- 142 migrations do zero em PostgreSQL 16.
- `saas:tenant:proteger-configuracao-grupo --apply`: **37 tabelas**.
- `saas:f1:pre-cutover-check`: **exit 0** após o comando documental.
- Suíte integral (enforcement desligado, como no CI): **1.339 passes, 4.254
  assertions, 8 skips, zero falhas**.
- Homologação com enforcement ligado: HTTP 200.

## Pendência assumida

**A suíte não cobre o modo com enforcement ligado.** Enquanto as factories não
criarem a fronteira, qualquer regressão nova ficaria escondida no meio das 566
falhas conhecidas. Esse é o próximo trabalho antes de considerar o modo novo
protegido por testes — foi uma escolha consciente de ordem, não um esquecimento.

`erp-novo/perda.sql` segue pré-existente e intocado.
