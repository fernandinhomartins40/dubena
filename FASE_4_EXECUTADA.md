# Fase 4 (Upgrade de Framework/Runtime + Limpezas) — Registro de Execução

> Execução da Fase 4 do `PLANO_MODERNIZACAO_ECOSSISTEMA.md`, validada contra os
> containers Docker locais e protegida pela rede de testes (Fase 2). **Produção
> intocada** (mudanças nos clones). Data: 2026-06-13.

> **Decisão de escopo (aprovada):** upgrade incremental **até onde for seguro**
> (Laravel 5.4 → 5.5 → 5.6 → 5.8), **parando antes do 6.0** — que exigiria substituir
> dependências travadas no L5.x (`laravelcollective/html`, `maatwebsite/excel 2.1`,
> bibliotecas fiscais `sped`/`boleto`) e reescrever APIs, um projeto à parte de alto
> risco fiscal. O 5.8 já entrega ganhos reais (auto-discovery, melhorias de segurança,
> base para o salto ao 6 LTS no futuro) sem tocar nas regras fiscais.

---

## Resultado por sistema

| Sistema | Framework antes | **depois** | PHP | Suíte |
| --- | --- | --- | --- | --- |
| ctrl-web (ERP) | Laravel 5.4.36 | **5.8.38** | 7.4 | 10 OK, 1 skip |
| monitoramento | Laravel 5.4.36 | **5.8.38** | 7.1→(roda 7.1) | 5 OK |
| api-app-gc | Laravel 5.6.40 | **5.8.38** | 7.4 | 5 OK |

> Os três agora compartilham a **mesma versão de framework (5.8.38)** — o ecossistema
> deixou de estar "desalinhado até dentro da mesma família Laravel".

---

## 1. Limpezas de baixo risco (feitas primeiro)

### Código morto eliminado (monitoramento)
Removida a pasta `app/Processors/` — **3.414 linhas** de código do ERP (caixa, financeiro,
NF, estoque) copiadas para o monitoramento e **nunca referenciadas** (zero usos em
controllers/rotas). App e suíte seguem verdes após a remoção.

### Módulo `integration/` legado reescrito (monitoramento)
Os scripts procedurais `integration/atualizaposicoes.php` + `Class/{SGCasa,Monitoramento}.php`
(PHP 4/5 com `mysql_*` removido no PHP 7, **SQL Injection** por concatenação, credenciais
e mapa device→veículo **hardcoded**) foram **substituídos** por um Artisan Command:
- `app/Console/Commands/SyncPosicoesSGCasa.php` (`php artisan sync:posicoes-sgcasa`).
- Query Builder com **bindings** (sem SQLi), conexão `sgcasa` via `.env` (sem credenciais
  no código), vínculo device→veículo **lido do banco**, inserção em lote + transação.
- Agendado no `Console/Kernel` (`everyMinute()->withoutOverlapping()`).
- Conexão `sgcasa` adicionada ao `config/database.php` (credenciais via env).
- Pasta `integration/` **removida**.

## 2. Upgrade incremental do ERP (ctrl-web): 5.4 → 5.5 → 5.6 → 5.8

Cada salto validado pela suíte antes do próximo. Ajustes necessários:
- **Removido `yajra/laravel-oci8`** (driver Oracle) — viável porque a Fase 3 já migrou
  para PostgreSQL. Removidos: o `use OracleBuilder` (só em docblocks) no `SearchController`,
  o `Oci8ServiceProvider` em `config/app.php`.
- **`config/auth.php`**: provider `driver => 'oracle'` (do oci8) → `'eloquent'` (padrão,
  funciona com Postgres). Era a causa de "Authentication user provider [oracle] is not defined".
- **Deps acopladas** subidas junto (`-W`): `laravelcollective/html ^5.8`,
  `barryvdh/laravel-dompdf ^0.8.5`, `barryvdh/laravel-ide-helper`, `phpunit ^7.5`.
- **Cache compilado** (`bootstrap/cache/*.php`) limpo a cada salto (referenciava
  providers antigos, ex.: `Carbon\Laravel\ServiceProvider`).

## 3. Upgrade dos sistemas menores

- **monitoramento** 5.4 → 5.8: subiu `yajra/laravel-oci8:5.8.*` (mantido — ainda lê o ERP
  via conexão `oracle3`; a troca para ler o Postgres é tema da Fase 5/integração),
  `passport ^7.0`, `revisionable ^1.40`, `dbal ^2.13`, `dompdf`, `laravelcollective`.
  `--ignore-platform-req=ext-oci8` (Oracle não instalado no container).
- **api-app-gc** 5.6 → 5.8: `passport ^7.0`, sem conflitos.

## 4. Build de assets modernizado

Substituído **Gulp 3 + Laravel Elixir** (descontinuado ~2017) por **Laravel Mix** no
ERP e no monitoramento:
- Novo `webpack.mix.js` (compila `resources/assets/sass/app.scss` → `public/css`).
- `package.json` atualizado (scripts `dev`/`watch`/`prod` via `mix`).
- `gulpfile.js` removido dos dois.
- (api-app-gc já usava webpack/laravel-mix.)

## 5. Ajustes nos testes (compat. 5.4→5.8)

A resolução de `env()` em testes mudou entre 5.4 e 5.8 (`putenv` deixou de ser lido).
Os testes que injetavam tokens via `putenv` passaram a injetar também em `$_ENV`/`$_SERVER`
(monitoramento `SegurancaFase1Test`, api-app-gc `SegurancaFase1Test`). Comportamento de
produção inalterado — só o setup de teste ficou compatível com ambas as versões.

---

## Validação final (contra containers)
- **ctrl-web**: Laravel 5.8.38, Postgres, HTTP 200 (home/login), 10 testes OK + 1 skip, migrate idempotente.
- **monitoramento**: Laravel 5.8.38, HTTP 200, 5 testes OK.
- **api-app-gc**: Laravel 5.8.38, `/api/video/get` 200, 5 testes OK.

---

## Pendências / próximos passos
- **Salto para Laravel 6 LTS → 8+**: requer substituir as deps travadas no L5.x
  (`laravelcollective/html` → `spatie/laravel-html` ou Blade puro; `maatwebsite/excel 2.1`
  → 3.x reescrevendo exports; revisar `sped`/`boleto`). É um **projeto dedicado** com a
  rede de testes — recomendado após a Fase 5 (unificação) e migração de dados.
- **`build` de assets**: `npm install` + `npm run prod` é etapa de build-time (Node), a
  validar no pipeline de CI/deploy — a configuração (Mix) já está pronta.
- **monitoramento ainda lê o ERP via Oracle (`oracle3`)**: como o ERP agora é Postgres,
  essa leitura cruzada deve migrar para a conexão Postgres / API na Fase 5 (unificação),
  quando o `oci8` pode sair também do monitoramento.
- Helpers globais antigos (`array_get`, `str_random`, etc. — 37 usos no ERP) ainda
  funcionam no 5.8, mas serão removidos no 6.0 — tratar no salto futuro.

## Como reproduzir
```bash
# cada sistema, com containers no ar:
docker compose exec app php artisan --version   # Laravel 5.8.38
docker compose exec app vendor/bin/phpunit       # suíte verde
```

> Portão de saída da Fase 4: ✅ os 3 backends em Laravel 5.8 + PHP atual, sem código
> morto crítico, sem módulo em runtime obsoleto, build moderno, suítes verdes.
