# Auditoria SaaS — Testes 02 — Feature A–M

> **Status:** FECHADO — 100% do recorte lido (101/101 arquivos; 15.874/15.874 linhas).  
> **Recorte:** `erp-novo/tests/Feature`, arquivos PHP cujo nome começa de A até M, inclusive.  
> **Fonte:** somente código e execução segura da suíte; documentação não foi usada como evidência.

## Resultado executivo

Foram registrados **11 achados: 6 altos e 5 médios**. A suíte contém bons testes de isolamento pontual, mas também cristaliza três contratos perigosos para SaaS: licenciamento fail-open, tributação default e cobertura de entrega condicionada ao marketplace. Quatro testes falham porque contratos antigos de comodato contradizem o modelo atual de `sentido`. Controles que dependem de PostgreSQL/RLS, fonte legada ou opção `--strict` não são efetivamente demonstrados por este recorte.

## Método e fechamento da leitura

- Cada um dos 101 arquivos foi lido integralmente, do início ao fim; não houve amostragem.
- O inventário foi obtido do sistema de arquivos e a contagem de linhas foi recalculada por arquivo.
- A execução foi particionada somente para isolar falhas, sem retirar arquivos do recorte: **692 testes; 688 passaram; 4 falharam; 2.051 assertions**.
- Critérios mestres: C1 conceito ausente; C2 classificação por texto; C3 flag como proxy; C4 convenção não declarada; C5 conceitos misturados; C6 escopo de tenant errado. Achados que não se enquadram honestamente são marcados como transversais fora de C1–C6.

## Achados

### T-02.01 — ALTA — C4 — o teste torna o licenciamento fail-open parte do contrato

**Evidência:** `tests/Feature/LicenciamentoSaasTest.php:17-20,55-61` exige que empresa sem assinatura receba todas as chaves de `RecursoCatalogo` e marketplace ativo, embora `assinaturaAtiva` seja falsa.

**Impacto na segunda revenda:** um tenant ainda não provisionado ou cuja assinatura foi removida continua com todos os módulos, eliminando a barreira comercial e de autorização esperada no SaaS.

**Direção:** substituir a exceção implícita por estado explícito de trial/grandfathering, com prazo e auditoria; fora dele, fail-closed. Reescrever o teste para esse contrato.

**Vínculo:** confirma A-10.3 e A-1.9; não é nova hipótese.

### T-02.02 — ALTA — C4 — ausência de matriz tributária gera tributação brasileira fixa

**Evidência:** `tests/Feature/MatrizTributariaTest.php:268-290` exige fallback CST `00` e ICMS de 18% quando nenhuma regra existe. No mesmo arquivo, `:177-185` exige falha para regra interestadual ausente, deixando contratos de ausência inconsistentes.

**Impacto na segunda revenda:** empresa em outra UF ou regime pode emitir documento com enquadramento e alíquota inadequados sem perceber que sua matriz não foi configurada.

**Direção:** ausência de regra aplicável deve bloquear emissão com erro acionável; defaults só podem existir em política fiscal versionada e atribuída ao tenant.

**Vínculo:** reforça A-6.4 e a dívida de matriz tributária registrada nos Volumes 2 e 6.

### T-02.03 — ALTA — C3 — desligar marketplace também desliga a cobertura geográfica

**Evidência:** `tests/Feature/AppPedidoCoberturaTest.php:15-21,68-76` denomina `app_marketplace_ativo=false` como white-label sem marketplace e exige aceitar pedido cerca de 250 km fora do raio.

**Impacto na segunda revenda:** uma escolha de canal/publicação altera silenciosamente a política logística e permite pedidos inviáveis fora da área atendida.

**Direção:** separar `participa_marketplace` de `validar_area_entrega`; toda dispensa de cobertura deve ser política explícita por operação/tenant.

**Vínculo:** relaciona-se a A-1.12 (configuração somente por grupo) e ao D-1 sobre o significado comercial do grupo.

### T-02.04 — ALTA — C3/C5 — quatro falhas revelam dois contratos de comodato obsoletos

**Evidência:** `ComodatoAcrescimoProdutoTest.php:62-73,115-128,131-155,187-197` cria o comodato legado sem `sentido`; três casos falham com `NOT NULL constraint failed: comodatos.sentido` quando o serviço replica/consulta a relação. Além disso, `ComodatoVigilanciaTest.php:204-216` exige que qualquer cliente marcado `fornecedor` nunca seja vigiado, enquanto `ComodatoSentidoTest.php:99-116` exige expressamente o oposto para comodato `CONCEDIDO`; `:166-192` determina que é o `sentido RECEBIDO`, não a flag cadastral, que exclui cobrança.

**Impacto na segunda revenda:** fixtures antigas escondem a migração incompleta de direção e uma regra baseada em papel comercial pode suprimir vigilância patrimonial de clientes legítimos.

**Direção:** tornar `sentido` obrigatório nas factories/helpers e migrar todos os fixtures; remover o contrato por flag `fornecedor`, conservando a regra por direção do comodato.

**Vínculo:** corrige a ambiguidade já descrita em A-1.10 e a dívida de comodato dos Volumes 1 e 5.

### T-02.05 — ALTA — transversal fora de C1–C6 — gates de produção não são provados no banco que precisam proteger

**Evidência:** `BancoProducaoCheckTest.php:16-20,24-35` fixa SQLite e declara que PostgreSQL foi verificado manualmente; o teste comprova apenas existência/flag e inspeção estática. `FaseF02CrossTenantTest.php:25-34` também declara que RLS é testada em PostgreSQL, mas neste recorte executa SQLite e escopo da aplicação.

**Impacto na segunda revenda:** regressão em policy, role ou catálogo PostgreSQL pode chegar ao deploy com a suíte verde e expor dados entre tenants.

**Direção:** adicionar job obrigatório com PostgreSQL real, role de runtime `NOBYPASSRLS`, duas empresas e negativas de SELECT/INSERT/UPDATE; o resultado inconclusivo deve bloquear o gate.

**Vínculo:** confirma A-14.15 e os riscos de RLS de A-3.3/A-3.9 e A-10.1.

### T-02.06 — ALTA — C4 — o go-live é permissivo por padrão e depende de opção humana

**Evidência:** `GoliveCheckTest.php:9-13,27-50` exige que integrações fake sejam apenas `WARN` e que o comando padrão retorne sucesso; somente a execução com `--strict` bloqueia.

**Impacto na segunda revenda:** um operador pode seguir o caminho padrão e liberar tenant com fiscal/cobrança simulados, produzindo operação sem validade externa.

**Direção:** modo estrito automático em produção e manifesto mínimo por plano/tenant; bypass deve ser excepcional, temporário e auditado.

**Vínculo:** mesma família de fail-open de A-14.9 e A-14.14.

### T-02.07 — MÉDIA — transversal fora de C1–C6 — o “contrato SPA” aceita stub 501 e não valida respostas

**Evidência:** `ContratoSpaTest.php:9-18` define sucesso como rota não retornar 404 e aceita explicitamente stub 501; `:105-137` extrai chamadas por regex apenas em `frontend/src/features` e verbos literais. `ApiContratoDriftTest.php:8-18,22-35` verifica método/caminho, não schema, autenticação, tenant nem payload.

**Impacto na segunda revenda:** uma tela pode parecer coberta apesar de chamar endpoint deliberadamente não implementado, ou divergir no formato de resposta sem falhar no gate.

**Direção:** contrato gerado de especificação executável com status funcional, schemas e requisitos de auth/tenant; inventariar todo `frontend/src`, inclusive wrappers dinâmicos.

**Vínculo:** complementa os achados A-13.04/A-13.06; não invalida a auditoria integral da SPA.

### T-02.08 — MÉDIA — C6 — testes cristalizam credencial compartilhada por grupo/plataforma

**Evidência:** `ContratoIntegracoesMigradasTest.php:86-99` exige chave Google Maps no grupo, não na empresa; `IntegracaoForaDeRequestTest.php:43-75` exige fallback grupo→variável de plataforma; `ConfigGlobalTest.php:30-38,73-82` fixa configuração por grupo.

**Impacto na segunda revenda:** sem a decisão D-1, tenants podem compartilhar cota, faturamento e poder de revogação de uma credencial; o fallback de instalação amplia o raio da falha.

**Direção:** decidir o dono comercial de cada integração; testar resolução empresa→grupo→plataforma somente onde cada nível for explicitamente permitido, com isolamento de cota e auditoria.

**Vínculo:** confirma A-1.4/A-1.12, A-13.02 e a análise de credenciais dos Volumes 8 e 11.

### T-02.09 — MÉDIA — C4 — onboarding de teste está acoplado à operação Dubena

**Evidência:** `AcessoRedeDubenaSeederTest.php:9,14-22,34-57,60-75,92-116,136-151` importa um seeder nominal, cria “Rede Dubena”, “Dubena Matriz/Filial” e contas `@dubena.com.br`, além de inferir matriz por volume de clientes.

**Impacto na segunda revenda:** a suíte protege uma receita de implantação específica, não um onboarding genérico; novos tenants podem herdar nomenclatura, heurística e papéis indevidos.

**Direção:** mover fixture Dubena para teste/migração operacional isolada e criar contrato genérico de provisionamento com parâmetros explícitos e idempotência.

**Vínculo:** reforça A-14.23 e o risco operacional dos seeders específicos.

### T-02.10 — MÉDIA — transversal fora de C1–C6 — grande parte dos cenários usa bypass de suporte

**Evidência:** 61/101 arquivos contêm `support => true`; 36 deles não contêm cenário literal `support => false`. Exemplos: `ComodatoAcrescimoProdutoTest.php:47-52`, `FaseF02CrossTenantTest.php:39-47` e `ContratoTelaApiTest.php:36-46` autenticam com bypass. A contagem é derivada do próprio recorte inventariado.

**Impacto na segunda revenda:** autorização, visibilidade por empresa e limites de papéis comuns podem regredir enquanto fluxos funcionais permanecem verdes via superusuário.

**Direção:** matriz mínima de personas sem suporte por módulo, com negativos cross-tenant e autorização por mutação; reservar `support=true` a casos cujo objeto seja o próprio suporte.

**Vínculo:** dá cobertura de teste insuficiente ao achado A-13.06 e às barreiras avaliadas no Volume 10.

### T-02.11 — MÉDIA — transversal fora de C1–C6 — ETL testa ausência de legado, não transformação real

**Evidência:** `EtlTravaPosCutoverTest.php:22-26,32-64` cobre caminho sem fonte e opção de escape, não detecção numa origem pós-cutover. `MigradoresModulosTest.php:18-26,48-79` cobre ausência/dry-run; os metadados em doc-comment dos métodos nas linhas `48-50` e `68-70` já geram aviso de depreciação e falharão no PHPUnit 12.

**Impacto na segunda revenda:** mudança de driver, schema ou dados reais pode romper migração sem sinal prévio; uma atualização do runner também transforma avisos em erro.

**Direção:** fixtures representativas por origem, testes de transformação/idempotência e pós-cutover real; migrar metadata para atributos PHPUnit.

**Vínculo:** confirma as lacunas explicitadas no Volume 14, sobretudo A-14.5/A-14.9/A-14.14.

## Execução reproduzida

| Segmento | Resultado |
|---|---:|
| A* e B* | 86 passaram; 220 assertions |
| C* exceto Comodato* | 146 passaram; 498 assertions |
| Comodato* | 59 passaram, 4 falharam; 134 assertions |
| D* a M* | 397 passaram; 1.199 assertions |
| **Total único** | **688 passaram, 4 falharam; 692 testes; 2.051 assertions** |

As quatro falhas estão integralmente explicadas em T-02.04. Houve ainda aviso de metadata em doc-comments de `MigradoresModulosTest`, registrado em T-02.11.

## Inventário e prova de cobertura

| Inicial | Arquivos | Linhas |
|---:|---:|---:|
| A | 14 | 1.792 |
| B | 2 | 200 |
| C | 29 | 5.133 |
| D | 2 | 235 |
| E | 12 | 1.792 |
| F | 13 | 1.698 |
| G | 4 | 458 |
| H | 1 | 74 |
| I | 8 | 1.622 |
| J | 3 | 260 |
| L | 2 | 277 |
| M | 11 | 2.333 |
| **Total** | **101** | **15.874** |

> Não há arquivo iniciado por K no diretório. A ausência resulta do inventário, não de exclusão.

### Inventário exato por arquivo

| Arquivo | Linhas |
|---|---:|
| `erp-novo/tests/Feature/AbacCondicoesApiTest.php` | 111 |
| `erp-novo/tests/Feature/AbacPolicyEvaluatorTest.php` | 164 |
| `erp-novo/tests/Feature/AbacVerbosSensiveisTest.php` | 147 |
| `erp-novo/tests/Feature/AcessoRedeDubenaSeederTest.php` | 153 |
| `erp-novo/tests/Feature/AlcadaCrudTest.php` | 115 |
| `erp-novo/tests/Feature/AlcadaDescontoTest.php` | 216 |
| `erp-novo/tests/Feature/ApiContratoDriftTest.php` | 36 |
| `erp-novo/tests/Feature/AppAuthHardeningTest.php` | 117 |
| `erp-novo/tests/Feature/AppPedidoCoberturaTest.php` | 78 |
| `erp-novo/tests/Feature/AppPedidoEscopoClienteTest.php` | 123 |
| `erp-novo/tests/Feature/AppRoleTest.php` | 126 |
| `erp-novo/tests/Feature/AppVendaCampoTest.php` | 206 |
| `erp-novo/tests/Feature/AuditoriaSegurancaTest.php` | 135 |
| `erp-novo/tests/Feature/AuthTenantTest.php` | 65 |
| `erp-novo/tests/Feature/BancoProducaoCheckTest.php` | 62 |
| `erp-novo/tests/Feature/BoletoPdfTest.php` | 138 |
| `erp-novo/tests/Feature/CadastroApoioRhTest.php` | 75 |
| `erp-novo/tests/Feature/CadastroApoioTest.php` | 145 |
| `erp-novo/tests/Feature/CaixaTest.php` | 76 |
| `erp-novo/tests/Feature/CargaFranqueadoTest.php` | 210 |
| `erp-novo/tests/Feature/CatalogoIbgeTest.php` | 186 |
| `erp-novo/tests/Feature/CentralAcessosTest.php` | 209 |
| `erp-novo/tests/Feature/CentralVendasTest.php` | 221 |
| `erp-novo/tests/Feature/CercaCriacaoTest.php` | 190 |
| `erp-novo/tests/Feature/CercaMunicipioTest.php` | 153 |
| `erp-novo/tests/Feature/CercasInteligentesTest.php` | 213 |
| `erp-novo/tests/Feature/CidadePlataformaTest.php` | 134 |
| `erp-novo/tests/Feature/ClienteRevisaoTest.php` | 181 |
| `erp-novo/tests/Feature/ClienteTest.php` | 277 |
| `erp-novo/tests/Feature/CobrancaTest.php` | 146 |
| `erp-novo/tests/Feature/ColaboradorTest.php` | 178 |
| `erp-novo/tests/Feature/ComodatoAcrescimoProdutoTest.php` | 225 |
| `erp-novo/tests/Feature/ComodatoContratoVersaoTest.php` | 215 |
| `erp-novo/tests/Feature/ComodatoDevolucaoParcialTest.php` | 266 |
| `erp-novo/tests/Feature/ComodatoPdfTest.php` | 155 |
| `erp-novo/tests/Feature/ComodatoSentidoTest.php` | 194 |
| `erp-novo/tests/Feature/ComodatoVigilanciaTest.php` | 411 |
| `erp-novo/tests/Feature/ComodatoVinculoApiTest.php` | 165 |
| `erp-novo/tests/Feature/ConfigGlobalTest.php` | 92 |
| `erp-novo/tests/Feature/ContratoIntegracoesMigradasTest.php` | 101 |
| `erp-novo/tests/Feature/ContratoNotaFiscalApiTest.php` | 108 |
| `erp-novo/tests/Feature/ContratoSpaTest.php` | 208 |
| `erp-novo/tests/Feature/ContratoTelaApiTest.php` | 167 |
| `erp-novo/tests/Feature/CrmTest.php` | 86 |
| `erp-novo/tests/Feature/CupomTextoTest.php` | 146 |
| `erp-novo/tests/Feature/DanfePdfTest.php` | 189 |
| `erp-novo/tests/Feature/DashboardResumoTest.php` | 46 |
| `erp-novo/tests/Feature/EmpresaCertificadoTest.php` | 206 |
| `erp-novo/tests/Feature/EmpresaTest.php` | 135 |
| `erp-novo/tests/Feature/EnderecoEmpresaColaboradorTest.php` | 176 |
| `erp-novo/tests/Feature/EnforcementCentralTest.php` | 143 |
| `erp-novo/tests/Feature/EntregaP7Test.php` | 136 |
| `erp-novo/tests/Feature/EscalaP9Test.php` | 80 |
| `erp-novo/tests/Feature/EscritaRestauradaTest.php` | 277 |
| `erp-novo/tests/Feature/EstoqueOperacoesTest.php` | 95 |
| `erp-novo/tests/Feature/EstruturaOrganizacionalTest.php` | 191 |
| `erp-novo/tests/Feature/EtlTravaPosCutoverTest.php` | 65 |
| `erp-novo/tests/Feature/ExampleTest.php` | 19 |
| `erp-novo/tests/Feature/ExtratoRemuneracaoTest.php` | 269 |
| `erp-novo/tests/Feature/F07FinanceiroCaixaChequeTest.php` | 147 |
| `erp-novo/tests/Feature/F11AuditoriaTest.php` | 103 |
| `erp-novo/tests/Feature/F12FrotaCrmGatesTest.php` | 111 |
| `erp-novo/tests/Feature/F13PerformanceTest.php` | 98 |
| `erp-novo/tests/Feature/F14RastreabilidadeTest.php` | 93 |
| `erp-novo/tests/Feature/FaseF00Test.php` | 189 |
| `erp-novo/tests/Feature/FaseF02CrossTenantTest.php` | 218 |
| `erp-novo/tests/Feature/FaseF09FiscalTest.php` | 149 |
| `erp-novo/tests/Feature/FaseF1SegurancaTest.php` | 175 |
| `erp-novo/tests/Feature/FieldLevelTest.php` | 137 |
| `erp-novo/tests/Feature/FinanceiroTest.php` | 103 |
| `erp-novo/tests/Feature/FiscalConfigTest.php` | 70 |
| `erp-novo/tests/Feature/FiscalTest.php` | 105 |
| `erp-novo/tests/Feature/GasDoPovoProgramaTest.php` | 214 |
| `erp-novo/tests/Feature/GeoTest.php` | 93 |
| `erp-novo/tests/Feature/GestaoTest.php` | 88 |
| `erp-novo/tests/Feature/GoliveCheckTest.php` | 63 |
| `erp-novo/tests/Feature/HomologSeederTest.php` | 74 |
| `erp-novo/tests/Feature/IdempotenciaTest.php` | 184 |
| `erp-novo/tests/Feature/IdentidadeClienteTest.php` | 417 |
| `erp-novo/tests/Feature/ImportacaoLogradourosTest.php` | 263 |
| `erp-novo/tests/Feature/InconsistenciaGeoTest.php` | 231 |
| `erp-novo/tests/Feature/IniciarRotaTest.php` | 128 |
| `erp-novo/tests/Feature/IntegracaoFailClosedTest.php` | 144 |
| `erp-novo/tests/Feature/IntegracaoForaDeRequestTest.php` | 87 |
| `erp-novo/tests/Feature/IntegracaoTenantTest.php` | 168 |
| `erp-novo/tests/Feature/JobsAgendadosTest.php` | 61 |
| `erp-novo/tests/Feature/JobsTratamentoFalhaTest.php` | 104 |
| `erp-novo/tests/Feature/JornadaEntregadorTest.php` | 95 |
| `erp-novo/tests/Feature/LicenciamentoSaasTest.php` | 179 |
| `erp-novo/tests/Feature/LookupTest.php` | 98 |
| `erp-novo/tests/Feature/MaloteTest.php` | 297 |
| `erp-novo/tests/Feature/MarketplaceTest.php` | 110 |
| `erp-novo/tests/Feature/MatrizTributariaTest.php` | 291 |
| `erp-novo/tests/Feature/MigracaoFerramentaTest.php` | 195 |
| `erp-novo/tests/Feature/MigradorEnumValidoTest.php` | 83 |
| `erp-novo/tests/Feature/MigradoresModulosTest.php` | 99 |
| `erp-novo/tests/Feature/MobileTest.php` | 730 |
| `erp-novo/tests/Feature/MonitoraApiTest.php` | 85 |
| `erp-novo/tests/Feature/MonitoraTest.php` | 113 |
| `erp-novo/tests/Feature/MotivosPedidoTest.php` | 165 |
| `erp-novo/tests/Feature/MultiTenantIsolamentoTest.php` | 165 |

## Itens não verificáveis neste recorte

- Não foi executado PostgreSQL real nem a role de runtime; comportamento de RLS ficou não verificável por este recorte, embora existam testes fora de A–M.
- Não foram usadas bases legadas Oracle/MySQL, serviços fiscais/cobrança reais nem credenciais externas; transformação, cotas e efeitos remotos permanecem não verificáveis.
- Não foi executado cutover destrutivo nem go-live de produção. Esses limites não alteram contradições determinísticas nem as quatro falhas reproduzidas.

## Fechamento

Leitura estática concluída em **101/101 arquivos e 15.874/15.874 linhas (100%)**. A frente fecha com **11 achados (6 ALTA, 5 MÉDIA)**, **4 testes falhos**, nenhum arquivo omitido dentro da regra A–M e os limites de ambiente acima explicitados.
