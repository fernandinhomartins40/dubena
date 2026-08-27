# F0-04K — PIX fake fail-closed em produção

**Estado:** CONCLUÍDO  
**Data:** 2026-08-25 22:08 (America/Sao_Paulo)  
**Branch/SHA de referência:** `main` / `4d8a3f3`

## Evidência reutilizada

Foi reutilizado o cross-scan final de A-12.19; não houve nova auditoria ampla.
Foram reconfirmados integralmente somente o binding dos drivers, `PixService`,
`FakePixDriver`, `GoliveCheck`, configuração PIX e os testes focais.

## Risco confirmado

`PIX_DRIVER=fake` produzia BR Code sintético e podia ser selecionado em produção.
Além disso, um nome de PSP não implementado não podia ser aceito como gate real.

## Implementação

- o container recusa resolver PIX fake ou desconhecido em produção;
- `FakePixDriver` possui uma segunda defesa e recusa uso direto em produção;
- o driver passou a ser resolvido apenas na porta de emissão de `PixService`:
  webhook não emite cobrança e não deve falhar ao construir o serviço;
- `golive:check` falha para PIX habilitado com fake e para PSP desconhecido;
- produção executa o gate de go-live em modo estrito automaticamente;
- dev/CI continuam podendo usar o fake de forma explícita.

## Validação

`php artisan test tests/Feature/PixDriverTest.php tests/Feature/PixWebhookFailClosedTest.php tests/Feature/GoliveCheckTest.php`

- 21 testes aprovados;
- 48 assertions;
- zero falha.

`vendor/bin/pint --test` nos seis arquivos do recorte: aprovado.  
`git diff --check` no recorte: aprovado.  
Busca por instanciação/resolução de `FakePixDriver`: somente binding e testes.

## Rollback

Reverter apenas as barreiras de binding/driver/go-live e a resolução lazy em
`PixService`. Isso reabre cobrança sintética em produção e, portanto, só é
aceitável em rollback acompanhado de `PIX_ENABLED=false` verificado.

## Pendência canônica

Ainda não existe PSP PIX real implementado neste build. A integração real e as
credenciais por `IntegrationAccount` permanecem para F5/F6; até lá produção deve
manter PIX desabilitado e não consegue emitir cobranças PIX, de forma explícita.
