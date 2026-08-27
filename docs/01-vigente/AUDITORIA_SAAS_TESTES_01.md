# Auditoria SaaS — Testes 01: Domain, Unit e Migration

**Estado:** FECHADO — 44/44 arquivos e 4.746/4.746 linhas lidos integralmente.

**Data:** 2026-08-25.

## Recorte e método

Foram lidos do início ao fim `tests/Domain/`, `tests/Unit/`, `tests/Migration/` e `tests/TestCase.php`, inclusive os dois `.gitkeep`. O conjunto contém 193 métodos de teste. Depois da leitura integral, a suíte executável foi rodada com:

```text
php artisan test tests/Domain tests/Unit tests/Migration
191 passed, 2 skipped, 635 assertions, 31.64s
```

Passar a suíte não foi tratado como prova automática de segurança: foram confrontados os dados montados, os caminhos exercitados e as asserções efetivas. `tests/TestCase.php` é abstrato e, corretamente, não foi passado isoladamente ao runner.

## Resultado executivo

Foram encontrados **10 achados: 3 ALTOS, 5 MÉDIOS e 2 BAIXOS**. O conjunto é forte em invariantes financeiras/estoque, máquinas de estado, idempotência de ETL e casos de regressão do legado. A fragilidade está no que ele não prova: RLS/PostgreSQL, autorização de borda, ids cross-tenant em caixa/logística e validade bancária além do formato superficial.

## Achados

### T-01.1 (ALTA) — a suíte verde não exercita RLS nem isolamento real do PostgreSQL

**Critério:** C6. **Evidência:** `tests/Migration/CercaPoligonoTest.php:32-44`, `ConfigOperacionalEComodatoTest.php:38-49`, `CountInvariantAjustesTest.php:31-42`, `F15MigratorsTest.php:36-48`, `FksNaoMapeadasTest.php:36-47` e `NumeracaoFiscalTest.php:36-47` montam SQLite em memória com `foreign_key_constraints=false`. `tests/Migration/DedupClientesFksTest.php:39-75` pula os dois únicos testes dependentes de `pg_constraint` fora do PostgreSQL; foram exatamente os 2 skips da execução.

**Impacto SaaS:** global scopes podem passar em SQLite enquanto policies RLS, GUCs, schemas, constraints e SQL PostgreSQL falham ou vazam em produção. Vincula-se aos achados de RLS dos Volumes 3/10 e às lacunas PostgreSQL do Volume 14.

### T-01.2 (ALTA) — caixa prova consistência contábil, mas não proíbe transferência cross-tenant

**Critério:** C6. **Evidência:** `tests/Domain/CaixaServiceTest.php:111-121` cria origem e destino pelo mesmo helper/mesma empresa e apenas verifica que o total é preservado. Não existe caso com duas empresas. Doze suítes Domain com banco nem sequer resolvem `TenantContext`, incluindo `CaixaServiceTest.php:26-41`.

**Impacto SaaS:** a cadeia explorável do A-12.2 permanece verde: movimentar duas contas alheias ainda preserva perfeitamente a soma que este teste considera suficiente.

### T-01.3 (ALTA) — Central só testa entregadores do próprio tenant e cristaliza o caminho vulnerável

**Critério:** C6. **Evidência:** `tests/Domain/CentralServiceTest.php:63-73` cria todos os usuários na mesma empresa; `:104-131` valida atribuir/redistribuir sem caso adversarial de entregador alheio. O serviço é chamado diretamente por id.

**Impacto SaaS:** o A-12.2 (atribuição global de entregador) não é detectado. O teste confirma escrita/trilha, mas não a principal pré-condição SaaS: pedido, entregador, veículo e operador pertencem ao mesmo tenant.

### T-01.4 (MÉDIA) — o único teste HTTP do recorte usa `support` e contorna autorização

**Critério:** C4. **Evidência:** `tests/Domain/CnabBoletoTest.php:108-119,123-137` cria usuário com `support=true` e chama endpoint Admin. Não há teste de usuário comum sem permissão, nem de `permissao:`/`recurso:` nas rotas.

**Impacto SaaS:** a suíte pode continuar verde com a autorização de borda inteiramente inerte, exatamente o A-12.1; também não prova isolamento de papéis/planos.

### T-01.5 (MÉDIA) — teste de módulo 10 CNAB é tautológico

**Critério:** transversal fora de C1–C6 (qualidade da verificação). **Evidência:** `tests/Domain/CnabBoletoTest.php:29-37` compara `CnabHelper::modulo10(entrada)` com a mesma chamada, sem valor esperado. Os testes dos drivers em `:52-75` verificam apenas comprimento e prefixo bancário.

**Impacto SaaS:** algoritmo bancário incorreto ou regressão do dígito verificador passa desde que a saída conserve o formato; boletos podem ser rejeitados apesar da suíte verde.

### T-01.6 (MÉDIA) — contrato Domain do comodato ainda é unidirecional

**Critério:** C1/C5. **Evidência:** `tests/Domain/SateliteServiceTest.php:122-155` cobre somente `emprestar`/`devolver`, nunca define nem verifica `sentido`; a criação direta em `:148-152` depende silenciosamente do default `CONCEDIDO`. Não há caso `RECEBIDO` neste recorte.

**Impacto SaaS:** o baseline continua descrevendo “comodato move estoque” como fluxo único e não protege as semânticas opostas de patrimônio, vigilância e contrato introduzidas pelo trabalho de dois sentidos.

### T-01.7 (MÉDIA) — ausência da origem de pagamentos é aceita como resultado final do ETL

**Critério:** C1/C4. **Evidência:** `tests/Migration/F15MigratorsTest.php:172-194` exige que `PagamentoMigrator` declare “sem origem no legado” e considera isso sucesso; não existe teste de conversão/reconciliação de pagamentos reais.

**Impacto SaaS:** a suíte institucionaliza ausência de migração de pagamento em vez de exigir estratégia de conversão, descarte quantificado ou reconciliação. Vincula-se às lacunas de cobertura/cauda longa do Volume 14.

### T-01.8 (MÉDIA) — indisponibilidade do legado é tratada como situação normal e sem aviso

**Critério:** C4. **Evidência:** `tests/Migration/FalhaDeLeituraTest.php:89-109` exige que conexão recusada não gere aviso, justificando ambientes sem bancos legados; `:42-54` também aceita tabela ausente como vazio silencioso.

**Impacto SaaS:** sem separação explícita entre modo CI e execução de migração, credencial/host incorreto pode parecer origem vazia. O `CountInvariant` testa tabela obrigatória ausente, mas não garante que toda etapa crítica esteja coberta por uma invariante.

### T-01.9 (BAIXA) — teste geográfico não verifica a afirmação do próprio nome

**Critério:** transversal fora de C1–C6 (qualidade da verificação). **Evidência:** `tests/Unit/GeoTest.php:30-44` diz verificar que um ponto na borda cai dentro da caixa, mas calcula `Geo::km` do ponto para ele mesmo (zero) e apenas confirma tipos/deltas positivos.

**Impacto SaaS:** regressão no uso real do bounding box pode passar; afeta pré-filtro de busca/roteirização, embora os cálculos básicos tenham cobertura útil.

### T-01.10 (BAIXA) — teste exemplo não testa produto

**Critério:** C1. **Evidência:** `tests/Unit/ExampleTest.php:7-15` contém apenas `assertTrue(true)`.

**Impacto SaaS:** infla contagem de testes e não protege contrato algum; deve ser removido ou substituído por smoke test relevante.

## Pressupostos específicos da Dubena

- `tests/Domain/DistribuidorServiceTest.php:25-28,102-135` fixa Guarapuava (`-25.39, -51.46`) como praça operacional. É útil como regressão da Dubena, mas não testa altas latitudes, antimeridiano ou outras áreas de atendimento SaaS.
- `tests/Migration/NumeracaoFiscalTest.php:90-115` usa os números reais da matriz (81.074 e 335.358). É excelente teste de aceitação do cutover Dubena, mas não substitui casos genéricos para múltiplas empresas, séries e modelos.
- `tests/Migration/CercaPoligonoTest.php:13-24` e `ConfigOperacionalEComodatoTest.php:17-30` registram contagens/dados do dump real. Devem continuar como regressões, identificados como fixtures de cliente, não como contrato universal do produto.
- `tests/Domain/CnabBoletoTest.php:52-75` cobre apenas Caixa e Itaú, coerente com a operação atual, mas o gate SaaS deve rejeitar banco/driver não implementado explicitamente.

## Cobertura positiva relevante

- `EstoqueMultiTenantTest.php:43-101` é o melhor padrão do recorte: cria dois tenants, troca contexto, comprova scope e bloqueia transferência cruzada.
- `JornadaServiceTest.php:80-90` prova rejeição de veículo de outra empresa.
- Migration cobre idempotência, dry-run, preços históricos, FKs mapeadas, numeração fiscal e divergência de saldos com bons casos negativos.
- Os testes usam `Event::fake` seletivo em Central/Distribuidor, preservando eventos de model que preenchem tenant.

## Inventário completo

```text
0 tests/Domain/.gitkeep
39 tests/Domain/BrFormatTest.php
124 tests/Domain/CaixaRegrasC4Test.php
151 tests/Domain/CaixaServiceTest.php
149 tests/Domain/CalculoImpostoTest.php
55 tests/Domain/CalculoParcelasTest.php
175 tests/Domain/CentralServiceTest.php
103 tests/Domain/ChequeServiceTest.php
164 tests/Domain/CnabBoletoTest.php
124 tests/Domain/CobrancaServiceTest.php
112 tests/Domain/ComissaoServiceTest.php
85 tests/Domain/ConciliacaoTest.php
168 tests/Domain/DistribuidorServiceTest.php
102 tests/Domain/EstoqueMultiTenantTest.php
138 tests/Domain/EstoqueServiceTest.php
115 tests/Domain/FinanceiroServiceTest.php
62 tests/Domain/GoogleRoutesDriverTest.php
31 tests/Domain/IbptTest.php
130 tests/Domain/JornadaServiceTest.php
214 tests/Domain/MissaoFluxoTest.php
116 tests/Domain/MonitoraServiceTest.php
41 tests/Domain/NumeroSequencialTest.php
109 tests/Domain/PedidoCondicaoPagamentoTest.php
130 tests/Domain/PedidoServiceTest.php
92 tests/Domain/RelatorioMonitoraTest.php
163 tests/Domain/RoteirizadorServiceTest.php
157 tests/Domain/SateliteServiceTest.php
60 tests/Domain/SpedContribuicoesTest.php
73 tests/Domain/SpedFiscalTest.php
92 tests/Domain/VeiculoServiceTest.php
0 tests/Migration/.gitkeep
57 tests/Migration/BalanceInvariantTest.php
154 tests/Migration/CercaPoligonoTest.php
145 tests/Migration/ConfigOperacionalEComodatoTest.php
130 tests/Migration/CountInvariantAjustesTest.php
76 tests/Migration/DedupClientesFksTest.php
52 tests/Migration/EstadosMigratorTest.php
227 tests/Migration/F15MigratorsTest.php
122 tests/Migration/FalhaDeLeituraTest.php
241 tests/Migration/FksNaoMapeadasTest.php
197 tests/Migration/NumeracaoFiscalTest.php
10 tests/TestCase.php
16 tests/Unit/ExampleTest.php
45 tests/Unit/GeoTest.php
```

## Itens não verificáveis

- A execução foi feita no ambiente de testes configurado localmente; não houve execução equivalente contra PostgreSQL com RLS ativa nem bancos legados reais.
- Os dois testes de catálogo de FKs foram pulados por dependerem de PostgreSQL.
- Este recorte não inclui `tests/Feature`; portanto ausência aqui não significa ausência global quando existe cobertura Feature. Os achados qualificam o contrato das suítes auditadas e seus vínculos com falhas já demonstradas.

**Fechamento:** 44/44 arquivos, 4.746/4.746 linhas, 193 métodos; 191 passaram, 2 foram pulados; 10 achados (3 ALTOS, 5 MÉDIOS, 2 BAIXOS).
