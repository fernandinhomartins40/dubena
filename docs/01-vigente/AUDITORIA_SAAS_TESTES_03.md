# Auditoria SaaS — Testes 03 — Feature N–Z

> **Status:** FECHADO — 100% do recorte lido (43/43 arquivos; 7.995/7.995 linhas).  
> **Recorte:** `erp-novo/tests/Feature`, arquivos PHP cujo nome começa de N até Z, inclusive.  
> **Fonte:** código atual e execução segura da suíte; documentação não foi usada como evidência.

## Resultado executivo

Foram registrados **10 achados: 3 ALTOS, 6 MÉDIOS e 1 BAIXO**. A suíte é ampla — 336 métodos — e contém bons casos adversariais de tenant, idempotência, autorização, PIX, rastreamento e fidelidade de relatórios. Ainda assim, ela institucionaliza três contratos perigosos: cutover verde sem fonte legada, visibilidade da rede inteira baseada apenas em `grupo_id` e retenção do tenant após o job. RLS/PostgreSQL continua fora da execução padrão; verificações de RBAC/rotas dependem de regex; e 23 dos 43 arquivos usam `support=true`, contornando o caminho normal de papéis.

## Método

- Cada arquivo foi lido integralmente, do início ao fim, sem amostragem.
- Inventário e linhas foram recalculados diretamente do sistema de arquivos.
- Os 43 arquivos foram executados juntos com `php artisan test`: **333 passaram, 3 foram pulados, 941 assertions, 330,03 s**. Os três skips são exatamente os métodos de `RlsCoberturaTest`, porque a conexão padrão é SQLite.
- Critérios canônicos: **C1 — conceito ausente; C2 — classificação por texto; C3 — flag como proxy; C4 — convenção não declarada; C5 — conceitos misturados; C6 — escopo de tenant errado**. Risco que não cabe honestamente neles é identificado como transversal.

## Achados

### T-03.1 — ALTA — C4 — a suíte exige que o cutover libere sem fonte nem invariantes reais

**Evidência:** `tests/Feature/RelatorioTest.php:131-143` diz explicitamente que, após `etl:run`, os demais migradores ficam com invariantes vazias porque o legado não está disponível e exige exit `0` de `cutover:check`.

**Impacto SaaS:** a regressão correta seria recusar um gate inconclusivo; o teste atual faz CI defender o falso positivo “PORTÃO LIBERADO” quando quase nada foi comparado.

**Direção:** exigir manifesto mínimo de checks, fonte obrigatória e estado inconclusivo distinto de sucesso; executar cenário com origem real/staging.

**Vínculo:** confirma diretamente A-14.9 e A-14.14.

### T-03.2 — ALTA — C4/C6 — testes transformam `grupo_id = um único cliente SaaS` em verdade operacional

**Evidência:** `tests/Feature/RedeFiliaisTest.php:15-25` declara grupo como rede de um dono; `:111-139` exige que o usuário da matriz veja clientes de matriz e filial, mesmo após trocar a empresa ativa. `tests/Feature/VisibilidadeRedeTest.php:96-123` repete que a listagem deve mostrar a rede inteira por padrão.

**Impacto SaaS:** o schema não impõe que todas as empresas do grupo pertençam ao mesmo titular jurídico. Se onboarding agrupar revendas independentes, esses testes protegem o vazamento em vez de detectá-lo.

**Direção:** modelar explicitamente organização/owner e concessão de visibilidade; testar grupo com dois titulares e negar por padrão.

**Vínculo:** reforça A-2.2, A-3.4 e a decisão de negócio pendente sobre a fronteira grupo/tenant.

### T-03.3 — ALTA — C6 — teste de job exige que o tenant permaneça no container depois do `handle`

**Evidência:** `tests/Feature/PushAssincronoTest.php:64-76` limpa o contexto, executa o job e então exige que `TenantContext` continue com empresa 42/grupo 7. Não há `finally`/limpeza exercitada.

**Impacto SaaS:** workers reutilizam processo e conexão; o job seguinte pode herdar tenant/GUC de outro cliente. O teste torna a ausência de teardown comportamento esperado.

**Direção:** encapsular aplicação do tenant em `try/finally`, restaurar/limpar contexto e GUCs, e testar dois jobs sequenciais de empresas diferentes.

**Vínculo:** confirma e torna executável o risco descrito em A-10.2.

### T-03.4 — MÉDIA — C4/C6 — o “guardião 100%” de RLS é pulado no banco padrão e duplica a allowlist

**Evidência:** `tests/Feature/RlsCoberturaTest.php:9-21` promete cobertura total, mas `:40-46` pula a classe inteira fora do PostgreSQL. A allowlist é copiada manualmente em `:27-38` e usada para excluir tabelas da verificação em `:78-82`.

**Impacto SaaS:** CI SQLite não prova policies, FORCE RLS, role nem GUCs; drift simultâneo ou divergente entre migration e cópia do teste cria ponto cego justamente na segunda barreira de isolamento.

**Direção:** job PostgreSQL obrigatório no CI e allowlist única compartilhada/inspecionada do schema; falhar se a suíte RLS for pulada no gate de release.

**Vínculo:** complementa A-3.2, A-3.3, A-3.4 e T-01.1.

### T-03.5 — MÉDIA — C2 — cobertura de autorização é inferida por regex e não enxerga o que foge do padrão

**Evidência:** `tests/Feature/RbacContratoTest.php:126-170` reconhece permissões apenas por literais `modulo.acao` cujo verbo esteja numa lista fechada. `tests/Feature/RotasSpaProtegidasTest.php:37-58` examina somente `<Route>` que já usa `p(...)`; uma rota nova que não use o helper fica fora da captura.

**Impacto SaaS:** chave dinâmica, verbo novo, autorização em middleware/service ou página criada sem `p` pode escapar e manter a suíte verde. O teste classifica segurança pelo formato textual do código.

**Direção:** manifesto tipado único gerando catálogo/rotas/menu, inspeção da tabela real de rotas e matriz HTTP por papel/ação.

**Vínculo:** reforça A-12.1 e A-13.06.

### T-03.6 — MÉDIA — C3 — 23 de 43 arquivos usam a flag `support` como proxy de autorização completa

**Evidência:** exemplos em `NfEntradaApiTest.php:23-29`, `RelatoriosPreGoLiveTest.php:31-41`, `TelefoniaTest.php:32-42`, `TempoRealTest.php:42-51` e `ViagensRotaTest.php:31-35`; a varredura integral encontrou `support=true` em 23 arquivos. Em geral, o cenário funcional testa sucesso pelo bypass e apenas um caso sem permissão testa 403, sem papel positivo real.

**Impacto SaaS:** CRUD/relatório pode funcionar apenas para suporte, com papel normal incompleto ou permissão errada, e ainda assim quase toda a suíte permanecer verde.

**Direção:** helper de usuário com papel mínimo real; reservar `support` para testes específicos do bypass e parametrizar sucesso/negação por perfil.

**Vínculo:** confirma A-2.2, T-01.4, T-02.10 e a lacuna de perfis apontada em A-13.06.

### T-03.7 — MÉDIA — C4 — webhook PIX sem autenticação fora de produção é contrato protegido

**Evidência:** `tests/Feature/PixWebhookFailClosedTest.php:159-167` exige que, fora de `production`, ausência de token e HMAC ainda confirme uma cobrança.

**Impacto SaaS:** homologação com dados/copias reais ou ambiente marcado incorretamente aceita confirmação forjada. A segurança depende de uma convenção de deploy, não da capacidade explicitamente habilitada.

**Direção:** fail-closed em qualquer ambiente; liberar simulação somente por driver fake e endpoint/test fixture não público.

**Vínculo:** risco transversal de segurança coerente com o fail-open de drivers em A-12.19.

### T-03.8 — MÉDIA — C5 — pontes misturam falha HTTP com status de domínio e os testes cristalizam HTTP 200

**Evidência:** `tests/Feature/PonteLegadoTest.php:17-23,126-149,207-217` exige HTTP 200 para validação, revenda divergente e recurso não encontrado, deslocando o erro para `OK/NOK/OPS`. `PonteNfwebTest.php:99-107,134-141,167-178` fixa o mesmo padrão.

**Impacto SaaS:** proxy, APM, métricas, retry e alertas não distinguem sucesso de recusa; incidentes e tentativas indevidas ficam invisíveis no status de transporte.

**Direção:** manter adaptador de compatibilidade isolado e instrumentado, com métricas/auditoria pelo status interno; aposentar gradualmente o contrato e usar HTTP semântico nos clientes novos.

**Vínculo:** confirma A-12.8.

### T-03.9 — MÉDIA — C4 — regressões geográficas continuam calibradas na praça Dubena

**Evidência:** `tests/Feature/ViagensRotaTest.php:344-386,393-433` usa exclusivamente coordenadas próximas de `-25.39/-51.46`; `:436-462` registra decisão do dono e medidas urbanas locais como regra. `NormalizacaoCidadesTest.php:14-20,98-105` fixa casos e volume da base real de Guarapuava.

**Impacto SaaS:** algoritmos podem passar com latitude, densidade viária e convenções locais da Dubena e degradar em outra praça sem qualquer sinal da suíte.

**Direção:** parametrizar latitude/hemisfério/densidade/perfil urbano e adicionar datasets sintéticos de regiões distintas; separar fixtures Dubena de contratos universais.

**Vínculo:** reforça A-8.7.

### T-03.10 — BAIXA — C4 — “todos os endpoints existem” verifica presença, não contrato completo

**Evidência:** `tests/Feature/PonteLegadoTest.php:220-240` e `PonteNfwebTest.php:181-203` coletam URIs e apenas fazem `assertContains`. Vários endpoints da lista não têm cenário de autenticação, tenant, payload e efeito correspondente no mesmo arquivo.

**Impacto SaaS:** uma rota pode continuar registrada com middleware/handler/shape errado e satisfazer o gate nominal.

**Direção:** data provider por endpoint com método HTTP, guard, tenant adversarial, payload mínimo e schema esperado.

**Vínculo:** amplia a evidência de A-12.8 sem criar um novo defeito de produção.

## Inventário integral

```text
NaturezaItemETaxaEntregaTest.php 308
NfEntradaApiTest.php 102
NfEntradaTest.php 118
NormalizacaoCidadesTest.php 270
NormalizacaoLogradourosTest.php 335
PagamentoTest.php 87
PaginacaoListagensTest.php 130
PapelGlobalTest.php 68
PedidoNfceTest.php 104
PedidoSituacaoTest.php 118
PedidoTest.php 96
PerfilCampoTest.php 147
PixDriverTest.php 159
PixWebhookFailClosedTest.php 169
PonteLegadoTest.php 267
PonteNfwebTest.php 272
ProdutoEstoqueTest.php 123
PushAssincronoTest.php 78
RastreamentoEntregadorTest.php 121
RbacContratoTest.php 174
RedeFiliaisTest.php 183
RegraExtratoTest.php 293
RelatoriosCentralTest.php 119
RelatoriosPreGoLiveTest.php 248
RelatorioTest.php 163
RlsCoberturaTest.php 120
RotaEntregadorConsumidorTest.php 148
RotasSpaProtegidasTest.php 70
SateliteStatusTest.php 57
SateliteTest.php 71
SegurancaAvancadaTest.php 179
SeparacaoClienteColaboradorTest.php 113
SuperAdminTest.php 169
TelefoniaTest.php 268
TempoRealTest.php 164
TipoDocumentoVeiculoTest.php 152
TraccarRastreamentoTest.php 547
TrilhaAuditoriaTest.php 285
TrocaDeEmpresaRegraTest.php 129
ValeGasPdfTest.php 220
VeiculoTest.php 72
ViagensRotaTest.php 785
VisibilidadeRedeTest.php 194
TOTAL 43 arquivos / 7.995 linhas / 336 métodos de teste
```

## Cobertura positiva observada

- Há casos cross-tenant explícitos em clientes, produtos, telefonia, PIX, realtime, veículos, viagens e troca de empresa.
- Idempotência é exercitada em NF de entrada, NFC-e, PIX, sync Traccar e cache de rotas.
- Webhooks PIX/PABX, perfis de campo e canal privado possuem casos adversariais úteis.
- Relatórios financeiros têm assertions numéricas, não apenas status HTTP.

## Limites

A leitura estática está fechada em 100%. A execução terminou com 333 testes aprovados e 3 RLS pulados. Como a suíte roda por padrão em SQLite, SQL, RLS, roles e comportamento concorrente do PostgreSQL real permanecem não verificados neste recorte. Integrações externas são fakes/stubs. Esses limites são declarados separadamente dos achados determinísticos acima.
