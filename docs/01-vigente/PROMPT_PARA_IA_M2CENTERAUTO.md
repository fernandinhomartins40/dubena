# Prompt para a IA responsável pelo m2centerauto

> Copie o bloco abaixo e envie para a IA/desenvolvedor que cuida do
> **m2centerauto**. Ele é autocontido: traz os dados observados, o diagnóstico e
> o que precisa ser verificado, sem depender de contexto do nosso projeto.

---

Olá. Compartilhamos a mesma VPS (Hostinger KVM 4 — 4 vCPUs, 16 GB RAM,
Ubuntu 22.04) com vários outros projetos. A **Hostinger limitou a CPU do
servidor a 20%** por excesso de consumo, e ao diagnosticar encontramos um
problema no stack do **m2centerauto** que precisa da sua atenção.

Não é uma acusação: cada projeto aqui contribuiu de algum jeito, e nós já
estamos corrigindo o nosso. Mas o item abaixo é o de maior impacto imediato e
está fora do nosso alcance.

## O que foi observado (2026-09-14, via SSH na VPS)

```
$ uptime
load average: 293.82, 293.62, 279.84       ← para 4 vCPUs

$ docker ps -a --filter name=m2centerauto
m2centerauto-backend-1        Restarting (137) 32 seconds ago
m2centerauto-gateway-1        Up 2 weeks (unhealthy)     nginx:1.27-alpine
m2centerauto-frontend-1       Up 2 weeks (unhealthy)
m2centerauto-plate-scraper-1  Up 2 weeks (unhealthy)
m2centerauto-alpr-1           Up 2 weeks (unhealthy)
m2centerauto-postgres-1       Up 4 months (unhealthy)    postgres:16-alpine
```

## Problema 1 (crítico) — `m2centerauto-backend-1` em crash-loop infinito

O container está reiniciando continuamente há pelo menos várias horas — foi
observado com "Restarting (128)" numa medição e "Restarting (137)" em outra,
cerca de 40 minutos depois. Ou seja: **não sobe, morre, e o Docker o relança sem
parar**.

Cada reinício paga o custo de bootstrap do Node inteiro. Num servidor já
saturado, um crash-loop consome CPU continuamente **sem entregar absolutamente
nada** — o serviço está fora do ar o tempo todo e ainda assim queimando recurso.

**Os códigos de saída dizem o que aconteceu, e eles mudaram entre as medições:**

- **137** = `128 + 9` → o processo levou **SIGKILL**. Na esmagadora maioria dos
  casos em container, isso é **OOM kill**: o processo pediu mais memória do que
  podia e o kernel (ou o limite do container) o matou.
- **128** = saída inválida/anômala, comum quando o processo morre antes de
  estabelecer um código próprio.

A troca de 128 para 137 sugere que o container está morrendo por **falta de
memória**, e não por um erro de lógica no código — o que muda completamente a
correção necessária.

### O que pedimos que você investigue

```bash
# 1. Por que ele morre — a causa está aqui
docker logs --tail 200 m2centerauto-backend-1

# 2. Confirmar se é OOM (o campo OOMKilled é a resposta definitiva)
docker inspect m2centerauto-backend-1 \
  --format '{{.RestartCount}} | exit={{.State.ExitCode}} | OOM={{.State.OOMKilled}} | limite={{.HostConfig.Memory}}'

# 3. Confirmar pelo lado do kernel
dmesg -T | grep -i 'killed process\|out of memory' | tail -20
```

### O que precisa ser feito

**Primeiro, o alívio imediato (por favor, faça isto assim que puder):**

```bash
docker stop m2centerauto-backend-1
```

Parar um serviço que já está 100% fora do ar não causa nenhuma indisponibilidade
nova — ele não está atendendo ninguém de qualquer forma. Só interrompe a queima
de CPU. Isso devolve recurso para a VPS inteira **imediatamente**, e dá tempo
para investigar com calma.

**Depois, a correção de fato — três frentes:**

1. **Resolver a causa do OOM.** Se o processo estoura memória, ou há vazamento,
   ou o volume de trabalho cresceu além do que o dimensionamento comporta. Vale
   olhar especialmente processamento em lote, filas acumuladas e imagens
   carregadas em memória (o stack tem ALPR e scraper de placas — reconhecimento
   de imagem é candidato natural a picos de memória).

2. **Declarar limites de memória no compose.** Sem limite explícito, um container
   com vazamento consome RAM até o kernel matar *algum* processo da máquina — que
   pode ser o dele ou o de um vizinho:

   ```yaml
   services:
     backend:
       deploy:
         resources:
           limits:
             memory: 512M      # ajuste ao consumo real medido
   ```

3. **Trocar a política de reinício.** Hoje ele relança para sempre. Com
   `on-failure:5`, o Docker desiste após 5 tentativas em vez de queimar CPU
   indefinidamente:

   ```yaml
   restart: on-failure:5
   ```

   Um serviço que falhou 5 vezes seguidas não vai se curar na sexta — vai só
   custar CPU até alguém olhar.

## Problema 2 (importante) — 5 dos 6 containers estão `unhealthy`

Incluindo o **postgres, unhealthy há 4 meses**. Isso merece verificação por dois
motivos:

- se o healthcheck está **certo**, os serviços estão degradados há muito tempo e
  ninguém foi avisado;
- se o healthcheck está **errado** (mal configurado, testando o endpoint errado,
  timeout curto demais), ele está rodando de graça a cada intervalo, em todos os
  containers, e ainda por cima mascara problemas reais — o dia em que algo
  quebrar de verdade, o sinal não vai significar nada.

Em qualquer dos dois casos há trabalho: ou consertar o serviço, ou consertar o
healthcheck. "Unhealthy há 4 meses" não é um estado aceitável para nenhum dos
dois.

```bash
docker inspect m2centerauto-postgres-1 \
  --format '{{json .State.Health}}' | head -c 2000
```

## Problema 3 (verificar) — o stack ainda é necessário?

As imagens estão todas na tag `eaa925f-20260826135333`, de **26 de agosto** — e
o backend está fora do ar. Se o projeto foi descontinuado ou está pausado, a
melhor otimização é simplesmente:

```bash
docker compose -p m2centerauto down
```

Isso libera CPU, RAM e espaço em disco de uma vez. Se ainda está em uso, ignore
este item — mas vale confirmar, já que ninguém percebeu o backend fora do ar.

## Contexto que ajuda a dimensionar a urgência

A VPS está com **disco em 81% (161 GB de 200 GB)** e **87 containers ativos de 9
projetos diferentes** em 4 vCPUs. A limitação de CPU imposta pela Hostinger
afeta **todos os projetos do servidor**, inclusive o seu — e a redefinição
manual do limite já foi usada esta semana, então não há como simplesmente
resetar. O limite só sai quando o consumo normalizar.

Por isso o pedido: **o `docker stop` do backend em crash-loop é o passo de maior
efeito e menor risco disponível agora**, e é quase instantâneo.

Se precisar de qualquer dado adicional da VPS para investigar, é só pedir.

Obrigado!
