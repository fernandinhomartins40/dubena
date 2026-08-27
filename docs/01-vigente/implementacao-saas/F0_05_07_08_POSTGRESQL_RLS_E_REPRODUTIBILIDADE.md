# F0-05.07/08 — PostgreSQL/RLS e reprodutibilidade

**Estado:** CONCLUÍDO  
**Data:** 2026-08-26 10:15 (America/Sao_Paulo)

## Recorte e evidência reutilizada

Foram reutilizados INF-07/INF-08 de `AUDITORIA_SAAS_INFRA.md` e o inventário de
migrations/testes já certificado. A releitura integral ficou limitada a
`RlsCoberturaTest`, migrations RLS finais, `ResolveTenant`, configurações de
banco, Dockerfile, Composes e scripts de promoção/rollback diretamente tocados.
Não houve nova auditoria ampla nem subagentes.

## INF-07 — gate PostgreSQL real

- Banco descartável PostgreSQL 15.14 criado isoladamente em Docker.
- Schema integral migrado pela conexão `pgsql_owner`.
- Testes executados pela role `erp_app`, confirmada como
  `NOSUPERUSER/NOBYPASSRLS`.
- `RlsCoberturaTest` deixou de tentar apagar/recriar schema pela role runtime;
  usa transações e prepara o cenário pela conexão owner.
- Migration `2026_08_28_000100_rls_fail_closed_sem_contexto.php` substitui o
  bypass anterior: GUC ausente/vazia agora nega leitura e escrita. Operações de
  plataforma/DDL devem usar conexão privilegiada explícita.
- Gate reutilizável: `composer test:pgsql-rls`, com `--fail-on-skipped`.

Resultado real: **6 testes, 346 assertions, zero falha e zero skip**. Foram
provados `ENABLE+FORCE`, policy em todas as tabelas classificadas pelo contrato
atual, negação de SELECT/UPDATE/INSERT cruzado e negação sem contexto.

## INF-08 — artefatos imutáveis e SBOM

- Bases de Node, PHP, Composer e Nginx fixadas por digest no Dockerfile.
- PostgreSQL 15.14, Redis e Nginx de homologação fixados por digest nos
  Composes.
- Runtime reconstruído: manifesto local
  `sha256:006b537b19d53663c4edfe8a8febc0550e8...`.
- Web reconstruído: manifesto local
  `sha256:534b697e56a69783a562dc37747cb82605ad...`.
- `docker/build-release.sh` publica app/web uma única vez com provenance e SBOM
  BuildKit, resolve os digests publicados, gera SPDX adicional com Syft fixado
  por digest e emite `release.env`.
- Prova local de SBOM: app com 351 linhas de pacotes e web com 66; ambos exit 0.
- Produção e homologação agora recebem `APP_IMAGE`/`WEB_IMAGE` obrigatoriamente
  por `@sha256`; não possuem build/bind mount de código no Compose promovido.
- Wrappers rejeitam referência mutável/placeholder com exit 78. Configuração
  válida por digest passou para os dois ambientes.
- `deploy/rollback.sh` seleciona manifesto auditável com os dois digests,
  valida/puxa os bytes existentes e sobe com `--no-build`.

## Validações

- `composer validate`: aprovado.
- `composer test:pgsql-rls`: 6/6, 346 assertions, zero skip.
- builds runtime e web por bases fixadas: aprovados.
- SBOM app/web: exit 0.
- `sh -n` de entrypoint, wrappers, build e rollback: aprovado.
- `docker compose config --quiet` produção/homologação: aprovado.
- wrappers com digest: exit 0; placeholder: exit 78.
- `git diff --check`: aprovado (somente avisos de normalização EOL).

## Limites e rollback

O digest do artefato local demonstra a construção, mas promoção real exige um
registry definido em `REGISTRY_PREFIX`; nenhum push/deploy externo foi feito.
Rollback de código não desfaz migration destrutiva. A migration RLS não reabre
o bypass em `down()` por decisão fail-closed.

Os containers e volume temporários `erpnovo-f005-*` foram removidos ao final;
containers preexistentes do usuário não foram alterados.

## Próximo passo

Executar F0-05A: fechar o anel externo (workflow inexistente no checkout,
TLS/headers/proxy, migrations, health e vulnerabilidades), preservando o gate de
mesma imagem por digest implementado aqui.
