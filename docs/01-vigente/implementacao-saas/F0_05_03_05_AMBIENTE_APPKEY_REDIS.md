# F0-05.03/04/05 — ambiente, APP_KEY e Redis fail-closed

**Estado:** CONCLUÍDO  
**Data:** 2026-08-26 09:45 (America/Sao_Paulo)

## Implementação

- `docker/compose-production.sh` exige arquivo externo legível e `GIT_SHA`, e
  sempre usa o mesmo arquivo em `docker compose --env-file`.
- Em produção/homologação, o entrypoint exige `vendor/`, APP_KEY base64 de
  exatamente 32 bytes e senha Redis base64url com pelo menos 32 caracteres.
- O entrypoint nunca cria/altera `.env` nesses ambientes e encerra com 78 quando
  o contrato falha. Desenvolvimento preserva geração local explícita.
- Produção e homologação configuram servidor e cliente Redis pela mesma fonte.
  O segredo entra como Docker secret, gera config efêmera 0600 e não aparece
  nos argumentos, labels nem no Compose renderizado.
- Healthcheck Redis é autenticado; app aguarda Redis saudável.

## Gates executados

- sintaxe dos dois scripts aprovada em `sh -n` dentro de Alpine;
- Compose de produção e homologação renderizados sem erro;
- imagem runtime reconstruída: `sha256:f76afa6562d9...`;
- APP_KEY ausente: exit 78; senha Redis curta: exit 78; válidos: exit 0;
- Redis real: healthy, `PING` anônimo = `NOAUTH`, autenticado = `PONG`;
- valor do segredo ausente do Compose renderizado;
- projeto/volume Docker temporários `erpnovo_f005test` removidos após a prova;
- `git diff --check` aprovado (somente aviso CRLF preexistente do Git).

Não houve deploy, push ou uso de segredo real.

Próximo: INF-06/09 (contrato Reverb e OPcache) e depois INF-07/08.
