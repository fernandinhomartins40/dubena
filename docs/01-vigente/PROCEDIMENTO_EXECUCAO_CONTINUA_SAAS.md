# Procedimento de execução contínua da transformação SaaS

**Objetivo:** permitir implementação autônoma, econômica e retomável de `PLANO_TRANSFORMACAO_SAAS.md`, sem depender da memória da conversa e sem perder trabalho em compactação, limite de uso, reinício ou ausência do usuário.

## 1. Contrato de execução

O trabalho é conduzido por um objetivo durável do Codex. O alvo é concluir F0–F10 e seus gates, não apenas produzir alterações. A unidade de execução é o **microlote**, composto por uma a três tarefas fortemente relacionadas.

O processo não interpreta “não parar” como autorização para ignorar segurança, alterar escopo, apagar dados, sobrescrever mudanças do usuário ou declarar sucesso sem prova.

## 2. Ciclo obrigatório de cada microlote

1. **Retomar:** ler `implementacao-saas/ESTADO_ATUAL.md`, `PLANO_TRANSFORMACAO_SAAS.md` e o diário da fase.
2. **Checar workspace:** registrar branch/SHA e `git status`; identificar alterações preexistentes e arquivos proibidos.
3. **Releitura:** cumprir integralmente o bloco “Releitura obrigatória” e o protocolo da seção 2 do plano.
4. **Inventário:** localizar schema, models, chamadores, jobs, rotas, eventos, SPA, configuração e testes do recorte.
5. **Hipóteses:** registrar o que foi confirmado, rejeitado, mudou desde a auditoria ou depende de decisão.
6. **Desenho:** definir expansão, conversão/shadow, switch, contract, testes e rollback antes de editar.
7. **Implementação:** alterar somente o recorte aprovado; preservar compatibilidade transitória de forma explícita.
8. **Validação:** executar testes focais, estáticos e, quando o gate exigir, PostgreSQL/role runtime, integração ou E2E real.
9. **Revisão:** usar segundo agente somente-leitura em mudança de alto risco; corrigir ou registrar divergências.
10. **Checkpoint:** atualizar diário, matriz e `ESTADO_ATUAL.md`; registrar comandos/resultados e próximo passo exato.
11. **Commit:** criar commit coeso apenas quando possível separar com segurança das mudanças preexistentes. Nunca incorporar arquivo alheio por conveniência.
12. **Continuar:** escolher o próximo microlote desbloqueado; não aguardar confirmação para trabalho local seguro já autorizado.

## 3. Política de agentes e consumo

### Modo econômico obrigatório a partir de 2026-08-25

Os volumes de auditoria, seus apêndices e os cross-scans já concluídos formam o
**índice durável de evidências**. Eles não devem ser auditados novamente a cada
microlote. A expressão “releitura obrigatória” do plano passa a ser executada de
forma incremental e verificável:

1. consultar no volume apenas o achado e as referências do recorte atual;
2. ler integralmente somente os arquivos de produção que serão alterados, seus
   chamadores diretos e os testes contratuais correspondentes;
3. ampliar a leitura apenas quando `rg`, o diff ou um teste revelar outro
   consumidor real;
4. registrar no diário quais evidências antigas foram reutilizadas e quais
   trechos mutáveis foram reconfirmados;
5. fazer uma recertificação ampla uma única vez no gate da fase, sem repetir a
   auditoria linha a linha já documentada.

Cada microlote econômico contém **uma contenção ou um contrato coeso**. Testes
focais são executados durante a implementação; suíte ampliada apenas no
fechamento do microlote e suíte integral apenas no gate da fase ou após mudança
transversal. Saídas volumosas são gravadas/filtradas, não reenviadas ao contexto.

Subagentes ficam desabilitados por padrão. Só podem ser usados quando o agente
principal já reduziu a pergunta a no máximo cinco arquivos independentes e a
economia de tempo superar claramente o custo de contexto. Nesse caso:

- usar no máximo um subagente;
- não herdar a conversa inteira; fornecer um pacote autocontido e curto;
- proibir nova auditoria ampla e releitura dos volumes completos;
- preferir revisão somente-leitura de um diff já pronto;
- não iniciar subagente quando o serviço tiver informado janela de limite ativa.

Uma falha de subagente por limite não deve ser repetida na mesma janela. O
agente principal continua serialmente ou encerra um checkpoint íntegro.

### Padrão

- um agente principal implementador;
- zero ou um revisor somente-leitura;
- no máximo dois agentes auxiliares adicionais quando os recortes forem independentes e não editarem os mesmos arquivos;
- sem força-tarefa para uma fase inteira;
- sem repetir a auditoria integral em cada agente: fornecer somente volumes, tarefas e arquivos necessários ao recorte.

### Usar agente auxiliar para

- inventário fechado de consumidores;
- revisão de migration/RLS;
- matriz de testes adversariais;
- execução e classificação de falhas;
- reconciliação independente de um domínio;
- revisão do diff sem editar.

### Não paralelizar

- migrations fundacionais e chaves de tenancy;
- `TenantContext`/`TenantEnvelope`, `ResolveTenant` e policies RLS;
- a mesma porta financeira/fiscal;
- o mesmo agregado ou conjunto de arquivos;
- tarefas em que uma decisão redefine a outra.

## 4. Controle de limite e janela do Codex

O agente não possui acesso garantido ao contador da assinatura ou ao horário da próxima janela. Portanto:

1. consultar o estado/orçamento do objetivo nos checkpoints quando o ambiente expuser essa informação;
2. nunca estimar percentuais ou horário de renovação sem dado fornecido pelo produto;
3. se uma chamada retornar limite e `Retry-After`/horário real, registrar em `ESTADO_ATUAL.md`:
   - instante da interrupção;
   - valor informado;
   - `resume_after` calculado diretamente desse valor;
   - comando/tarefa exatos de retomada;
4. confiar no objetivo durável para continuar entre turnos; um temporizador local não consegue reativar sozinho um agente sem suporte do produto;
5. antes de qualquer interrupção previsível, priorizar checkpoint pequeno e íntegro em vez de iniciar alteração ampla;
6. quando o orçamento exposto for insuficiente para concluir um microlote com validação, não abrir o microlote: finalizar documentação do atual e preparar o seguinte;
7. depois da retomada, verificar workspace e testes antes de continuar, pois processos externos podem ter alterado o estado.

## 5. Uso eficiente de contexto

- Referenciar arquivos duráveis em vez de recontar toda a conversa.
- Reutilizar inventários certificados e matrizes de achados; não refazer buscas
  integrais já concluídas sem evidência concreta de drift.
- Passar ao contexto somente excertos com linha e conclusão; resultados grandes
  devem ser filtrados por erro, resumo e contagem.
- Um prompt deve nomear tarefas, escopo, evidência, testes e condição de parada uma única vez.
- Saídas grandes de ferramentas devem ser filtradas/segmentadas, sem confundir busca com leitura integral obrigatória.
- O diário conserva decisões; o agente seguinte não precisa redescobri-las, mas precisa reconfirmar código mutável.
- Testes focais durante edição; suíte ampla no gate da fase.
- Revisão completa somente em mudanças de alto risco e fechamento de fase.

## 6. Segurança e autonomia

Autorizado dentro do workspace e do plano:

- ler integralmente código, schema, dados de cópia, configurações permitidas e testes;
- criar/editar código, migrations, testes e documentação;
- executar ferramentas locais, containers e bancos de desenvolvimento/cópia;
- criar checkpoints e commits coesos quando não incorporarem mudanças alheias;
- usar rede e integrações de homologação quando credenciais, destino e efeito estiverem confirmados.

Exigem verificação específica antes de executar:

- operação destrutiva em banco ou arquivos;
- rotação/revogação de segredo externo;
- push que acione deploy;
- migration/cutover em ambiente remoto;
- envio de mensagem ou mudança em serviço externo;
- qualquer ação cujo destino não possa ser provado como cópia, desenvolvimento ou homologação autorizada.

## 7. Definição de checkpoint recuperável

Todo checkpoint contém:

- fase e tarefas;
- estado: `PREPARANDO`, `IMPLEMENTANDO`, `VALIDANDO`, `REVISANDO`, `CONCLUÍDO` ou `BLOQUEADO`;
- SHA/branch inicial e atual;
- arquivos preexistentes intocáveis;
- arquivos lidos e alterados;
- decisões/evidências;
- testes executados e resultados;
- rollback;
- pendências e próximo comando;
- limite/janela somente se efetivamente informado pelo ambiente.

## 8. Critério de bloqueio

Uma falha de teste, implementação difícil ou orçamento temporariamente indisponível não é bloqueio arquitetural. Antes de registrar bloqueio, devem ser esgotadas as frentes seguras e independentes. Bloqueio legítimo exige decisão comercial/jurídica, credencial/serviço externo indispensável ou risco destrutivo cujo destino não possa ser confirmado.

## 9. Critério final

O objetivo só pode ser marcado concluído quando:

- F0–F10 estiverem encerradas;
- todos os gates executáveis tiverem evidência;
- a matriz de rastreabilidade não tiver achado sem destino;
- conversão Dubena e segundo tenant piloto estiverem reconciliados;
- rollback tiver sido ensaiado;
- caminhos legados previstos tiverem sido removidos ou mantidos com justificativa e prazo;
- pendências externas não forem apresentadas como sucesso.
