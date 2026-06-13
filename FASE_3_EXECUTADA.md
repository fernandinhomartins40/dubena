# Fase 3 (Migração Oracle → PostgreSQL) — Registro de Execução

> Execução da Fase 3 do `PLANO_MODERNIZACAO_ECOSSISTEMA.md` no **ctrl-web (ERP)**,
> validada contra o container Docker local (PostgreSQL 15) e protegida pela rede de
> testes da Fase 2. **Produção intocada** (mudanças no clone).
> Data: 2026-06-13.

---

## Resultado

| Métrica | Antes | Depois |
| --- | --- | --- |
| Banco | Oracle 11g | **PostgreSQL 15** |
| Migrations rodadas | 9 / 625 (travado) | **625 / 625** ✅ |
| Tabelas no schema | — | **214** |
| `php artisan migrate` | erro dbal `adsrc` | **"Nothing to migrate"** (idempotente) |
| App HTTP | 500 (sem schema) | **200** (home/login) |
| Suíte de testes | 6 OK (sem banco) | **10 OK** (contra Postgres) + 1 skipped |

---

## 1. Bloqueio fundamental: doctrine/dbal

- **Sintoma:** `column pg_attrdef.adsrc does not exist` — o `dbal 2.4` usa uma coluna do
  catálogo Postgres removida no PG 12+.
- **Correção:** atualizado para `doctrine/dbal ^2.13` (→ **2.13.9**), que mantém a API 2.x
  (compatível com Laravel 5.4) e suporta PG 12+. Sem isso, nenhum `->change()` rodava.

## 2. Helper de migração portável

Criado `app/Helpers/MigrationHelper.php` para centralizar conversões por driver, mantendo
as migrations legíveis e portáveis (Oracle/MySQL/Postgres):
- `toBoolean()` — cast int→boolean com `USING` (Postgres exige).
- `toDecimal()` — cast texto→numeric com `USING` + normalização de vírgula.
- `addBinary()` — Oracle `BLOB` → Postgres `bytea`.
- `addLongText()` — Oracle `CLOB` → Postgres `text`.
- `setNullable()` — Oracle `MODIFY ... null` → Postgres `ALTER COLUMN ... DROP/SET NOT NULL`.
- `oracleOnly()` — DDL exclusivo do Oracle (ex.: `NLS_SORT`) vira no-op nos demais.

## 3. Padrões de incompatibilidade traduzidos

| Padrão (Oracle/MySQL) | Problema no Postgres | Correção |
| --- | --- | --- |
| `boolean('ativo')->change()` | sem cast implícito int→bool | `MigrationHelper::toBoolean` (USING) |
| `ADD coluna BLOB / CLOB` | tipos Oracle | `bytea` / `text` via helper |
| `MODIFY coluna ... null` | sintaxe Oracle | `ALTER COLUMN ... DROP NOT NULL` |
| `ALTER SYSTEM SET NLS_SORT` | exclusivo Oracle | no-op (`oracleOnly`) |
| `dropForeign('NOME_FK')` (nome fixo) | Postgres nomeia FK diferente | `dropForeign(['coluna'])` + try/catch |
| **Identificadores em MAIÚSCULAS/CamelCase** | Postgres é case-sensitive | minúsculas (61 arquivos corrigidos em lote) |
| `USER_TAB_COLUMNS` (dicionário Oracle) | não existe no Postgres | reescrito com `Schema::hasColumn()` |
| `decimal('valor', 15, 4)->change()` (texto→num) | sem cast implícito | `MigrationHelper::toDecimal` (USING) |
| `unsignedInteger('x', 4)` | 2º arg = autoIncrement (bool) → conflito NULL/NOT NULL | removido o arg espúrio |
| `increments('id')->primary(...)` | dupla PK | removido `->primary()` redundante |
| `count($model)` em seeder | fatal no PHP 7.4+ | `! is_null($model)` |

### Migrations/arquivos tocados (principais)
- Helper: `app/Helpers/MigrationHelper.php` (novo).
- Boolean/BLOB/CLOB/MODIFY/NLS: `alter_empresas_grupo_table_ativo`, `alter_empresas_table_add_logo`,
  `alter_users_table_add_foto`, `alter_colaborador2_table`, `alter_empresas7_table`,
  `alter_empresa_grupos2_table`, `alter_nls_sort`, `alter_produtos14_table`,
  `alter_veiculos_colaborador_id_null_table`, `alter_ligacoestelefonicas_changetelefone`,
  `alter_colaboradorcomissaos_add_app_fields`.
- FK por nome: `alter_tipopessoa1_table`, `alter_contas_table1`, `alter_financeiros1_table`,
  `alter_contamovimentos4_table`, `alter_contamovimentoestornos1_table`.
- Case (lote): 61 migrations com `Schema::table/create/...('Nome')` → minúsculas.
- Dicionário Oracle: `alter_table_produtos4` (reescrita com `Schema::hasColumn`).
- Estrutura: `create_sped_contribuicoes_credito_table` (2º arg int), `create_nfoperacaoprodutoconvenios_table` (dupla PK).
- Seeder: `PisCofinsCredTableSeeder` (`count()` PHP 7.4).

## 4. Rede de testes (Fase 2) validou a migração

A suíte `CaracterizacaoFase2Test` (cripto do certificado, conversão de moeda BR,
arredondamento/truncamento fiscal) **passou 100% contra o Postgres migrado** — provando
que a mudança de banco **não alterou as regras fiscais/financeiras**. Era exatamente o
propósito da rede de proteção.

### Testes reativados (estavam skipped na Fase 2)
- `NotificationTest` → teste de integração do schema (`androids` existe e é consultável no PG).
- `MobileAppRoadTest` → valida que a consulta do `MobileRepository` roda **sem erro de SQL** no PG.
- `GettingLatLongTest` → permanece skipped (depende de **Google Maps externo**, não de banco).

## 5. Estado final dos 3 sistemas (validado)
- **ctrl-web** (PostgreSQL): 10 testes OK, 1 skipped. Migrate idempotente. HTTP 200.
- **monitoramento**: 5 testes OK.
- **api-app-gc**: 5 testes OK.

---

## Pendências / próximos passos
- **Seeds de dados base**: o `db:seed` completo ainda falha em violações de FK ao
  re-executar (idempotência de dados, não de schema). Não bloqueia a Fase 3 (que é de
  schema). Refinar seeders idempotentes quando popular dados de homologação.
- **Migração de DADOS reais** (ETL Oracle→Postgres, anonimizado) é etapa à parte, na
  preparação do staging — aqui validamos o **schema** e a **compatibilidade de código**.
- **Provider Oracle** (`Yajra\Oci8`) permanece registrado em `config/app.php` (inócuo com
  driver pgsql). Pode ser removido no upgrade de framework (Fase 4).
- **NLS_SORT** (ordenação acento-insensível) vira, no Postgres, `unaccent`/collation —
  configurar quando necessário para buscas.

## Como reproduzir
```bash
cd ctrl-web
docker compose up -d
docker compose exec app php artisan migrate --force   # 625/625
docker compose exec app vendor/bin/phpunit            # 10 OK, 1 skipped
```

> Portão de saída da Fase 3: ✅ ERP roda em PostgreSQL, migrate completo, suíte de
> caracterização verde. Pronto para a **Fase 4 (upgrade incremental Laravel/PHP)**.
