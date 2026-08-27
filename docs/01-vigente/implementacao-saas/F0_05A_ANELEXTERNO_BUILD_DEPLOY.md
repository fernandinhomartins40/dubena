# F0-05A — anel externo de build e deploy

**Estado:** CONCLUÍDO localmente; promoção remota pendente de execução controlada.
**Data:** 2026-08-26 (America/Sao_Paulo)

## Recorte confirmado

Foram lidos integralmente os workflows CI, homologação e produção, os dois
Composes promovidos, os wrappers, o rollback, os vhosts e parâmetros de proxy,
o Dockerfile e os arquivos de Nginx diretamente envolvidos. A evidência
reutilizada foi INF-08 e o diário F0-05.07/08. Não houve reauditoria ampla nem
alteração em `ctrl-web/`.

## Implementação

- CI constrói uma vez e publica `erpnovo-app` e `erpnovo-web` no GHCR com
  provenance, SBOM, relatórios Trivy/Syft e manifesto contendo os dois digests.
- Homologação e produção recebem apenas referências `@sha256`; não fazem build
  nem montam o checkout como código de runtime. A migration usa a conexão owner
  e a aplicação executa com a role runtime.
- Produção exige confirmação, release homologada e release de rollback
  explícitas, backup anterior à escrita, healthcheck e comparação dos image IDs
  em execução com os digests do manifesto.
- O rollback não escolhe uma tag anterior implicitamente e recusa manifestos ou
  imagens sem digest. O rollback automático só é elegível depois de a promoção
  ter sido iniciada.
- O proxy público usa TLS, redirecionamento HTTP→HTTPS, parâmetros explícitos e
  encaminha produção para 3130/3131; homologação permanece em 3120/3121.
- A imagem web passou a usar Nginx 1.29.8/Alpine 3.23 por digest. O APT da base
  PHP é resolvido no snapshot que acompanha seu digest, evitando variação do
  conjunto de pacotes entre builds.

## Gates executados

- `bash deploy/nginx/test-config.sh`: aprovado para os três vhosts, com
  certificados efêmeros e `nginx -t`.
- `bash -n` nos scripts externos e wrappers: aprovado.
- `actionlint` 1.7.7 por digest: aprovado para CI, homologação e produção.
- Build local do target `web` usando bases/snapshot fixados: imagem
  `erpnovo-web:f005a-gated` criada (sha256:8ae27fa…fc6cd).
- `nginx -t` dentro da imagem, com upstream resolvido: aprovado.
- Trivy 0.66.0 por digest, severidade `CRITICAL`, `--ignore-unfixed`: zero
  vulnerabilidades na imagem web final. A base anterior apresentava três.
- Servidor web efêmero retornou 200 e HSTS, CSP, X-Content-Type-Options,
  X-Frame-Options, Referrer-Policy e Permissions-Policy.
- `git diff --check`: aprovado; apenas avisos de EOL do checkout Windows.

O segundo build local concorrente encerrou com colisão de tag já existente após
o primeiro exportar a imagem. Isso não mascara um build incompleto: a imagem
resultante foi inspecionada e passou nos testes acima. Em CI a tag é única por
SHA e o job é único por release.

## Limite operacional remanescente

Não foi feito push, deploy, migration ou rollback em VPS/GHCR: isso requer o
workflow autorizado, o ambiente externo e uma release real. A execução remota
é o próximo ensaio operacional do pipeline, não uma aprovação presumida.

## Próximo microlote

F0-06: gerar catálogo vivo reexecutável do schema efetivo, RLS, modelos, jobs,
rotas, capabilities e integrações; em seguida F0-07 registra o baseline de
testes para fechar o gate F0.
