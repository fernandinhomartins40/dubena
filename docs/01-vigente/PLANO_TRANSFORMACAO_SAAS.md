# Plano de transformação do Dubena em SaaS canônico

**Estado:** plano de implementação; nenhuma tarefa deste documento autoriza alteração automática de código ou dados.  
**Base:** leitura integral da auditoria SaaS, Volumes 1–15, apêndices de testes e infraestrutura.  
**Princípio central:** o produto SaaS define o modelo; cada cópia Dubena é uma origem a ser diagnosticada, mapeada, convertida, reconciliada e, quando ambígua, colocada em quarentena. O modelo SaaS nunca será deformado para reproduzir convenções acidentais da Dubena.

## 1. Resultado esperado

Ao final deste plano, uma revenda independente deve poder entrar no sistema sem compartilhar dados, credenciais, configurações, limites ou convenções com outra. Toda operação de negócio deve ter, de forma inequívoca:

1. o titular dos dados (`TenantAccount`);
2. a empresa operacional responsável pela escrita;
3. o conjunto explicitamente autorizado para leitura;
4. o usuário ou ator de plataforma;
5. a permissão para a ação;
6. o entitlement contratado;
7. a versão das regras relevantes;
8. uma trilha auditável e uma chave de idempotência quando houver efeito financeiro, fiscal, patrimonial ou de integração.

A cópia da Dubena só poderá ocupar esse modelo depois de passar por snapshot imutável, mapeamentos aprovados, transformação em staging, quarentena de ambiguidades, invariantes por tenant/empresa e ensaio de rollback.

## 2. Como usar este documento sem depender de memória

### 2.1 Regra de releitura obrigatória

Antes de iniciar qualquer tarefa `F<n>-<nn>`, o implementador deve:

1. reler integralmente as seções da auditoria indicadas na tarefa, incluindo evidência, impacto, direção e notas de cobertura;
2. abrir e ler integralmente cada arquivo-fonte citado pelo achado — intervalos de linha servem para localizar, não para limitar a leitura;
3. reler as migrations que criam e alteram as tabelas envolvidas, os models, traits, services, controllers/commands/jobs, rotas e testes que alcançam o fluxo;
4. procurar consumidores adicionais com `rg` pelo nome da classe, tabela, coluna, rota, evento e constante;
5. consultar novamente o schema e a cópia de dados; números e linhas da auditoria são fotografia de 2026-08-25, não verdade eterna;
6. registrar no diário da fase os arquivos realmente lidos, commit/SHA, consultas executadas, hipóteses confirmadas e hipóteses rejeitadas;
7. parar e dividir a tarefa quando o recorte crescer além do que pode ser lido integralmente. Busca, AST, cobertura e agentes ajudam no inventário, mas não substituem leitura;
8. confrontar comentário/documentação com execução. Comentário prova intenção, não comportamento;
9. não implementar uma “correção” se a evidência atual contradisser o achado. Registrar a divergência e revisar o plano primeiro.

Modelo obrigatório para o diário de cada fase:

```markdown
## Tarefa F?-?? — <nome>
- SHA inicial:
- Achados relidos integralmente:
- Arquivos lidos integralmente:
- Chamadores/consumidores inventariados:
- Consultas e resultados:
- Hipóteses confirmadas:
- Hipóteses rejeitadas ou ambíguas:
- Decisão aprovada e responsável:
- Migrações expand/migrate/contract:
- Testes e evidências:
- Plano e prova de rollback:
- SHA final:
```

O diário deve ficar em `docs/01-vigente/implementacao-saas/` e entrar no mesmo commit da fase. Checklist sem lista de arquivos e evidências não vale como leitura.

### 2.2 Fontes e precedência

Em caso de divergência, usar esta ordem:

1. comportamento medido em código + PostgreSQL/cópia atual;
2. schema efetivo, constraints, policies, roles e privilégios;
3. testes que exercitam o mesmo banco e a mesma role de runtime;
4. auditoria, como mapa para reencontrar a evidência;
5. documentação histórica, somente como intenção a confirmar.

### 2.3 Estratégia de mudança

Toda substituição estrutural segue `expand → convert/shadow → reconcile → switch → observe → contract`.

- Não remover nem reinterpretar coluna no mesmo deploy em que nasce o substituto.
- Novas escritas passam primeiro pela porta canônica; adaptadores legados ficam somente para leitura/transição.
- Dual-write só é permitido com idempotência, comparação automática e prazo de remoção.
- Backfill nunca inventa dado silenciosamente. Valor ambíguo vira sugestão ou quarentena.
- Migração destrutiva exige snapshot restaurável, ensaio e aprovação registrada.
- Cada etapa deve ser reversível até o `contract`; depois dele, rollback é restauração/cutover previamente ensaiado.

### 2.4 Armadilhas de interpretação que devem ser reconfirmadas

Estes pontos já produziram leitura incompleta ou conclusão plausível, porém errada. Sempre que uma tarefa os tocar, a releitura deve destacar explicitamente o que foi confirmado na versão atual:

- o banco é cópia de trabalho; preservar comportamento errado por medo de alterar “produção viva” inverte o objetivo;
- a flag `fornecedor` não identifica com segurança a direção de comodato; um filtro existente evitava uma acusação inicial, mas escondia milhares de recipientes legítimos;
- cabeçalhos intermediários de cobertura podem conservar números antigos; vale a cobertura final e, na implementação, vale o inventário atual recalculado;
- o Volume 11 declara 49 arquivos no cabeçalho e 48/48 no fechamento, embora preserve 5.314 linhas; recalcular o manifesto antes de usar a contagem como gate;
- procurar `BelongsToTenant` no arquivo não detecta herança de trait/classe abstrata; ler a hierarquia completa;
- RLS sem `FORCE` não significava vazamento medido para a role runtime atual, mas continua sendo falha de defesa em profundidade;
- `login_logs` e certas escritas com `empresa_id NULL` têm necessidade pré-tenant; não “corrigir” adicionando filtro que quebre autenticação/onboarding;
- a duplicação de endereço em `Empresa` é sincronizada e consciente; a duplicação em cliente não possui o mesmo contrato;
- cache geográfico global pode ser correto; credencial, quota, breaker e autoria globais não são automaticamente corretos;
- senha de certificado precisa existir em memória para assinar; o problema é ownership, proteção, validade e auditoria, não a mera decifragem no runtime;
- A-9.1 não reconsultou o banco naquele volume; confirmar se `clientes.endereco` continua vazio antes de alterar geocodificação;
- a regra bancária de fator de vencimento precisa de fonte FEBRABAN vigente; não tratar aproximação auditada como especificação oficial;
- comentários e testes atuais podem cristalizar fail-open, persistência de contexto ou cutover permissivo; teste existente não decide sozinho o contrato-alvo;
- `empresa_id` globalmente único na cópia pode esconder chaves lógicas incompletas; o ensaio deve usar IDs coincidentes entre fontes/tenants;
- estrutura correta porém vazia não deve ser recriada sem investigar por que foi abandonada; medir rota, UI, treinamento e consumidores antes de substituir;
- dado histórico impossível de provar recebe estado `UNKNOWN/NOT_RECONCILABLE` e evidência; não recebe um default conveniente.

## 3. Decisões arquiteturais que governam todo o trabalho

### D1 — Fronteira comercial e de dados

`TenantAccount` é a fronteira rígida de contrato, faturamento, segurança e propriedade dos dados.

```text
Plataforma
└── TenantAccount (um titular/controlador dos dados)
    ├── assinatura, usuários, papéis, políticas e integrações do tenant
    ├── organização/rede interna
    │   └── Empresa (CNPJ/estabelecimento operacional)
    │       └── StockLocation/Unidade (depósito, loja, pátio, veículo etc.)
    └── concessões explícitas, limitadas e auditadas

Rede/franquia entre titulares diferentes
└── vínculo comercial separado; nunca amplia RLS automaticamente
```

Regras não negociáveis:

- proprietários independentes nunca compartilham tenant;
- `grupo_id` não prova propriedade comum;
- compartilhar marca, distribuidora, tabela-modelo ou franquia não concede acesso a dados;
- toda tabela de negócio tem `tenant_id`; tabelas operacionais também têm `empresa_id` quando a autoria/efeito é empresarial;
- empresa de escrita e empresas de leitura são conceitos distintos;
- acesso de plataforma usa guard, role/conexão e auditoria próprios, nunca `support` como passe universal.

### D2 — Assinatura e capacidades

A assinatura pertence ao tenant. Add-ons e limites podem pertencer à empresa quando custo/capacidade são locais. O catálogo é global, tipado e versionado; planos são composições versionadas de capabilities. Ausência de assinatura ou entitlement é `deny`, não liberação.

As empresas atuais recebem explicitamente `Legacy Full` durante a transição. Isso conserva acesso sem transformar “ausência de assinatura” em regra do produto.

### D3 — Contexto único

Toda request, job, comando, evento assíncrono ou impersonação recebe um `TenantEnvelope` imutável:

```text
tenant_id
empresa_operacional_id
empresas_leitura_ids
actor_type + actor_id
permission_version
entitlement_version
impersonation_id (opcional)
correlation_id
```

Contexto ausente é negado com erro tipado e observável. Nunca é convertido em coleção vazia, `0`, skip ou sucesso. Operações genuinamente globais são declaradas como plataforma e usam caminho separado. Aplicação e limpeza sempre ocorrem em `try/finally`.

### D4 — Vocabulário canônico

- `Party` + papéis vigentes substituem flags acumuladas de cliente/fornecedor/transportador.
- `Item.kind = CONTENT | CONTAINER | PRODUCT | SERVICE | FEE` substitui inversões, regex e inferência por descrição.
- Pedido, fulfillment, pagamento e fiscal têm estados ortogonais.
- `StockLocation` tipado substitui `setores` usados como pátio, veículo e rota.
- Ledger imutável é a verdade de estoque; saldo é projeção; custódia de recipiente tem ledger patrimonial próprio.
- Existe um único `Vehicle`; rastreador é associação substituível.
- `IntegrationAccount.owner_type = PLATFORM | TENANT | COMPANY` define dono, quota, custo, revogação e raio de falha.

## 4. Bloqueios imediatos

Até os gates correspondentes serem aprovados:

- não cadastrar uma segunda revenda independente;
- não promover o painel atual de migração como ferramenta confiável;
- não executar cutover real;
- não considerar `grupo_id` autorização;
- não aceitar fake em produção para pagamento, fiscal ou autenticação;
- não declarar isolamento com a suíte SQLite;
- não reconstruir o espelho sobre a última cópia boa;
- não executar seeders operacionais/demo em produção;
- não publicar a infraestrutura auditada como artefato SaaS pronto.

## 5. Fases de implementação

## F0 — Contenção, inventário vivo e decisões

**Objetivo:** impedir dano imediato e transformar premissas comerciais em decisões verificáveis antes de alterar o modelo.

**Releitura obrigatória:** método mestre inteiro; Volume 15 inteiro; A-3.1–A-3.5; A-6.1–A-6.4; A-7.1–A-7.5; A-10.3–A-10.4; A-12.1–A-12.5 e A-12.17–A-12.19; A-14.3, A-14.5, A-14.9 e A-14.16–A-14.19; T-02.05–T-02.06; INF-01–INF-08. Reler integralmente todos os arquivos citados por esses achados e os entrypoints/configurações que os selecionam.

### Tarefas

- **F0-01 — Registro de decisões:** ratificar D1–D4, identificar juridicamente o titular/controlador de cada empresa atual e registrar quais visibilidades entre empresas são legítimas. Nenhuma inferência pela distribuição atual dos dados.
- **F0-02 — Freeze técnico:** bloquear por feature flag/controle operacional novas migrações, novo tenant independente e cutover; preservar apenas diagnóstico e leitura.
- **F0-03 — Segredos:** rotacionar credenciais versionadas, remover defaults perigosos, inventariar proprietário/consumidor de cada segredo e preparar remoção do histórico conforme procedimento seguro.
- **F0-04 — Vulnerabilidades conhecidas:** conter rotas/serviços cross-tenant já demonstrados, logs globais expostos, sobrescrita genérica de segredos e fallback fake. Correções temporárias devem ser marcadas e mapeadas à substituição canônica.
- **F0-05 — Infraestrutura fail-closed:** produzir imagem completa e imutável, servir o mesmo `public/` da mesma release, unificar fonte de ambiente, recusar APP_KEY/Redis inválidos, fixar dependências/digests e, na imagem imutável de produção, usar OPcache sem revalidação de timestamp com restart explícito na promoção.
- **F0-05A — Anel externo:** inventariar e ler integralmente o workflow externo de build/deploy, proxy TLS do host e locks/resolução efetiva de dependências que ficaram fora do recorte original; criar gates para TLS/HSTS/CSP, headers/proxy, migrations, healthchecks, vulnerabilidades e promoção da mesma imagem. Ausência desses artefatos deve ser registrada como bloqueio, não presumida como correta.
- **F0-06 — Catálogo vivo:** gerar inventário de tabelas, columns tenant/grupo/empresa, RLS/FORCE/owner, models, jobs, rotas, capabilities, integrações e credenciais. Versionar o resultado como evidência, não como fonte manual permanente.
- **F0-07 — Baseline:** executar testes atuais particionados, registrar as quatro falhas conhecidas de comodato e os cinco skips PostgreSQL, sem “corrigir o teste” antes de decidir o contrato.

**Entregáveis:** decisões assinadas; mapa proprietário→tenant→empresa; inventário vivo; credenciais rotacionadas; build imutável; relatório de baseline; lista explícita de contenções temporárias.

**Gate F0:** nenhuma credencial conhecida permanece válida; deploy limpo é reproduzível; produção não inicia com fake crítico; migração/cutover estão bloqueados; todas as empresas têm titular classificado ou estão marcadas `OWNERSHIP_UNRESOLVED`.

**Rollback:** reverter somente flags/artefato da contenção; rotação de segredo não é revertida para segredo exposto.

## F1 — Kernel SaaS: tenant, contexto e RLS

**Objetivo:** tornar impossível ler ou escrever negócio sem fronteira de tenant e empresa operacional inequívocas.

**Dependência:** F0.  
**Releitura obrigatória:** Volumes 1–3 inteiros; A-8.4; A-9.3–A-9.4; A-10.1–A-10.2; A-11.1–A-11.2; A-12.2–A-12.3 e A-12.16–A-12.17; T-01.1–T-01.4; T-03.2–T-03.4. Ler integralmente `app/Domain/Tenant/`, `ResolveTenant`, `User`, todas as migrations RLS, todos os jobs `ShouldQueue`, middleware/Kernel/bootstrap, models sem trait e testes de RLS.

### Tarefas

- **F1-01 — Schema raiz:** criar `tenant_accounts`, memberships, tenant-company, grants de leitura/operação e vínculo comercial de rede/franquia separado.
- **F1-02 — Classificação de tabelas:** classificar 100% como `PLATFORM`, `TENANT`, `COMPANY`, `DERIVED` ou `STAGING`. Toda exceção global precisa de justificativa e owner.
- **F1-03 — Expansão de chaves:** adicionar `tenant_id` e, onde necessário, `empresa_id` com FK/índice/NOT NULL diferido; nunca backfill por “empresa majoritária”.
- **F1-04 — TenantEnvelope:** substituir GUCs fragmentadas e contexto mutável por envelope único em HTTP, CLI, jobs, eventos e WebSockets.
- **F1-05 — Fail-closed:** ausência/invalidade de contexto nega com erro tipado; nunca retorna vazio/zero como se a consulta tivesse sido executada. Bypass de plataforma exige conexão/role separada e evento auditado.
- **F1-06 — RLS única:** funções SQL canônicas para tenant e grants, `USING` + `WITH CHECK`, `FORCE` em todas as protegidas, runtime sem ownership/superuser/bypass.
- **F1-07 — Jobs/workers:** captura, serialização, validação e limpeza em `finally`; opt-out somente para job de plataforma declarado. Testar dois tenants sequenciais no mesmo worker.
- **F1-08 — Pais e FKs:** eliminar herança silenciosa por consulta quando possível; constraints compostas ou validação estrutural devem impedir pai de outro tenant e filhos órfãos.
- **F1-09 — Objetos temporários:** proibir tabela de backup com dados fora do catálogo/RLS; fornecer mecanismo de staging com TTL, owner e policy.
- **F1-10 — Migração das empresas atuais:** criar tenants conforme titularidade aprovada, nunca copiando automaticamente a fronteira de `grupo`.

**Gate F1:** PostgreSQL real, role runtime real, zero skip RLS; duas empresas com IDs coincidentes em tenants distintos; contexto ausente negado; leitura e escrita cruzadas negadas em todas as classes; worker sem resíduo; 100% das tabelas classificadas e policies conformes.

**Rollback:** dual-read controlado entre chaves antigas/novas; shadow policies validadas antes do switch; snapshot de grants e mapeamentos.

**Não fazer:** trocar apenas `grupo_id` por `empresa_id`; serializar CSV como nova verdade; confiar em global scope como barreira; usar `withoutTenant()` em fluxo de negócio.

## F2 — Identidade, autorização, licença e auditoria

**Objetivo:** separar “quem pode”, “o que foi contratado” e “quem operou”, todos fail-closed.

**Releitura obrigatória:** A-1.9; A-2.2; A-2.11; A-3.4; A-10.3–A-10.10; Volume 12 inteiro; A-13.6; T-01.4; T-02.01, T-02.07–T-02.10; T-03.5–T-03.8. Ler rotas integrais, middleware, policies/Gates, catálogo SPA, services SaaS/Security/Audit, controllers SuperAdmin e todos os testes que usam `support`.

### Tarefas

- **F2-01 — Manifesto único:** definir action/resource/capability, schema de request/response, papel e escopo por rota; gerar bindings backend e catálogo frontend.
- **F2-02 — RBAC:** autorização comportamental em cada porta de mutação e leitura sensível; validações de FK tenant-aware; negar condição ABAC desconhecida.
- **F2-02A — Hierarquia de permissões:** decidir e testar a semântica de `herda_filhos`; implementá-la integralmente ou migrar/remover o campo. Persistir uma promessa sem enforcement fica proibido.
- **F2-03 — Licença:** catálogo e planos versionados, assinatura por tenant, add-ons/limites locais, estados e overrides temporários auditados; cache com versão.
- **F2-04 — Legacy Full:** associar explicitamente às empresas atuais antes de remover fail-open.
- **F2-05 — Break-glass:** substituir `support` por acesso temporal, motivo, escopo, 2FA, aprovação quando crítica e trilha de plataforma append-only.
- **F2-06 — Auditoria:** unificar ator, tenant, empresa, correlation/idempotency, antes/depois e motivo; separar login pré-tenant de eventos tenant sem expor logs globais.
- **F2-07 — Segurança:** lockout não pode cruzar tenants por NAT/e-mail; política de senha tem dono correto; TOTP impede replay; campos sensíveis usam catálogo default-deny.
- **F2-08 — Testes reais:** matriz positiva e negativa por papéis reais, sem `support`; teste de ausência de assinatura; troca adversarial de IDs em todas as rotas.

**Gate F2:** rota não catalogada falha CI; ausência de permissão ou entitlement nega em HTTP, job e service; nenhum teste principal depende de `support`; impersonação e mudança de plano têm trilha completa.

## F3 — Vocabulário e cadastros canônicos

**Objetivo:** eliminar regras inferidas das palavras, flags e defaults da Dubena.

**Releitura obrigatória:** A-1.1–A-1.6, A-1.8, A-1.10–A-1.13, A-1.15–A-1.17 e A-1.19; A-2.4–A-2.7 e A-2.9–A-2.10; Volumes 4, 5 e 9 inteiros; A-11.3–A-11.7; A-13.7–A-13.10; T-02.03–T-02.04 e T-02.09; T-03.9. Ler migrations/models/domínios/ETL/testes completos de cada conceito.

### Tarefas

- **F3-01 — Party:** criar papéis com vigência, identidade e regimes separados; endereço único normalizado com município IBGE e histórico; preservar texto derivado somente quando consumidor exigir.
- **F3-02 — Item:** introduzir `kind`; modelar recipiente, conteúdo, compatibilidade/capacidade e vínculo comercial. Regex em português gera apenas sugestão com confiança e evidência.
- **F3-03 — Snapshot de item:** pedido congela descrição, kind, custo e atributos fiscais relevantes no momento da venda.
- **F3-04 — Estados ortogonais:** separar order/fulfillment/payment/fiscal; transições tipadas, versionadas e idempotentes. Forma de pagamento nunca é status de entrega.
- **F3-04A — Estado de rota:** representar `EM_ROTA/SAIU_PARA_ENTREGA` por código/efeito explícito; uma ação operacional nunca procura por `LIKE` em português nem autocadastra situação para conseguir continuar.
- **F3-05 — Operações e canais:** substituir booleanos paralelos e colunas `*_app` por dimensões/relacionamentos extensíveis.
- **F3-06 — StockLocation:** tipar depósito, loja, pátio, veículo e demais locais; rota não é local de estoque. Converter `setores` somente por mapa aprovado.
- **F3-07 — Organização:** uma hierarquia ativa dentro do tenant; desativar a concorrente somente após medir consumidores e converter dados.
- **F3-08 — Geografia:** município IBGE como catálogo autoritativo; cidade/área de cobertura referenciam-no; eliminar três catálogos concorrentes por fases.
- **F3-09 — Vehicle:** consolidar as duas frotas; mapear IDs e dependências; rastreador vira vínculo, não identidade.
- **F3-10 — Configuração:** schemas tipados/versionados, defaults de plataforma semeados no onboarding e copiados para o tenant; nenhum literal Dubena/Guarapuava vira regra universal.
- **F3-11 — Escrita canônica:** bloquear novas flags/inferências; adaptadores legados são somente leitura e emitem telemetria de uso.

**Gate F3:** 100% das novas escritas usam entidades canônicas; ambiguidades estão em fila humana; teste com nomenclatura não portuguesa e operação distinta da Dubena; zero regra de negócio depende de descrição livre.

## F4 — Estoque, pedido e custódia de recipientes

**Objetivo:** garantir que mercadoria, conteúdo, recipiente, saldo e custódia representem eventos reais e reconciliáveis.

**Releitura obrigatória:** A-1.7 e A-1.18; A-2.5; Volume 4 inteiro; Volume 5 inteiro; T-01.6; T-02.04; migrations/models/services/controllers e os testes de comodato atualmente falhos.

### Tarefas

- **F4-01 — Ledger:** movimento imutável com tenant, empresa, local origem/destino, item, quantidade, unidade, evento causal, ator e idempotency key.
- **F4-02 — Projeção de saldo:** materializada e recalculável; divergência nunca é ajustada silenciosamente.
- **F4-03 — Inventário/fechamento:** ativar as estruturas existentes depois de reconciliar seu contrato; contagem por local/item, autoria, aprovação e ajuste rastreável.
- **F4-04 — Custódia:** ledger patrimonial por Party×recipiente; entrada e saída explícitas; `fornecedor` não decide direção.
- **F4-05 — Comodato por itens:** contratos/avaliações/movimentos multi-item, chaves únicas com tenant e porta única. Resolver conscientemente os dois contratos conflitantes revelados pelos quatro testes falhos.
- **F4-06 — Pedido:** porta única de transição e efeitos, participante vendedor/entregador explícito, canal tenant-aware e preço revalidado no momento aprovado.
- **F4-07 — Reconciliação Dubena:** comparar ledger proposto, saldos, pedidos, inventários e acumulados; diferenças históricas vão para evidência/quarentena, não para ajuste automático.

**Gate F4:** soma do ledger fecha por tenant/empresa/local/item; rerun não duplica; inventário gera divergência auditável; custódia não depende de texto/flag; quatro testes falhos são substituídos por contrato aprovado, não apenas tornados verdes.

## F5 — Financeiro, cobrança, pagamento e fiscal

**Objetivo:** uma única porta idempotente para dinheiro e um motor fiscal versionado que bloqueia quando não sabe.

**Releitura obrigatória:** A-2.3, A-2.5 e A-2.9; Volumes 6 e 7 inteiros; A-10.7; A-11.9–A-11.10; A-12.19; T-01.2 e T-01.5; T-02.02, T-02.05, T-02.08. Ler drivers/configurações/templates/XML/CNAB/OFX/controllers/jobs/testes integrais e confirmar requisitos legais vigentes antes de implementar.

### Tarefas

- **F5-01 — Ownership financeiro:** plano de contas modelo copiável, centro de custo, conta bancária, caixa, credencial e sequência por tenant/empresa conforme contrato aprovado.
- **F5-02 — Título e liquidação:** separar obrigação de pagamento; porta única para liquidar/estornar/agrupar, com transação, origem e idempotência.
- **F5-03 — Bancos:** nosso-número em namespace correto; CNAB por conta/empresa; retorno indexado, sem varrer toda a base nem casar substring; módulo 10, fator de vencimento e campos posicionais validados por vetores oficiais vigentes dos bancos/FEBRABAN, com entradas e resultados esperados independentes da própria implementação.
- **F5-04 — Conciliação:** persistir FITID e histórico de matching; exceções manuais auditadas.
- **F5-05 — Pagamentos:** credencial e webhook autenticado por IntegrationAccount; endpoint público é fail-closed em qualquer ambiente. Simulação ocorre somente em driver/harness explicitamente isolado, nunca pela remoção condicional da autenticação. Driver fake é impossível em produção.
- **F5-06 — Perfil fiscal:** emitente completo por empresa e vigência; ambiente explícito; certificado monitorado; segredo cifrado e rotacionável.
- **F5-07 — Matriz tributária:** linhas versionadas por operação, item/grupo, jurisdição e tipo de destinatário; ausência/ambiguidade bloqueia emissão.
- **F5-08 — Snapshot fiscal:** XML/DANFE/SPED consomem integralmente a resolução congelada, sem constantes de SP, CST ou alíquota inventada.
- **F5-09 — Homologação:** cenários por UF/regime/PF/PJ/entrada/saída/ST; validar com especialistas e ambientes oficiais atuais.
- **F5-10 — Reconciliação:** valores por título, pagamento, conta, caixa, documento e imposto fecham por empresa e período.
- **F5-11 — Semântica temporal:** confirmar no schema efetivo o tipo de vencimento e dos demais marcos; uniformizar limites inclusivos, timezone e comparações date/datetime em DRE, fluxo de caixa, relatórios e conciliações.

**Gate F5:** troca de ID cross-tenant não produz efeito; baixa concorrente ocorre uma vez; fakes bloqueados; ausência fiscal nega; XML e reconciliações aprovados por empresa.

## F6 — Operação, logística, integrações e tempo real

**Objetivo:** tornar parâmetros, credenciais, posições e autoria explicitamente pertencentes ao escopo correto.

**Releitura obrigatória:** Volume 8 inteiro; A-9.1–A-9.3 e A-9.5–A-9.10; Volume 11 inteiro; A-12.5, A-12.11 e A-12.13–A-12.15; A-13.7–A-13.8; INF-06. Ler serviços Google/Traccar/Overpass/ViaCEP/IBGE/PABX/CONSISA, jobs, canais/eventos e configurações inteiros.

### Tarefas

- **F6-01 — IntegrationAccount:** proprietário, credencial, quota, custo, finalidade, health, circuit breaker e auditoria no mesmo escopo.
- **F6-02 — Frota/rastreador:** Vehicle único, device mapping explícito, posição por empresa/jornada e quarentena para device desconhecido. Conectividade, movimento, ignição e infração/alerta são dimensões distintas; limiares e interpretação de sinal pertencem ao tenant/perfil do provedor.
- **F6-03 — Geocodificação:** endereço canônico; política de provedor, identificação, quota e cache por owner; falha observável.
- **F6-03A — Casos geográficos:** suíte sintética fora de Guarapuava, cobrindo bordas/vértices, eixos, hemisférios e antimeridiano; nomes de testes devem verificar suas próprias afirmações.
- **F6-04 — Logística:** algoritmos geométricos únicos e testados; parâmetros de praça por perfil/empresa; partida ausente é estado explícito.
- **F6-05 — Marketplace:** cobertura geográfica independente de flag de canal; pedido pendente e entregador sempre no tenant/empresa corretos.
- **F6-06 — Autoria:** atribuição automática, missão, vale, convênio, telefonia e demais atos guardam ator/regra/versão.
- **F6-06A — Dados e normalização legados:** substituir ocorrências de `iconv(...TRANSLIT)` pelo normalizador canônico testado; reparar/backfill/reindexar telefones NFWEB escritos fora do observer, preservando origem e evidência.
- **F6-07 — Tempo real:** nomes de canais carregam tenant/empresa; autorização do canal e teste de publicação/recepção cruzada; Reverb configurado explicitamente.
- **F6-08 — Falhas:** rede indisponível é diferente de recusa de negócio; métricas, retry e dead-letter preservam contexto.

**Gate F6:** nenhum segredo global acidental; health por owner; device desconhecido não aparece para tenant; eventos não cruzam canais; dois tenants usam provedores/configurações diferentes no mesmo ambiente.

## F7 — Plano de controle da conversão

**Objetivo:** transformar o esqueleto existente em conversor fail-closed, idempotente, retomável e probatório.

**Releitura obrigatória:** A-2.8; Volume 14 inteiro; todos os achados dos três apêndices de testes relacionados a ETL/cutover; scripts Python e seus chamadores; migrations/models `Migracao*`; todos os 28 migradores, invariantes, seeders, factories, comandos, job, service, controller e testes — integralmente, ainda que a tarefa pareça tocar apenas um deles.

### Tarefas

- **F7-01 — Entidades:** implementar `ConversionRun`, `SourceSnapshot`, `MappingSet`, `StagingRecord`, `QuarantineRecord`, `InvariantResult`, `CutoverPlan` e `EvidenceBundle`.
- **F7-02 — Estados:** máquina com estados felizes e bloqueantes; transição exige pré-condições e CAS/lock. `COMPLETED` não pode ser setado diretamente pelo job.
- **F7-03 — Snapshot:** fonte bruta imutável, manifesto nominal, schema, hashes, contagens, watermark e LOB integral; carga nova nunca derruba a última boa.
- **F7-04 — Registry:** pipeline por tipo/bundle de origem; nomes e dependências validados; lista vazia/`apenas` desconhecido falha.
- **F7-04A — Progresso:** medir por linhas/bytes/unidades declaradas e registrar cobertura de módulos; lista de migradores vazia nunca produz 100%.
- **F7-05 — Contexto:** mapa tenant/empresa aprovado é obrigatório e governa todas as entidades/FKs; destino declarado é respeitado.
- **F7-06 — Linhagem:** chave `source_system + entity + source_pk`; payload bruto/normalizado e versão de transformador; upsert idempotente.
- **F7-07 — Descartes/quarentena:** um registro por linha/decisão; ambiguidade de owner, identidade, dinheiro, fiscal, item ou GPS bloqueia ou exige aprovação humana.
- **F7-08 — Erros/checkpoints:** zero `catch` silencioso; falha tipada, retry seguro, checkpoint durável e estado parcial explícito.
- **F7-09 — Exclusão mútua:** lock por destino e idempotency key por execução; painel, CLI e job usam o mesmo serviço.
- **F7-10 — Invariantes:** por run, source, tenant e empresa; fonte/check ausente é inconclusivo/bloqueante; saldo ausente diverge; manifesto mínimo obrigatório.
- **F7-11 — Seed:** plataforma e catálogos usam namespace seguro; dados Dubena/demo viram fixtures separadas; nenhuma senha conhecida/default operacional.
- **F7-12 — Cutover:** freeze ou CDC/journal, delta, shadow target, switch atômico, pós-check, blue/green e rollback com RTO/RPO/responsáveis.
- **F7-13 — Evidência:** bundle imutável contém mapeamentos, checks, contagens, hashes, quarentena, aprovações, versões e resultado de rollback ensaiado.

### Máquina de estados mínima

```text
DRAFT → PREFLIGHT → DISCOVERED → MAPPING_REQUIRED → MAPPED
→ SNAPSHOTTING → STAGED → TRANSFORMING → RECONCILING
→ READY_FOR_REVIEW → READY_FOR_CUTOVER → FREEZING
→ APPLYING_DELTA → SWITCHING → POSTCHECK → COMPLETED
```

Estados de bloqueio/erro: `BLOCKED_SOURCE`, `BLOCKED_MAPPING`, `BLOCKED_INVARIANT`, `FAILED_RETRYABLE`, `FAILED_TERMINAL`, `ROLLING_BACK`, `ROLLED_BACK`.

**Gate F7:** fonte ausente, zero checks ou falha parcial nunca concluem; mapa altera efetivamente a carga; duas runs concorrentes são impedidas; rerun não duplica; snapshot anterior sobrevive; invariantes segmentadas fecham; rollback completo é demonstrado.

No fechamento dos testes de F7, migrar metadata PHPUnit de doc-comments para attributes/configuração suportada e remover/substituir testes-exemplo/tautológicos por contrato real; “teste presente” não conta como evidência.

## F8 — Conversão específica da cópia Dubena

**Objetivo:** adaptar os dados reais ao SaaS sem transformar suas convenções em regras universais.

**Dependência:** F1–F7 prontos em shadow.  
**Releitura obrigatória:** todos os Volumes 1–14 e apêndices antes do primeiro ensaio; para cada domínio, reler novamente o volume e os arquivos-fonte/transformadores diretamente envolvidos. Atualizar as consultas de perfil da cópia.

### Mapeamentos e decisões obrigatórias

| Origem/convenção Dubena | Destino SaaS | Tratamento |
|---|---|---|
| `grupo`, 12 empresas, uma operacional | TenantAccount/organização/empresa | mapear por titularidade comprovada; empresas vazias não definem fronteira |
| flags de `clientes` | PartyRole vigente | derivar sugestões; 36 sem papel e conflitos vão à revisão |
| `vasilhame_retornavel`, `natureza`, regex | Item.kind + compatibilidade | heurística apenas sugere; aprovação humana; conservar evidência |
| `pedidosituacoes` | estados ortogonais | mapa linha a linha; forma de pagamento nunca vira status |
| `pedidooperacoes`/canal app | operação/canal tipado | converter combinações; combinações impossíveis em quarentena |
| `setores` texto | StockLocation tipado | mapa humano; veículo/rota não entram automaticamente como depósito |
| estoque, fechamentos e físico | ledger/projeção/inventário | reconstruir e reconciliar; ausência histórica não é zero inventado |
| hierarquias concorrentes | organização canônica | escolher por evidência de uso e mapear relações |
| três catálogos de cidade | Município IBGE + referências | reconciliar código IBGE; texto ambíguo em quarentena |
| duas frotas | Vehicle + device link | tabela de correspondência explícita; não casar só por descrição |
| comodato acumulado/itens | ledger de custódia + contratos | preservar movimentos; contrato multi-item; divergência evidenciada |
| financeiro/caixa/CNAB/OFX | título/liquidação/conta | reconciliar por empresa; não casar substring/valor+data silenciosamente |
| matriz fiscal PF/PJ | regra fiscal versionada | transformar dimensões; falta de regra bloqueia |
| credenciais/configs | IntegrationAccount | atribuir owner explícito; segredo desconhecido não ganha fallback global |
| GPS sem device conhecido | QuarantineRecord | jamais atribuir à empresa majoritária |
| IDs preservados/seed | lineage + IDs novos | remapear todas as FKs; proibir colisão com bootstrap |

### Execução

- **F8-01:** capturar snapshot imutável e perfil de schema/dados.
- **F8-02:** criar tenant(s) e empresas somente conforme D1 e titularidade aprovada.
- **F8-03:** construir e aprovar MappingSet por domínio.
- **F8-04:** transformar em staging e shadow target, sem tocar ambiente ativo.
- **F8-05:** reconciliar contagem, soma, FK, ledger, dinheiro, fiscal, estoque e custódia por tenant/empresa.
- **F8-06:** resolver quarentena; toda resolução registra autor, motivo e regra reutilizável ou explicitamente específica da origem.
- **F8-07:** repetir do zero; o segundo resultado deve ser determinístico e idempotente.
- **F8-08:** ensaiar delta, switch e rollback cronometrados.
- **F8-09:** emitir EvidenceBundle e obter aprovações técnica, operacional, financeira/fiscal e do controlador dos dados.

**Gate F8:** zero quarentena bloqueante; reconciliações fechadas por empresa; nenhuma regra `dubena_*` está no kernel SaaS; rerun determinístico; rollback dentro do RTO/RPO.

## F9 — API e SPA sobre o modelo canônico

**Objetivo:** expor apenas contratos seguros e impedir resíduo de identidade no cliente.

**Releitura obrigatória:** Volumes 12 e 13 inteiros; T-02.07; T-03.5, T-03.8 e T-03.10; rotas, controllers, manifesto, hooks, stores, QueryClient, autenticação, SuperAdmin e testes completos.

### Tarefas

- **F9-01 — API:** resources/actions do manifesto, IDs tenant-aware, scopes de campo, schemas e status reais; 501 não conta como implementação.
- **F9-01A — Processamento em lote:** comandos/lookups grandes usam streaming/chunking e escopo explícito por tenant; é proibido carregar cadastros globais inteiros em memória.
- **F9-02 — Contexto visível:** empresa operacional e escopo de leitura são controles distintos; resposta informa contexto efetivo.
- **F9-03 — Cache:** QueryClients separados para tenant/plataforma; toda query key inclui identidade e escopo; logout/impersonação cancela requests e limpa cache antes da troca.
- **F9-04 — Persistência:** filtros qualificados por user+tenant; nenhum localStorage global de contexto operacional.
- **F9-05 — Mutação:** UI reflete autorização/entitlement do backend, mas backend continua sendo autoridade.
- **F9-06 — White-label/localidade:** branding, favicon, título, locale, timezone e moeda por tenant, com defaults de plataforma explícitos.
- **F9-07 — Pontes:** medir uso, substituir por contratos canônicos e remover gradualmente; falha HTTP não é estado de domínio.
- **F9-08 — E2E adversarial:** sessão A→logout→B, tenant→SuperAdmin, requests em voo, duas abas, cache persistido, troca de empresa e tentativa de IDs externos.

**Gate F9:** nenhum dado/filtro/request de A aparece em B; contratos tipados; todas as mutações críticas têm autorização negativa; branding/localidade não são Dubena globais.

## F10 — Piloto independente, cutover e remoção do legado

**Objetivo:** provar repetibilidade com uma operação que contradiga as convenções Dubena.

### Tarefas

- **F10-01 — Seleção:** escolher piloto com proprietário independente, outra UF, nomenclatura, frota, parâmetros e ao menos uma integração diferente.
- **F10-02 — Shadow:** executar F7/F8 sem atalhos e comparar diariamente.
- **F10-03 — Game days:** simular fonte indisponível, segredo revogado, worker reaproveitado, falha no delta, perda do provedor, rollback e restauração.
- **F10-04 — Cutover:** executar runbook aprovado, monitorar isolamento, dinheiro, fiscal, estoque, integrações e filas.
- **F10-05 — Estabilização:** período definido com métricas/SLOs e reconciliação diária.
- **F10-06 — Contract:** remover colunas, adaptadores, flags, policies e pontes legadas somente quando telemetria provar zero uso e backup/rollback estiverem aprovados.
- **F10-07 — Onboarding repetível:** transformar decisões reutilizáveis em catálogo/mapping template; conservar particularidades da Dubena apenas no adaptador dessa origem.

**Gate final:** segundo tenant independente opera sem acesso cruzado; assinatura limita corretamente; conversão e rollback são repetíveis; reconciliações fecham; zero dependência silenciosa de descrição/flag/default Dubena.

## 6. Gates transversais obrigatórios

| Gate | Condição objetiva |
|---|---|
| Tenancy | 100% das tabelas classificadas; contexto ausente nega; grants explícitos |
| RLS | PostgreSQL + role runtime; FORCE; policies canônicas; zero skip |
| Jobs | dois tenants sequenciais no mesmo worker, com limpeza comprovada |
| API | troca adversarial de cada ID externo não lê nem altera outro tenant |
| RBAC/licença | manifesto completo; papel real; sem assinatura = negado |
| SPA | A→logout→B sem cache, filtro, request ou render residual |
| Segredos | scan limpo; owner/rotação de toda credencial; sem defaults críticos |
| Drivers | produção não inicializa com fake crítico |
| Domínio | novas escritas canônicas; heurística nunca decide silenciosamente |
| Financeiro | idempotência, concorrência e reconciliação por empresa |
| Fiscal | ausência de regra bloqueia; snapshot/XML homologado |
| ETL | fonte/check ausente bloqueia; mapa aplicado; rerun não duplica |
| Cutover | snapshot, delta, checks por tenant, quarentena zero e rollback ensaiado |
| Infra | artefato imutável completo; mesmo public/SHA; APP_KEY/Redis consistentes |
| Auditoria | plataforma, impersonação, plano, conversão e atos críticos têm autor/motivo/antes/depois |

Gate reprovado bloqueia a fase dependente. Exceção não pode ser aprovada apenas por desenvolvedor; precisa de owner, prazo, compensação, risco e critério de remoção.

## 7. Matriz de rastreabilidade da auditoria

Esta matriz impede que um achado desapareça entre fases. Antes de fechar uma fase, reler os achados do intervalo e registrar o status individual (`corrigido`, `substituído`, `não aplicável com evidência`, `pendente`).

| Fonte | Destino principal |
|---|---|
| A-1.1–A-1.19 | F1, F2, F3, F4, F5 e F8 |
| A-2.1–A-2.11 | F1, F2, F3, F5 e F7 |
| A-3.1–A-3.10 | F0 e F1 |
| A-4.1–A-4.10 | F3 e F4 |
| A-5.1–A-5.7 | F3 e F4 |
| A-6.1–A-6.12 | F5 |
| A-7.1–A-7.13 | F5 |
| A-8.1–A-8.12 | F6 |
| A-9.1–A-9.10 | F3 e F6 |
| A-10.1–A-10.10 | F1 e F2 |
| A-11.1–A-11.11 | F1, F2, F3, F5 e F6 |
| A-12.1–A-12.19 | F0, F1, F2, F6 e F9 |
| A-13.01–A-13.10 | F3, F6 e F9 |
| A-14.1–A-14.23 | F0, F7 e F8 |
| T-01.1–T-01.10 | gates F1, F4, F5, F6 e F7 |
| T-02.01–T-02.11 | gates F0, F2, F3, F4, F5, F6, F7 e F9 |
| T-03.1–T-03.10 | gates F1, F2, F3, F5, F6, F7 e F9 |
| INF-01–INF-05, INF-08–INF-09 | F0 e gate operacional final |
| INF-06 | F6 e gate operacional final |
| INF-07 | F1 e gate operacional final |

## 8. Ordem de releases e dependências

```text
F0
└── F1
    ├── F2
    └── F3
        ├── F4
        ├── F5
        └── F6
            └── F7
                └── F8
                    └── F9
                        └── F10
```

F4, F5 e F6 podem avançar em paralelo depois de F1–F3, mas uma equipe não pode alterar o mesmo agregado/migration sem coordenação. F7 pode construir o plano de controle cedo, porém transformadores finais dependem dos modelos canônicos. F9 pode preparar infraestrutura de contratos/cache, mas não deve cristalizar DTOs legados.

Cada release deve conter mudanças coesas, migration reversível, testes, diário e atualização da matriz. O deploy ocorre a partir de artefato versionado; `main` só recebe fase cujo gate local passou. Push/deploy permanecem ações explícitas e auditadas.

## 9. Métricas de prontidão

- 100% das tabelas classificadas e protegidas conforme classe;
- 0 operação de negócio sem TenantEnvelope;
- 0 rota/mutação fora do manifesto;
- 0 entitlement fail-open;
- 0 driver fake crítico em produção;
- 0 inferência textual silenciosa em nova escrita;
- 0 segredo sem owner e política de rotação;
- 0 skip nos testes PostgreSQL/RLS do gate;
- 0 quarentena bloqueante no cutover;
- 100% das contagens/somas/FKs críticas reconciliadas por tenant e empresa;
- rerun de conversão com zero duplicação;
- rollback ensaiado dentro do RTO/RPO;
- sessão A→B com zero resíduo de cache/request/filtro;
- segundo tenant independente aprovado por evidência operacional.

## 10. Critério de conclusão do programa

O projeto não termina quando existem tabelas `tenant_accounts`, telas de plano ou uma execução ETL verde. Termina quando:

1. a fronteira de propriedade dos dados é estrutural e fail-closed;
2. autorização e licença são independentes e obrigatórias;
3. o domínio não depende das convenções da Dubena;
4. a cópia Dubena foi convertida para o modelo canônico com linhagem e reconciliação;
5. uma segunda revenda realmente diferente passou pelo mesmo processo sem alterar o kernel;
6. rollback, isolamento e ausência de resíduo foram demonstrados em ambiente equivalente ao real;
7. os caminhos antigos foram removidos com evidência de zero uso;
8. a matriz de todos os achados está encerrada e os diários permitem reconstruir cada decisão sem memória oral.

Até essas condições serem simultaneamente verdadeiras, o sistema é uma transformação em andamento, não um SaaS pronto para onboarding independente.
