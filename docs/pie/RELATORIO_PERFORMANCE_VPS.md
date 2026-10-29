# Relatório de desempenho e VPS

**Data da medição:** 2026-10-29 ~02:50 UTC · **Ambiente:** VPS de homologação (`72.60.10.108`), só leitura · **Base:** [BANCA_R5_SRE.md](BANCA_R5_SRE.md)

Os números abaixo foram **medidos** pelo R5 com comandos registrados no parecer. Não
houve carga sintética: a homologação estava ociosa, então não há p95 sob uso real.
Não há "antes e depois" de desempenho, porque nenhuma correção de desempenho foi
aplicada nesta execução.

## 1. Medições

| Recurso | Valor medido |
|---|---|
| CPU | 4 vCPU, load 0,93 |
| RAM | 16 GB, 9,2 GB disponíveis; swap em uso: 300 MB |
| Disco | 60 % usado (79 GB livres); pressão de I/O 7 % em 5 min |
| OOM kills (14 dias) | 18, todos no memcg de **outra** aplicação (ffmpeg); nenhum global |
| erp-novo (7 contêineres) | ~660 MiB no total; db 302 MiB, 6,8 % de CPU |
| Banco | 3,6 GB, sendo 1,06 GB do schema `legado` (sem índices) |
| Postgres | `shared_buffers` 128 MB, `work_mem` 4 MB, `jit` on, timeouts 0; hit de cache 93,9 %; 690 GB de temp files acumulados (atribuir ao ETL é hipótese); sem `pg_stat_statements` |
| Agendador | `sync-positions`, `pix:expirar`, `gerar-missoes`: 0,3–2 s, sem sobreposição; 0 FAIL em 24 h |
| HTTP aquecido | `/up` ~20 ms, `/api/health` ~40 ms; nenhum 5xx no nginx |

## 2. Gargalo confirmado por medição

**Contagem sob RLS nas listagens paginadas (PIE-28).** Como `erp_app`, com o tenant
configurado em transação read only:

| Consulta | Tempo |
|---|---|
| `count(*)` de pedidos | 3,5 s (7,97 s a frio) |
| `count(*)` de financeiroparcelas | 7,8 s |
| `count(*)` de clientes | 409 ms (27 ms sem RLS) |

O `paginate()` do Laravel faz essa contagem em toda página; o padrão aparece em 13
controllers (ex.: `PedidoController.php:50-56`). A armadilha já está descrita no
`CLAUDE.md` e **não** foi mitigada nessas listagens. A correção mexe no contrato de
paginação da SPA (`total`/`last_page`) e por isso ficou para um lote próprio, com
medição antes e depois.

## 3. Riscos de disponibilidade e dados

| ID | Risco | Situação |
|---|---|---|
| PIE-12 | Backup do deploy de produção apontava para a homologação | **Corrigido** no script; não executado (a produção não existe na VPS) |
| PIE-13 | Nenhum backup da homologação, que guarda dados reais | Script corrigido; **instalar o cron depende de aprovação** |
| PIE-27 | Corrida no `bootstrap/cache` compartilhado: 45 `routes-v7.php Failed to open stream`, 1 ParseError, 2 comandos agendados falhando no mesmo segundo. O queue recicla de hora em hora (RestartCount=300) e regenera o cache a cada partida | Pendente (entrypoint) |
| PIE-29 | Contêineres sem limite de memória (teto teórico de 16 GB com 16 FPM × 1 GB) numa VPS com ~75 contêineres; sem healthcheck em app/web/queue/scheduler/reverb; 135 `failed_jobs` (133 `NotificarEstoqueBaixoJob`) sem alerta; `laravel.log` sem rotação | Pendente (infraestrutura compartilhada) |

## 4. Hipóteses não medidas

- Ajustar `shared_buffers` para ~2–4 GB e criar índices no schema `legado` (ou movê-lo para fora do banco de runtime) reduziria os temp files. Sem `pg_stat_statements`, não há como provar.
- O `jit` ligado pode custar mais do que rende nas consultas curtas de OLTP.

## 5. Impacto em outras aplicações

A VPS é compartilhada. O erp-novo usa pouco hoje (~660 MiB), mas sem limites um pico
de FPM pode competir com os outros ~68 contêineres. Os OOM kills observados são de
outra aplicação.
