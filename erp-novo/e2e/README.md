# Jornadas E2E por perfil

Prova em runtime do [protocolo de varredura](../../PROTOCOLO_VARREDURA_PONTA_A_PONTA.md) §5:
navegador real → SPA → API como `erp_app` → Postgres com RLS → releitura no banco.

## Subir o ambiente (Windows, sem Docker)

1. **Banco do agente**, clonado do banco-base (dados de demonstração, 2 empresas, 1 tenant,
   usuários por perfil, superadmin):
   ```bash
   psql -h 127.0.0.1 -p 55432 -U postgres -c "create database pie_r3_<x> template pie_r3_base"
   ```
   O `template` exige o `pie_r3_base` sem conexões abertas.
2. **API** (PowerShell), com o runtime `erp_app` e os enforcements como na homologação:
   ```powershell
   $env:Path = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;$env:Path"
   $env:DB_CONNECTION='pgsql'; $env:DB_HOST='127.0.0.1'; $env:DB_PORT='55432'; $env:DB_DATABASE='pie_r3_<x>'
   $env:DB_USERNAME='erp_app'; $env:DB_PASSWORD='runtime_test_password_2026'; $env:RLS_APP_DB_PASSWORD='runtime_test_password_2026'
   $env:DB_OWNER_USERNAME='postgres'; $env:DB_OWNER_PASSWORD='postgres'
   $env:CACHE_STORE='file'; $env:QUEUE_CONNECTION='sync'; $env:SESSION_DRIVER='array'
   $env:SAAS_ENFORCE_LICENCA='true'; $env:SAAS_ENFORCE_TENANT_ENVELOPE='true'
   cd erp-novo; php artisan serve --port=<porta>
   ```
3. **SPA**: `cd erp-novo/frontend && npx vite --port <porta>`.
4. **Jornadas**:
   ```bash
   cd erp-novo/e2e && npm install
   PIE_DB=pie_r3_<x> PIE_API_URL=http://127.0.0.1:<porta-api> PIE_SPA_URL=http://localhost:<porta-spa>/novo/app/ npx playwright test
   ```

## Contas (só no banco local de teste)

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | `admin@pie.local` | `Pie-R3-Admin!2026` |
| Gerente / Operador / Entregador | `gerente@` / `operador@` / `entregador@pie.local` | `Pie-R3-Perfil!2026` |
| Superadmin | `sa@pie.local` | `Pie-R3-Super!2026` |

## Regras para escrever uma jornada

- Uma pasta por perfil ou área (`atendimento/`, `caixa/`, `plataforma/`…).
- Use as fixtures de `support/fixtures.ts`: `entrar(perfil)`, `evidencia` (console e HTTP ≥ 400)
  e `acessibilidade(nome)`. Termine com `semErroGrave(evidencia)`.
- **Prova de persistência:** depois de salvar, `recarregar(page)` **e** `consultar(sql)` de
  `support/db.ts`. O toast de "salvo" não é prova.
- Todo caso negativo do protocolo §5.2 que se aplica à tela entra como `test` próprio.
- Seletores por papel ou rótulo (`getByRole`, `getByLabel`), como o usuário enxerga. Se a
  tela não tem rótulo acessível, isso **é** um achado.
