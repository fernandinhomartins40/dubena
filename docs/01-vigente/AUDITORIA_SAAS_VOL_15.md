# Auditoria SaaS — Volume 15: consolidação, arquitetura-alvo e plano

**Estado:** FECHADO — consolidação dos Volumes 1–14, dos três apêndices de testes e do apêndice de infraestrutura.  
**Data:** 2026-08-25.  
**Fonte:** achados previamente demonstrados em código e dados nos volumes da auditoria. Este volume não transforma documentação antiga em evidência.

## Cobertura final

A auditoria cobriu o código próprio do `erp-novo`, excluindo dependências, artefatos gerados, locks, caches e o `.env` real:

| Recorte único | Arquivos não vazios | Linhas |
|---|---:|---:|
| Migrations | 118 | 9.802 |
| Models | 179 | 7.032 |
| Domínios | 193 | 24.865 |
| Núcleo ETL | 41 | 10.664 |
| API, Console, Providers, rotas e configuração backend | 154 | 22.675 |
| Seeders, factories e conversores externos | 37 | 3.911 |
| SPA e configuração frontend | 221 | 23.192 |
| Testes PHP | 186 | 28.615 |
| Infraestrutura operacional | 22 | 1.675 |
| **Total único** | **1.151** | **132.431** |

Além desses arquivos, seis placeholders vazios (`.gitkeep`) foram inventariados. Sobreposições deliberadas entre volumes — por exemplo rotas e testes ETL — foram removidas do total acima.

Os Volumes 1–14 registram **177 achados**, dos quais **87 de severidade ALTA**. Os apêndices de testes acrescentam 31 achados de contrato/cobertura, 12 ALTOS, e a infraestrutura acrescenta 9, 5 ALTOS. Achados dos apêndices frequentemente confirmam uma falha principal; por isso não são somados como se fossem 217 defeitos independentes.

A execução particionada cobriu toda a suíte PHP: **1.221 testes**, com **1.212 aprovados, 4 falhos, 5 pulados e 3.627 assertions**. As quatro falhas são as contradições de comodato documentadas em `T-02.04`; os cinco skips dependem de PostgreSQL/RLS. A SPA passou em **35/35 testes** e no typecheck.

## Conclusão executiva

O sistema contém muitos componentes corretos e reaproveitáveis, mas **ainda não é um SaaS seguro para receber uma segunda revenda independente nem possui uma ferramenta confiável de conversão/cutover**.

O problema central não é falta de módulos. É a coexistência de peças construídas com fronteiras e significados diferentes:

- `grupo`, empresa ativa, empresa padrão e empresas visíveis são usados alternadamente como tenant;
- RLS, scopes, jobs, controllers e SPA falham abertos ou dependem de o chamador lembrar do contexto;
- conceitos do negócio são inferidos por texto, flags e defaults da Dubena;
- estruturas novas corretas convivem com caminhos antigos sem substituí-los;
- dinheiro, fiscal, identidade e logística possuem mais de uma porta de mutação;
- integrações e credenciais alternam sem contrato entre plataforma, grupo e empresa;
- o ETL admite fonte ausente, execução parcial e falso sucesso;
- a suíte principal roda em SQLite e pula exatamente as garantias PostgreSQL/RLS mais críticas.

Consequentemente, adicionar colunas e filtros pontuais perpetuaria o desenho. A correção precisa começar pela fronteira comercial e pelo kernel de isolamento, depois substituir os vocabulários e portas de mutação, e só então migrar dados.

## Decisão D-1 — fronteira comercial do SaaS

**Decisão recomendada:** criar `TenantAccount` como fronteira rígida de contrato, faturamento, segurança e propriedade dos dados.

```text
Plataforma
└── TenantAccount — um titular/controlador dos dados
    ├── Assinatura, usuários, papéis e políticas
    ├── Organização/rede interna
    │   └── Empresa — CNPJ/estabelecimento operacional
    │       └── Unidade/StockLocation — depósito, loja, veículo, rota etc.
    └── Concessões explícitas de leitura/operação

Rede/franquia entre titulares independentes
└── vínculo comercial separado, sem ampliar RLS automaticamente
```

Regras decorrentes:

1. Empresas de proprietários independentes nunca compartilham `TenantAccount`.
2. `grupo` pode ser migrado inicialmente para uma organização 1:1 dentro do tenant, mas deixa de ser a barreira implícita de segurança.
3. Antes de preservar a visibilidade atual entre empresas de um grupo, deve-se comprovar o mesmo titular/controlador.
4. Uma franquia ou rede comercial entre donos diferentes usa vínculo próprio; compartilhar marca, distribuidora ou catálogo não concede acesso aos dados.
5. Toda tabela de negócio recebe `tenant_id`; tabelas operacionais também recebem `empresa_id`.
6. A empresa operacional de escrita é diferente do escopo de leitura multiempresa.
7. Acesso da plataforma ocorre por conexão/guard próprio e impersonação auditada, não por bypass `support` no fluxo tenant.

## Decisão D-2 — planos e licenciamento

A assinatura pertence ao `TenantAccount`. Limites e add-ons podem pertencer à empresa quando custo ou capacidade são locais, como emissor fiscal, rastreador, volume de documentos ou terminal.

Modelo mínimo:

- catálogo global e versionado de capacidades;
- plano como conjunto de entitlements e limites;
- assinatura com versão contratada, vigência e estados `trial`, `ativa`, `grace`, `suspensa` e `cancelada`;
- overrides temporários, com motivo, prazo e auditoria;
- ausência de assinatura/entitlement é fail-closed;
- mudança de plano invalida cache e publica evento de alteração;
- backend, jobs e API aplicam a licença; a SPA apenas reflete o resultado.

Para as empresas atuais, criar um plano transitório versionado `Legacy Full`, associá-lo explicitamente e só então remover o comportamento atual que libera tudo sem assinatura. O catálogo comercial definitivo deve ser decidido com dados de uso e custo, não inferido das 21 flags existentes.

## Nove causas-raiz

### 1. Identidade de tenant fragmentada

`grupo_id`, empresa ativa, empresa padrão, lista visível e filtro da SPA respondem a perguntas diferentes, mas são tratados como equivalentes. É a raiz das duas gerações de RLS, de relatórios da empresa errada, de jobs com outro alcance e do cache colidindo entre sessões.

### 2. Enforcement opt-in e fail-open

Sem tenant, scopes/policies podem liberar; controllers precisam lembrar de autorizar; rotas não usam os middlewares disponíveis; condições ABAC desconhecidas liberam; assinatura ausente libera módulos. Uma barreira SaaS não pode depender de disciplina local.

### 3. IDs globais usados como pertencimento

`exists:tabela,id` e buscas por conta, cliente, entregador, telefone ou recurso confirmam que o ID existe, não que pertence ao tenant. A mesma falha reaparece em comodato, caixa, financeiro, logística, telefonia e auditoria.

### 4. Vocabulário implícito da Dubena

Descrição, regex, capacidade fixa, flags e defaults de Guarapuava/São Paulo substituem enums, relações e políticas versionadas. O comportamento muda conforme o texto cadastrado e não sobrevive ao onboarding de outra operação.

### 5. Estrutura nova sem substituição da antiga

Licenciamento, inventário/fechamento, hierarquia organizacional, municípios IBGE, integração por tenant, duas frotas e modelos concorrentes de programa/cliente existem simultaneamente. Criar a estrutura não mudou a porta usada pelo fluxo.

### 6. Múltiplas portas de mutação

Baixa financeira, mudança de estado, identidade, posição e atos auditáveis possuem caminhos com guardas diferentes. A consistência depende do controller, ponte, job ou comando que chamou.

### 7. Credencial e configuração sem dono

Fiscal, bancos, pagamento, Google, Traccar, PABX e CONSISA alternam entre plataforma, grupo, empresa e variável global. Isso mistura quota, custo, revogação e raio de falha.

### 8. Conversão sem plano de controle

O mapa de empresas é persistido e ignorado, todo registry roda para qualquer origem, falhas parciais terminam como concluídas, invariantes são globais e scripts podem destruir o último espelho bom.

### 9. Teste verde não equivale a gate SaaS

RLS é pulada no SQLite, muitos testes usam `support`, regex substitui autorização comportamental, 501 conta como endpoint e há testes que protegem fail-open. A cobertura funcional é valiosa, mas não prova isolamento ou prontidão operacional.

## Arquitetura-alvo

### Contexto único e imutável

Toda request, job, comando ou operação administrativa recebe um `TenantEnvelope`:

```text
tenant_id
empresa_operacional_id
empresas_leitura_ids
user_id
permission_version
entitlement_version
impersonation_id (opcional)
correlation_id
```

A empresa operacional é obrigatória para escrita fiscal, financeira e de estoque. Aplicação e limpeza do contexto acontecem em `try/finally`. CLI sem contexto falha, exceto comandos de plataforma explicitamente marcados.

### RLS e isolamento

- ausência de contexto retorna zero linhas ou erro;
- policies usam funções SQL únicas para tenant e empresas autorizadas;
- `FORCE ROW LEVEL SECURITY` em todas as tabelas protegidas;
- runtime `NOSUPERUSER`, `NOBYPASSRLS` e sem ownership;
- operações de plataforma usam role/conexão separadas;
- allowlist global mínima e central;
- tabelas temporárias/backup com dados de cliente não ficam fora do catálogo;
- testes PostgreSQL reais exercitam leitura e escrita entre dois tenants.

### RBAC, ABAC e licença

RBAC responde se o usuário pode realizar a ação; licença responde se o tenant contratou o recurso. Ambos são obrigatórios e independentes. Rotas, catálogo, menus e ações devem nascer de um manifesto tipado único. `support` vira break-glass com motivo, prazo, escopo, 2FA e auditoria.

### Modelo de domínio

- **Contraparte:** entidade `Party` no tenant; papéis cliente/fornecedor/transportador como relações com vigência; regime fiscal e programas em entidades próprias.
- **Produto:** `kind = CONTENT | CONTAINER | PRODUCT | SERVICE | FEE`; casco e conteúdo relacionados explicitamente por especificação/compatibilidade; heurística textual somente sugere conversão.
- **Pedido:** estados ortogonais de pedido, fulfillment, pagamento e fiscal; participantes com papel explícito; transições idempotentes por uma porta única.
- **Estoque:** `StockLocation` tipado; ledger imutável como verdade; saldo materializado derivado; inventário/fechamento como rotina; custódia de casco em ledger patrimonial próprio.
- **Financeiro:** título separado de pagamento/liquidação; uma única porta idempotente de baixa; FITID e histórico de conciliação; contas e sequências bancárias por empresa.
- **Fiscal:** perfil por empresa e vigência; matriz tributária versionada; ausência de regra bloqueia emissão; XML usa o snapshot tributário resolvido integralmente.
- **Frota/logística:** um `Vehicle`; rastreador como vínculo substituível; posição escopada por empresa/jornada; cobertura independente de marketplace; parâmetros por empresa/perfil operacional.
- **Integrações:** `IntegrationAccount` com dono `PLATFORM | TENANT | COMPANY`, credencial cifrada, quota, custo, health e circuit breaker no mesmo escopo.

### SPA

- `QueryClient` separado para tenant e SuperAdmin;
- toda query key inclui identidade e escopo apropriados;
- logout/impersonação cancela requests e limpa cache antes da nova identidade;
- filtro persistido é qualificado por usuário+tenant;
- empresa operacional e escopo de leitura aparecem separadamente;
- branding, locale e moeda são configurações do tenant;
- testes E2E provam sessão A → logout → sessão B sem dado residual.

## Ferramenta de conversão-alvo

O código atual de transformação pode ser reaproveitado somente atrás de um novo plano de controle fail-closed.

Entidades mínimas:

- `ConversionRun` — tenant, empresas permitidas, fontes, versões e operador;
- `SourceSnapshot` — cópia imutável, manifesto, hashes, contagens e watermark;
- `MappingSet` — mapeamentos explícitos, versionados e aprovados;
- `StagingRecord` — dado bruto/normalizado com linhagem;
- `QuarantineRecord` — ambiguidade, erro e decisão humana;
- `InvariantResult` — resultado por run, tenant, empresa, fonte e regra;
- `CutoverPlan` — freeze, delta, switch, rollback, RTO/RPO e responsáveis;
- `EvidenceBundle` — manifesto final imutável de resultados e aprovações.

Fluxo:

```text
DRAFT → PREFLIGHT → DISCOVERED → MAPPING_REQUIRED → MAPPED
→ SNAPSHOTTING → STAGED → TRANSFORMING → RECONCILING
→ READY_FOR_REVIEW → READY_FOR_CUTOVER → FREEZING
→ APPLYING_DELTA → SWITCHING → POSTCHECK → COMPLETED
```

Estados bloqueantes incluem `BLOCKED_SOURCE`, `BLOCKED_MAPPING`, `BLOCKED_INVARIANT`, `FAILED_RETRYABLE`, `FAILED_TERMINAL`, `ROLLING_BACK` e `ROLLED_BACK`. Fonte indisponível, zero checks, lista vazia ou exceção interna nunca equivalem a sucesso.

Regras:

1. Snapshot bruto é imutável; nenhum `DROP` antes de validar a cópia nova.
2. `source_system + entity + source_pk` é chave de linhagem/idempotência.
3. O mapa tenant/empresa governa toda entidade e FK.
4. Ambiguidade de tenant, identidade, dinheiro ou fiscal bloqueia ou vai para quarentena aprovada; nunca usa empresa majoritária.
5. Staging e shadow target não escrevem diretamente na produção viva.
6. Lock exclusivo impede duas conversões sobre o mesmo destino.
7. Invariantes são segmentadas por run, tenant e empresa; soma global não pode compensar vazamento.
8. Cutover usa freeze ou journal/CDC, delta, switch atômico e rollback blue/green.
9. `COMPLETED` exige todos os módulos, fontes, invariantes, pós-check e aprovação.

## Plano por dependência

### F0 — contenção e decisões

- congelar onboarding de segundo tenant;
- ratificar D-1 e D-2;
- rotacionar credenciais versionadas;
- corrigir paths cross-tenant conhecidos, fake financeiro, logs globais e sobrescrita de segredos;
- tornar imagem, `APP_KEY`, Redis e variáveis de deploy fail-closed.

**Saída:** riscos exploráveis e deploy não determinístico bloqueados; titulares das empresas atuais mapeados.

### F1 — kernel SaaS

- criar `TenantAccount`, grants de empresa e `TenantEnvelope`;
- migrar/classificar tabelas por plataforma, tenant e empresa;
- unificar RLS fail-closed e roles;
- limpar contexto de jobs/workers;
- separar guard/conexão de plataforma.

**Gate:** PostgreSQL real, zero skip RLS, duas organizações adversariais e ausência de contexto negada.

### F2 — acesso, licença e auditoria

- manifesto único de rotas/permissões/recursos;
- RBAC e entitlement obrigatórios;
- ABAC default-deny;
- plano `Legacy Full` e transição de assinaturas;
- break-glass auditado no lugar de `support`.

**Gate:** matriz positiva/negativa por papel real, sem bypass, e empresa sem assinatura negada.

### F3 — vocabulário canônico

- Party/papéis, item/natureza/casco, estados ortogonais, StockLocation e Vehicle único;
- backfill com sugestão automática e fila humana;
- adaptadores de leitura legada temporários;
- bloquear novas inferências silenciosas por descrição/flag.

**Gate:** novas escritas usam somente o modelo canônico e toda ambiguidade fica registrada.

### F4 — dinheiro e fiscal

- porta única de liquidação/estorno;
- contas e sequências por empresa;
- matriz fiscal fail-closed e snapshot completo;
- reconciliar PIX, boleto, cartão, CNAB, caixa e documentos fiscais.

**Gate:** testes reais de homologação e reconciliações financeira/fiscal fechadas por empresa.

### F5 — integrações e operação

- proprietário explícito de credencial/quota;
- unificar frota/rastreador e parâmetros logísticos;
- ativar inventário/fechamento e autoria operacional;
- remover fallbacks globais em autenticação, dinheiro e fiscal.

**Gate:** nenhum driver fake inicia em produção e toda integração obrigatória passa healthcheck por tenant/empresa.

### F6 — conversão e onboarding

- plano de controle, snapshots, staging, mapeamentos, quarentena e invariantes;
- seed genérico sem IDs fixos; Dubena vira fixture separada;
- conversor idempotente por tipo de origem;
- ensaio completo de cutover e rollback.

**Gate:** falha parcial nunca conclui; rerun não duplica; quarentena bloqueante zero; evidence bundle aprovado.

### F7 — API e SPA

- validação tenant-aware de IDs;
- contratos tipados com status/schema/auth;
- cache segregado e limpeza de sessão;
- empresa operacional separada de leitura;
- branding/locale do tenant;
- desativação progressiva das pontes.

**Gate:** E2E multiusuário/multiempresa sem dado residual e testes adversariais de todos os IDs externos.

### F8 — segundo tenant piloto

Escolher operação com UF, nomenclatura, frota e integrações diferentes da Dubena. Executar shadow conversion, ensaio, rollback, cutover assistido e período de estabilização. Só depois liberar onboarding repetível.

**Gate final:** isolamento, licença, dinheiro, fiscal, estoque, integrações, conversão e operação aprovados por evidência executável, não por presença de tabela/tela.

## Bloqueadores para produção SaaS

Até F1/F2/F6, ficam bloqueados:

- onboarding de uma revenda independente;
- promoção do painel de migração como ferramenta confiável;
- cutover baseado nos gates atuais;
- uso de `grupo_id` como prova automática de mesmo dono;
- driver fake ou credencial global para dinheiro/fiscal/autenticação;
- declaração de isolamento baseada apenas na suíte SQLite;
- build/deploy de produção a partir do Dockerfile/Compose auditados sem correção.

## Evidências que devem virar gates

| Gate | Condição de aprovação |
|---|---|
| Tenancy | 100% das tabelas classificadas; contexto ausente nega |
| RLS | PostgreSQL/runtime real; zero skip; FORCE e policies unificadas |
| Jobs | dois tenants sequenciais no mesmo worker sem contexto residual |
| API | troca adversarial de cada ID externo não produz efeito cross-tenant |
| RBAC/licença | rotas em manifesto; papéis reais; sem assinatura = negado |
| SPA | A → logout → B sem cache, filtro, request ou renderização de A |
| Segredos | secret scan limpo; proprietário de toda credencial definido |
| Drivers | produção não inicializa com fake crítico |
| ETL | fonte/check ausente bloqueia; mapa aplicado; rerun idempotente |
| Cutover | snapshot, invariantes por tenant, quarentena zero e rollback ensaiado |
| Infra | imagem completa/imóvel; public servido pela mesma release; APP_KEY/Redis consistentes |
| Auditoria | toda ação de plataforma, impersonação, plano e conversão tem autor/motivo/antes/depois |

## Itens não verificados

A leitura estática está fechada, mas não foram executados:

- bancos Oracle/MySQL/PostgreSQL reais para uma conversão completa;
- suíte de release usando PostgreSQL com RLS e role runtime;
- integrações fiscais, bancárias, pagamentos, mapas e rastreamento reais;
- navegador E2E multiusuário para provar flashes/cache;
- build/deploy externo que pode existir fora dos arquivos auditados;
- ensaio destrutivo de cutover e rollback.

Esses itens não anulam achados determinísticos. Eles são gates obrigatórios das fases, não justificativa para considerar o sistema pronto.

## Fechamento

A auditoria solicitada está concluída. O resultado não recomenda reescrever tudo nem remendar achado por achado. Recomenda preservar os componentes corretos, introduzir uma fronteira SaaS explícita, direcionar todas as escritas para portas canônicas, converter os dados com evidência e remover os caminhos antigos somente depois da reconciliação.

O primeiro marco comercial não é white-label nem tabela de planos: é tornar impossível executar uma operação de negócio sem tenant, empresa operacional, autorização e entitlement inequívocos. O segundo é provar que uma base real pode ser convertida, reconciliada e revertida sem inferir dono, perder linha ou declarar falso sucesso.
