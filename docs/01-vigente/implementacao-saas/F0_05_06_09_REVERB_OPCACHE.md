# F0-05.06/09 — Reverb e OPcache

**Estado:** CONCLUÍDO  
**Data:** 2026-08-26 09:48 (America/Sao_Paulo)

## Implementação

- Produção e homologação exigem `BROADCAST_CONNECTION=reverb`, app id,
  chave pública e segredo de no mínimo 32 caracteres; ausência encerra com 78.
- O template de homologação agora declara o contrato completo do Reverb.
- A mesma imagem contém perfis OPcache separados. Antes de iniciar o processo,
  o entrypoint seleciona `validate_timestamps=0` em produção e `=1` nos demais
  ambientes. Promoção/rollback recria containers e processos.

## Gate executado

- imagem reconstruída: `sha256:7ad7922d0d95...`;
- Reverb ausente em produção: exit 78;
- contrato completo em produção e homologação: exit 0;
- `php -i` dentro do runtime: OPcache timestamps `Off` em produção e `On`
  em homologação;
- sintaxe shell e `git diff --check` aprovados.

Próximo: INF-07 (PostgreSQL/RLS real) e INF-08 (bases/digests/SBOM/promoção).
