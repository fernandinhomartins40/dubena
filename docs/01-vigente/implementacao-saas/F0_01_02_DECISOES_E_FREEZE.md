# F0-01/F0-02 — decisões e freeze operacional

**Estado:** CONCLUÍDO  
**SHA inicial:** `4d8a3f3560af2de765bb417cc1b66482ccb41adf`  
**Branch:** `main`  
**Data:** 2026-08-25

## Achados e referências relidos

- método mestre e Volume 15;
- fronteira atual por grupo, RLS fail-open, painel/serviço/job de migração e trava pós-cutover;
- testes que cristalizavam execução sem origem e liberação do ETL;
- plano F0 e protocolo de execução contínua.

## Arquivos-fonte lidos integralmente

- `app/Http/Controllers/Api/SuperAdmin/MigracaoController.php`
- `app/Services/Migracao/MigracaoService.php`
- `app/Jobs/ExecutarMigracaoJob.php`
- `app/Console/Commands/EtlRun.php`
- `app/Console/Commands/CutoverCheck.php`
- `app/Http/Controllers/Api/Admin/EmpresaController.php`
- `app/Http/Controllers/Api/SuperAdmin/EmpresaController.php`
- `app/Http/Requests/EmpresaRequest.php`
- `bootstrap/app.php`
- testes focais de migração, pós-cutover, empresa e relatório.

## Decisões

- D1–D4 do plano são o alvo ratificado para implementação.
- Até existir `TenantAccount` e mapa de titularidade comprovado, criar empresa pelo CRUD atual é inseguro porque preserva automaticamente a fronteira `grupo`.
- Carga real do ETL deve permanecer bloqueada; diagnóstico, mapeamento e dry-run podem continuar disponíveis.
- A contenção deve estar na API, no serviço central e no CLI. Esconder rota/tela não seria barreira.
- O default é bloqueado. A liberação requer variável explícita por ambiente.
- O teste de proteção pós-cutover continua existindo com freeze explicitamente desligado, pois valida uma barreira distinta.

## Implementação

- `config/saas_transformation.php` com:
  - `SAAS_FREEZE_MIGRATION_WRITES=true` por default;
  - `SAAS_FREEZE_COMPANY_CREATION=true` por default.
- `TransformationFreeze` e `TransformationFrozenException`.
- bloqueio antes de enfileirar migração no SuperAdmin;
- bloqueio no serviço para proteger chamadores diretos/jobs antigos;
- bloqueio no CLI `etl:run`, exceto `--dry-run`;
- bloqueio da criação de empresa no controller tenant;
- resposta HTTP 423 tipada com a operação bloqueada;
- exemplos de ambiente documentam o freeze em desenvolvimento, homologação e produção.

## Testes

Comando:

```text
php artisan test tests/Feature/SaasTransformationFreezeTest.php tests/Feature/MigracaoFerramentaTest.php tests/Feature/EtlTravaPosCutoverTest.php tests/Feature/EmpresaTest.php tests/Feature/RelatorioTest.php
```

Resultado: **32 testes aprovados, 94 assertions, 0 falhas, 315,92 s**.

Também executado `php -l` em todos os PHP alterados: zero erro de sintaxe. `git diff --check` sem erro material; apenas aviso de futura normalização CRLF→LF em `EtlRun.php`.

## Rollback

O freeze é reversível por configuração explícita, sem alterar dados:

```text
SAAS_FREEZE_MIGRATION_WRITES=false
SAAS_FREEZE_COMPANY_CREATION=false
```

Remover as classes/configuração restaura o comportamento anterior. Isso não deve ser feito antes dos gates F1/F7.

## Pendências

- titularidade/controlador das 12 empresas atuais ainda precisa de evidência jurídica/operacional;
- o freeze não corrige o plano de controle atual; apenas impede sua execução destrutiva;
- cutover real não foi executado;
- documentos e alterações de comodato preexistentes permaneceram preservados.
