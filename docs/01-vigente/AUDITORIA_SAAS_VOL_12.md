# Auditoria SaaS — Volume 12: API e infraestrutura de entrada

**Estado:** FECHADO — 156/156 arquivos e 22.675/22.675 linhas lidos integralmente.

**Data:** 2026-08-25.

## Recorte e método

O recorte original (`routes/api.php`, `routes/channels.php`, `bootstrap/app.php`, todo `app/Http/`, `app/Services/`, `app/Jobs/` e `app/Console/`) contém **133 arquivos / 20.340 linhas**. A ampliação (`app/Observers/`, `app/Providers/`, demais `bootstrap/*.php`, `config/*.php`, `routes/web.php`, `routes/console.php`, `public/index.php` e `resources/js/*.js`) contém **23 arquivos / 2.335 linhas**.

Todos os arquivos do inventário foram lidos do início ao fim. Buscas e contagens serviram apenas para conferir medidas depois da leitura, nunca como substituto dela. Os achados foram revalidados no código atual; o rascunho anterior foi corrigido quando divergia do repositório.

| Bloco | Arquivos | Linhas |
|---|---:|---:|
| Controllers Admin | 58 | 9.694 |
| Mobile (inclui concern) | 10 | 2.022 |
| Legado | 2 | 899 |
| SuperAdmin | 5 | 592 |
| Demais `app/Http` | 26 | 1.775 |
| `app/Console` | 27 | 3.731 |
| Services + Jobs | 2 | 426 |
| Rotas API/channels + bootstrap principal | 3 | 1.201 |
| Ampliação | 23 | 2.335 |
| **Total** | **156** | **22.675** |

## Resultado

**19 achados: 10 ALTOS, 6 MÉDIOS e 3 BAIXOS.** As defesas mais consistentes são Mobile, canais privados, guard de SuperAdmin, `ResolveTenant` e gates fail-closed de fiscal, boleto, Firebase e FCM. As lacunas dominantes são autorização de borda inerte, ids validados globalmente, divergência empresa ativa/padrão, pontes legadas e degradação financeira para fake.

## Achados

### A-12.1 (ALTA) — middlewares de permissão e licença registrados, mas não usados

**C1.** `bootstrap/app.php:36-55`; `routes/api.php:91-1037`. Há **594 declarações de rota**, zero aplicação de `permissao:` e zero de `recurso:`. **Impacto SaaS:** controller que omita autorização interna fica aberto; recursos contratados não são barrados na borda.

### A-12.2 (ALTA) — `exists` global completa caminho cross-tenant no caixa

**C6.** Há 152 regras inline `exists:<tabela>,id` e três `Rule::exists` sem tenant. `app/Http/Controllers/Api/Admin/CaixaController.php:208-219` entrega ids globais ao serviço. **Impacto SaaS:** operador pode indicar conta alheia; o serviço remove o scope e pode alterar saldo de outro tenant. Estoque e atribuição logística repetem a classe de risco.

### A-12.3 (ALTA) — empresa padrão ignora empresa ativa

**C5/C6.** `app/Http/Middleware/ResolveTenant.php:36-43` aceita `X-Empresa-Id`, mas há 72 leituras de `$request->user()->empresa_id` em 22/58 controllers Admin; exemplo `RelatorioController.php:23-36`. **Impacto SaaS:** usuário posicionado na filial B pode receber relatório/operação da empresa padrão A.

### A-12.4 (ALTA) — configuração genérica sobrescreve segredos

**C4.** `EmpresaConfigController.php:42-58` usa `$request->all()` e grava todo campo não estrutural em `dados`, sem validação. **Impacto SaaS:** `empresa.edit` pode substituir integrações e parâmetros bancários, contornando cifragem/write-only.

### A-12.5 (MÉDIA) — segredo global do PABX escreve em qualquer empresa

**C6.** `PabxWebhookController.php:34-63` autentica por segredo da instalação, mas recebe `empresa_id` global. **Impacto SaaS:** integrador pode criar chamadas na fila de qualquer tenant.

### A-12.6 (MÉDIA) — dashboard sem permissão funcional

**C4.** `RelatorioController.php:23-29`; `routes/api.php:125-158`. **Impacto SaaS:** token autenticado que alcance Admin obtém indicadores agregados operacionais/financeiros.

### A-12.7 (MÉDIA) — migração cross-tenant sem trilha de plataforma

**C4/C6.** `SuperAdmin/MigracaoController.php:48-234` e `app/Jobs/ExecutarMigracaoJob.php:47-86` criam, mapeiam e executam carga sem `AuditoriaPlataforma`. **Impacto SaaS:** importações que criam/preenchem tenants não registram autoria imutável.

### A-12.8 (BAIXA) — ponte converte falhas em HTTP 200

**C4.** `app/Http/Middleware/DialetoLegado.php:43-74`. **Impacto SaaS:** 4xx/5xx ficam invisíveis a monitoramento por status e prejudicam SLO/suporte.

### A-12.9 (BAIXA) — `herda_filhos` persistido, mas sem enforcement

**C1/C5.** `Admin/UsuarioController.php:149-192` grava o campo; o avaliador ligado por `AuthServiceProvider` não o consome. **Impacto SaaS:** UI promete semântica hierárquica sem efeito real.

### A-12.10 (BAIXA) — comando carrega bases globais em memória

**C2.** `IdentidadeVincularColaboradores.php:84-142` faz `get()` global e só separa depois por empresa. **Impacto SaaS:** memória/tempo crescem com todos os tenants, embora a chave composta evite cruzamento.

### A-12.11 (MÉDIA) — NFWEB insere telefones fora do observer

**C5.** `PonteNfwebController.php:317-393` cria cliente por Eloquent, mas telefones por `DB::table`; `ClienteIdentidadeObserver.php:42-55` e `AppServiceProvider.php:315-326` dependem de eventos de `ClienteTelefone`. **Impacto SaaS:** nome/documento já são sincronizados pelo observer atual (a alegação antiga de cliente totalmente invisível era falsa), mas telefones não entram no motor de identidade.

### A-12.12 (ALTA) — `getCadastros` usa colunas inexistentes

**C4/C6.** `PonteNfwebController.php:290-307` filtra bairros, ruas e segmentos por `empresa_id`; o schema os define por `grupo_id` (`0003_...geografico_tables.php:16-48`, `0002_...cadastros_apoio_tables.php:20-35`). **Impacto SaaS:** endpoint quebra; cidades ainda saem globalmente e podem excluir a praça do tenant pelo limite.

### A-12.13 (ALTA) — pontes usam `veiculos.colaborador_id` inexistente

**C4.** `PonteMovelAppController.php:124-139,224-244`; `PonteNfwebController.php:414-434`; migration de frota `:36-51`. **Impacto SaaS:** leitura/troca de veículo falha e a ponte mascara como HTTP 200.

### A-12.14 (ALTA) — duas frotas para o mesmo conceito

**C5.** `AppEntregadorController.php:60-96` usa `monitora_veiculos`; pontes e Admin/Frota usam `veiculos`; Admin/Monitora volta à primeira. **Impacto SaaS:** veículo, vínculo e hodômetro divergem conforme o app.

### A-12.15 (MÉDIA) — cidades globais sem auditoria

**C4.** `SuperAdmin/PainelController.php:54-71` cria/altera/exclui sem auditoria. **Impacto SaaS:** mudança que afeta descoberta/cobertura de todas as empresas não tem autoria/histórico.

### A-12.16 (ALTA) — lookups expõem cadastros sem permissão

**C4/C6.** `routes/api.php:157-158`; `Admin/LookupController.php:26-109` oferece clientes, usuários, colaboradores, produtos e contas sem Gate. **Impacto SaaS:** usuário sem permissão do módulo enumera dados do tenant.

### A-12.17 (ALTA) — tenant vê tentativas de login de toda a plataforma

**C6.** `Admin/AuditoriaController.php:57-86` inclui todo `empresa_id IS NULL`, sem vínculo por domínio/e-mail. **Impacto SaaS:** revenda recebe e-mails, IPs, motivos e horários pré-login alheios.

### A-12.18 (MÉDIA) — produto expõe custo sem autorização por campo

**C4.** `app/Http/Resources/ProdutoResource.php:43-54` sempre serializa custo médio/frete. **Impacto SaaS:** perfil de catálogo/preço deriva margem comercial.

### A-12.19 (ALTA) — pagamento cai silenciosamente no fake em produção

**C1/C4.** `AppServiceProvider.php:57-93` define fail-close; boleto, fiscal, Firebase e FCM o usam, mas `PagamentoDriver` em `:130-134` retorna `FakePagamentoDriver` sempre que não for `erede`; `config/services.php:99-106` traz `fake` por padrão. **Impacto SaaS:** produção mal configurada pode confirmar pagamento simulado, divergindo pedido, recebível e conciliação.

## Inventário verificável

Formato `linhas arquivo`; marcadores vazios contam como arquivo/zero linhas.

### Recorte original — 133 / 20.340

```text
app/Console/Commands: ApiManifestGerar.php:86; BancoProducaoCheck.php:359; CercaClassificarMunicipio.php:178; CidadesNormalizar.php:180; ClientesSuspeitosExclusao.php:95; CnefeImportar.php:161; ComodatoVigiar.php:118; CutoverCheck.php:58; DedupClientesApp.php:489; EstoqueCorrigirServicos.php:81; EtlRun.php:196; FinanceiroNotificarVencidos.php:31; GoliveCheck.php:250; IbgeSincronizar.php:95; IbptAtualizar.php:115; IdentidadeReindexar.php:141; IdentidadeRepararCadeias.php:186; IdentidadeVarrer.php:240; IdentidadeVincularColaboradores.php:162; LogradourosImportar.php:169; LogradourosNormalizar.php:149; MissoesGerar.php:32; MonitoraSyncPositions.php:31; NotifyAlertas.php:31; NotifyInconsistencias.php:38; PixExpirar.php:25; VendaDiaria.php:35

app/Http/Controllers/Api/Admin: AlcadaDescontoController.php:114; AlertaController.php:84; AssinaturaController.php:65; AuditoriaController.php:228; BoletoController.php:143; CadastroApoioController.php:83; CaixaController.php:239; CargaFranqueadoController.php:98; CentralController.php:196; CentralVendasController.php:102; ChequeController.php:120; CidadeController.php:111; ClienteController.php:235; ClienteRevisaoController.php:190; ClienteSubrecursoController.php:190; ClienteTelefoneController.php:51; ColaboradorController.php:419; ComodatoController.php:457; ConfigFiscalController.php:51; ConfigGlobalController.php:98; ConvenioController.php:67; CrmController.php:240; EmpresaConfigController.php:290; EmpresaController.php:131; EstoqueController.php:280; EstruturaController.php:214; FinanceiroCadastroController.php:93; FinanceiroController.php:333; FiscalConfigController.php:97; GeoController.php:203; GestaoController.php:180; GrupoController.php:66; ImportacaoLogradouroController.php:117; LogradouroOficialController.php:220; LookupController.php:111; MalaDiretaController.php:77; MaloteController.php:71; MissaoController.php:233; MonitoraController.php:477; MunicipioIbgeController.php:147; NfEntradaController.php:102; NotaFiscalController.php:204; PagamentoController.php:172; PapelController.php:309; PedidoController.php:411; PixController.php:48; ProdutoConfigController.php:88; ProdutoController.php:72; ProdutoPrecoController.php:52; RegiaoController.php:62; RelatorioController.php:277; SateliteStatusController.php:74; SetorController.php:61; TaxaEntregaController.php:145; TelefoniaController.php:78; UsuarioController.php:235; ValeGasController.php:110; VeiculoController.php:273

app/Http/Controllers/Api: AuthController.php:117; HealthController.php:40; PabxWebhookController.php:73; PixWebhookController.php:137; SegurancaController.php:165
app/Http/Controllers/Api/Legado: PonteMovelAppController.php:334; PonteNfwebController.php:565
app/Http/Controllers/Api/Mobile: AppAuthController.php:317; AppEntregadorController.php:302; AppFiscalController.php:105; AppLojaController.php:153; AppMissaoController.php:243; AppPedidoController.php:272; AppPerfilController.php:195; AppSolicitacaoController.php:339; MarketplaceController.php:51; Concerns/ResolveClienteDoApp.php:45
app/Http/Controllers/Api/SuperAdmin: AuthController.php:95; EmpresaController.php:98; MigracaoController.php:236; PainelController.php:86; PlanoController.php:77
app/Http/Controllers: Concerns/AutorizaPorPermissao.php:67; Concerns/PaginaListagem.php:84; Controller.php:8
app/Http/Middleware: AppRole.php:35; DialetoLegado.php:95; Idempotente.php:117; Permissao.php:31; Recurso.php:35; ResolveTenant.php:139; ValidaRevendaLegado.php:54
app/Http/Requests: .gitkeep:0; Auth/LoginRequest.php:21; ClienteRequest.php:69; EmpresaRequest.php:51; PedidoRequest.php:43; ProdutoRequest.php:87
app/Http/Resources: .gitkeep:0; ClienteResource.php:97; EmpresaResource.php:54; PedidoResource.php:71; ProdutoResource.php:85
app/Jobs/ExecutarMigracaoJob.php:88
app/Services/Migracao/MigracaoService.php:338
bootstrap/app.php:99
routes/api.php:1037
routes/channels.php:65
```

### Ampliação — 23 / 2.335

```text
app/Observers/ClienteIdentidadeObserver.php:56
app/Providers/AppServiceProvider.php:328
app/Providers/AuthServiceProvider.php:52
bootstrap/providers.php:9
config/app.php:126
config/auth.php:126
config/broadcasting.php:61
config/cache.php:117
config/cors.php:37
config/database.php:267
config/filesystems.php:80
config/logging.php:143
config/mail.php:118
config/queue.php:155
config/reverb.php:102
config/sanctum.php:95
config/services.php:165
config/session.php:217
public/index.php:20
resources/js/app.js:1
resources/js/bootstrap.js:4
routes/console.php:49
routes/web.php:7
```

## Itens não verificáveis

- Não foi executada exploração contra base PostgreSQL multiempresa; os caminhos cross-tenant são conclusão estática da cadeia controller → validação → serviço/schema.
- `.env`, secrets, proxy HTTPS e workers de produção não pertencem ao recorte. A-12.19 prova que o código permite fake; não afirma qual driver está ativo hoje.
- As 594 rotas são declarações HTTP no arquivo, não `route:list` com rotas de framework/pacotes.

**Fechamento:** 156/156 arquivos e 22.675/22.675 linhas; 19 achados (10 ALTOS, 6 MÉDIOS, 3 BAIXOS).
