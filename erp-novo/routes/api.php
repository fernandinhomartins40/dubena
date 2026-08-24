<?php

use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Api\Admin\AlcadaDescontoController;
use App\Http\Controllers\Api\Admin\AssinaturaController;
use App\Http\Controllers\Api\Admin\AuditoriaController;
use App\Http\Controllers\Api\Admin\BoletoController;
use App\Http\Controllers\Api\Admin\CadastroApoioController;
use App\Http\Controllers\Api\Admin\CaixaController;
use App\Http\Controllers\Api\Admin\CargaFranqueadoController;
use App\Http\Controllers\Api\Admin\CentralController;
use App\Http\Controllers\Api\Admin\CentralVendasController;
use App\Http\Controllers\Api\Admin\ChequeController;
use App\Http\Controllers\Api\Admin\CidadeController;
use App\Http\Controllers\Api\Admin\ClienteController;
use App\Http\Controllers\Api\Admin\ClienteRevisaoController;
use App\Http\Controllers\Api\Admin\ClienteSubrecursoController;
use App\Http\Controllers\Api\Admin\ClienteTelefoneController;
use App\Http\Controllers\Api\Admin\ColaboradorController;
use App\Http\Controllers\Api\Admin\ComodatoController;
use App\Http\Controllers\Api\Admin\ConfigFiscalController;
use App\Http\Controllers\Api\Admin\ConfigGlobalController;
use App\Http\Controllers\Api\Admin\ConvenioController;
use App\Http\Controllers\Api\Admin\CrmController;
use App\Http\Controllers\Api\Admin\EmpresaConfigController;
use App\Http\Controllers\Api\Admin\EmpresaController;
use App\Http\Controllers\Api\Admin\EstoqueController;
use App\Http\Controllers\Api\Admin\EstruturaController;
use App\Http\Controllers\Api\Admin\FinanceiroCadastroController;
use App\Http\Controllers\Api\Admin\FinanceiroController;
use App\Http\Controllers\Api\Admin\FiscalConfigController;
use App\Http\Controllers\Api\Admin\GeoController;
use App\Http\Controllers\Api\Admin\GestaoController;
use App\Http\Controllers\Api\Admin\GrupoController;
use App\Http\Controllers\Api\Admin\ImportacaoLogradouroController;
use App\Http\Controllers\Api\Admin\LogradouroOficialController;
use App\Http\Controllers\Api\Admin\LookupController;
use App\Http\Controllers\Api\Admin\MalaDiretaController;
use App\Http\Controllers\Api\Admin\MaloteController;
use App\Http\Controllers\Api\Admin\MissaoController;
use App\Http\Controllers\Api\Admin\MonitoraController;
use App\Http\Controllers\Api\Admin\MunicipioIbgeController;
use App\Http\Controllers\Api\Admin\NfEntradaController;
use App\Http\Controllers\Api\Admin\NotaFiscalController;
use App\Http\Controllers\Api\Admin\PagamentoController;
use App\Http\Controllers\Api\Admin\PapelController;
use App\Http\Controllers\Api\Admin\PedidoController;
use App\Http\Controllers\Api\Admin\PixController;
use App\Http\Controllers\Api\Admin\ProdutoConfigController;
use App\Http\Controllers\Api\Admin\ProdutoController;
use App\Http\Controllers\Api\Admin\ProdutoPrecoController;
use App\Http\Controllers\Api\Admin\RegiaoController;
use App\Http\Controllers\Api\Admin\RelatorioController;
use App\Http\Controllers\Api\Admin\SateliteStatusController;
use App\Http\Controllers\Api\Admin\SetorController;
use App\Http\Controllers\Api\Admin\TaxaEntregaController;
use App\Http\Controllers\Api\Admin\TelefoniaController;
use App\Http\Controllers\Api\Admin\UsuarioController;
use App\Http\Controllers\Api\Admin\ValeGasController;
use App\Http\Controllers\Api\Admin\VeiculoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Legado\PonteMovelAppController;
use App\Http\Controllers\Api\Legado\PonteNfwebController;
use App\Http\Controllers\Api\Mobile\AppAuthController;
use App\Http\Controllers\Api\Mobile\AppEntregadorController;
use App\Http\Controllers\Api\Mobile\AppFiscalController;
use App\Http\Controllers\Api\Mobile\AppLojaController;
use App\Http\Controllers\Api\Mobile\AppMissaoController;
use App\Http\Controllers\Api\Mobile\AppPedidoController;
use App\Http\Controllers\Api\Mobile\AppPerfilController;
use App\Http\Controllers\Api\Mobile\AppSolicitacaoController;
use App\Http\Controllers\Api\Mobile\MarketplaceController;
use App\Http\Controllers\Api\PabxWebhookController;
use App\Http\Controllers\Api\PixWebhookController;
use App\Http\Controllers\Api\SegurancaController;
use App\Http\Controllers\Api\SuperAdmin\AuthController as SuperAdminAuthController;
use App\Http\Controllers\Api\SuperAdmin\EmpresaController as SuperAdminEmpresaController;
use App\Http\Controllers\Api\SuperAdmin\MigracaoController as SuperAdminMigracaoController;
use App\Http\Controllers\Api\SuperAdmin\PainelController as SuperAdminPainelController;
use App\Http\Controllers\Api\SuperAdmin\PlanoController as SuperAdminPlanoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Rotas da API (JSON). Toda a SPA e os apps consomem por aqui.
| Contrato: JSON uniforme, número cru, sem View/Redirect.
*/

// Readiness (PÚBLICO) — P9. Checa banco p/ o LB/monitor; throttle anti-abuso.
Route::get('/health', HealthController::class)->middleware('throttle:60,1');

// Autenticação (pública) — rate-limit estreito anti-brute-force (F13).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Webhook PIX (PÚBLICO — o PSP chama de fora; segurança no controller/service) — N7.
Route::post('/pix/webhook', [PixWebhookController::class, 'handle']);
// Webhook do PABX (T4.4) — chamada entrante. Segredo dedicado, fail-closed;
// nao repete o sha1(APP_KEY) do legado (a APP_KEY dele vazou no repo).
Route::post('/pabx/chamada', [PabxWebhookController::class, 'handle']);

// Login do app mobile (PÚBLICO) — N10. Token real por usuário/colaborador (e-mail+senha).
// Mesmo limiter do login web: são o mesmo alvo de brute-force por outra porta.
Route::post('/app/v1/login', [AppAuthController::class, 'login'])
    ->middleware('throttle:login');

// Login do CLIENTE pelo app (PÚBLICO) — F1. Phone-auth do Firebase + empresa_id.
Route::post('/app/v1/cliente/login', [AppAuthController::class, 'loginCliente'])
    ->middleware('throttle:login');

// Cadastro do CLIENTE pelo app (PÚBLICO) — F3b. Firebase + dados → cria cliente + token.
// Limiter próprio, mais estreito que o de login: cadastro é irreversível e cria linhas.
Route::post('/app/v1/cliente/cadastro', [AppAuthController::class, 'cadastrarCliente'])
    ->middleware('throttle:cadastro-cliente');

// Marketplace (PÚBLICO) — MP1. Descoberta de empresas por geolocalização (rate-limited).
Route::post('/app/v1/marketplace/empresas', [MarketplaceController::class, 'empresas'])
    ->middleware('throttle:marketplace');

// Cidades atendidas pela plataforma (PÚBLICO) — P3. Catálogo de descoberta (rate-limited).
Route::get('/app/v1/marketplace/cidades', [MarketplaceController::class, 'cidades'])
    ->middleware('throttle:marketplace');

// Rotas autenticadas (Sanctum) + tenant resolvido + rate-limit por usuário (F13).
Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Usuário autenticado + tenant ativo (substitui o "quem sou / qual empresa" do legado).
    Route::get('/me', function (Request $request, TenantContext $tenant) {
        // payloadAuth inclui roles+permissions efetivas na empresa ATIVA
        // (resolvida pelo middleware tenant) — a SPA depende disso para o RBAC.
        return response()->json([
            'user' => $request->user()->payloadAuth($tenant->empresaId()),
            'tenant' => [
                'empresa_id' => $tenant->empresaId(),
                'grupo_id' => $tenant->grupoId(),
            ],
        ]);
    });

    // ── Admin (consumido pela SPA em /api/admin) — N1 ──
    Route::prefix('admin')->group(function () {
        // ── Segurança da conta (A5) — 2FA e sessões do PRÓPRIO usuário ──
        // (sob /admin pois o cliente da SPA usa baseURL .../api/admin).
        Route::get('seguranca/2fa', [SegurancaController::class, 'twoFactorStatus']);
        Route::post('seguranca/2fa/setup', [SegurancaController::class, 'twoFactorSetup']);
        Route::post('seguranca/2fa/confirmar', [SegurancaController::class, 'twoFactorConfirm']);
        Route::post('seguranca/2fa/desabilitar', [SegurancaController::class, 'twoFactorDisable']);
        Route::get('seguranca/sessoes', [SegurancaController::class, 'sessoes']);
        Route::delete('seguranca/sessoes/{id}', [SegurancaController::class, 'revogarSessao'])->whereNumber('id');
        Route::post('seguranca/sessoes/revogar-outras', [SegurancaController::class, 'revogarOutras']);
        // Política de senha da empresa (admin de acessos).
        Route::get('seguranca/politica-senha', [SegurancaController::class, 'politicaSenhaShow']);
        Route::put('seguranca/politica-senha', [SegurancaController::class, 'politicaSenhaUpdate']);

        // Lookups (AsyncSelect da SPA) — listas {id,label} por tipo. Só auth.
        Route::get('lookups/{tipo}', [LookupController::class, 'index']);

        // Empresas (entidade-tenant) + config + troca de tenant ativo.
        Route::get('empresas', [EmpresaController::class, 'index']);
        Route::post('empresas', [EmpresaController::class, 'store']);
        Route::get('empresas/{id}', [EmpresaController::class, 'show'])->whereNumber('id');
        Route::put('empresas/{id}', [EmpresaController::class, 'update'])->whereNumber('id');
        Route::delete('empresas/{id}', [EmpresaController::class, 'destroy'])->whereNumber('id');
        Route::post('empresas/{id}/ativar', [EmpresaController::class, 'ativar'])->whereNumber('id');

        Route::get('empresas/{id}/config', [EmpresaConfigController::class, 'show'])->whereNumber('id');
        Route::put('empresas/{id}/config', [EmpresaConfigController::class, 'update'])->whereNumber('id');
        Route::put('empresas/{id}/config/senha-mestra', [EmpresaConfigController::class, 'senhaMestra'])->whereNumber('id');
        // Uploads/config de empresa — C2 (certificado A1, token NFC-e, teste SMTP).
        Route::post('empresas/{id}/config/testar-email', [EmpresaConfigController::class, 'testarEmail'])->whereNumber('id');
        Route::get('empresas/{id}/certificado', [EmpresaConfigController::class, 'certificadoStatus'])->whereNumber('id');
        Route::post('empresas/{id}/certificado', [EmpresaConfigController::class, 'uploadCertificado'])->whereNumber('id');
        Route::put('empresas/{id}/nfce-token', [EmpresaConfigController::class, 'nfceToken'])->whereNumber('id');
        // Integrações por empresa (multi-tenant) — PIX/cartão. Segredos write-only.
        Route::get('empresas/{id}/integracoes', [EmpresaConfigController::class, 'integracoes'])->whereNumber('id');
        Route::put('empresas/{id}/integracoes', [EmpresaConfigController::class, 'salvarIntegracoes'])->whereNumber('id');

        // Config global do grupo (F01): RT/CSRT, SMTP, SAT, Google Maps.
        Route::get('config-global', [ConfigGlobalController::class, 'show']);
        Route::put('config-global', [ConfigGlobalController::class, 'update']);

        // Assinatura/plano da empresa ativa (P2) — leitura. Gestão é do SuperAdmin (P4).
        Route::get('assinatura', [AssinaturaController::class, 'show']);

        // Cidades da plataforma (P3) — catálogo + vínculo empresa↔cidade.
        Route::get('cidades', [CidadeController::class, 'index']);
        Route::post('cidades', [CidadeController::class, 'store']);
        Route::put('cidades/{id}', [CidadeController::class, 'update'])->whereNumber('id');
        Route::delete('cidades/{id}', [CidadeController::class, 'destroy'])->whereNumber('id');
        Route::get('empresas/{id}/cidades', [CidadeController::class, 'cidadesDaEmpresa'])->whereNumber('id');
        Route::put('empresas/{id}/cidades', [CidadeController::class, 'definirCidadesDaEmpresa'])->whereNumber('id');

        // Grupos (redes) — C1.
        Route::get('grupos', [GrupoController::class, 'index']);
        Route::post('grupos', [GrupoController::class, 'store']);
        Route::put('grupos/{id}', [GrupoController::class, 'update'])->whereNumber('id');
        Route::delete('grupos/{id}', [GrupoController::class, 'destroy'])->whereNumber('id');

        // ── Central de Acessos (A2) — papéis e usuários ──
        // Perfis/papéis do grupo + catálogo de permissões para a UI de marcação.
        Route::get('papeis/catalogo', [PapelController::class, 'catalogo']);
        Route::get('papeis', [PapelController::class, 'index']);
        Route::post('papeis', [PapelController::class, 'store']);
        Route::put('papeis/{id}', [PapelController::class, 'update'])->whereNumber('id');
        Route::delete('papeis/{id}', [PapelController::class, 'destroy'])->whereNumber('id');
        // Condições ABAC (A4) por papel.
        Route::get('papeis/{id}/condicoes', [PapelController::class, 'condicoesIndex'])->whereNumber('id');
        Route::post('papeis/{id}/condicoes', [PapelController::class, 'condicaoStore'])->whereNumber('id');
        Route::delete('papeis/{id}/condicoes/{condId}', [PapelController::class, 'condicaoDestroy'])->whereNumber(['id', 'condId']);
        // Histórico de versões do papel (A6).
        Route::get('papeis/{id}/historico', [PapelController::class, 'historico'])->whereNumber('id');

        // ── Auditoria de segurança (A6) ──
        Route::get('auditoria/eventos', [AuditoriaController::class, 'eventos']);
        Route::get('auditoria/logins', [AuditoriaController::class, 'logins']);
        // Trilha de ações (quem fez o quê). `opcoes` e `clientes` vêm ANTES de
        // `registro/{entidade}` para não serem capturados como entidade.
        Route::get('auditoria/trilha', [AuditoriaController::class, 'trilha']);
        Route::get('auditoria/opcoes', [AuditoriaController::class, 'opcoes']);
        Route::get('auditoria/clientes', [AuditoriaController::class, 'buscarClientes']);
        // A entidade aceita underscore (`notas_fiscais`); o controller ainda
        // valida contra o catálogo, então isto é só a primeira barreira.
        Route::get('auditoria/registro/{entidade}/{id}', [AuditoriaController::class, 'registro'])
            ->whereNumber('id')->where('entidade', '[a-z_]+');

        // Usuários da empresa ativa + atribuição de papéis + reset de senha.
        Route::get('usuarios', [UsuarioController::class, 'index']);
        Route::post('usuarios', [UsuarioController::class, 'store']);
        Route::put('usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id');
        Route::post('usuarios/{id}/resetar-senha', [UsuarioController::class, 'resetarSenha'])->whereNumber('id');
        Route::delete('usuarios/{id}', [UsuarioController::class, 'destroy'])->whereNumber('id');

        // ── Estrutura organizacional (A3) — unidades/departamentos/setores/cargos ──
        Route::get('unidades', [EstruturaController::class, 'unidadesIndex']);
        Route::post('unidades', [EstruturaController::class, 'unidadeStore']);
        Route::put('unidades/{id}', [EstruturaController::class, 'unidadeUpdate'])->whereNumber('id');
        Route::delete('unidades/{id}', [EstruturaController::class, 'unidadeDestroy'])->whereNumber('id');

        Route::get('departamentos', [EstruturaController::class, 'departamentosIndex']);
        Route::post('departamentos', [EstruturaController::class, 'departamentoStore']);
        Route::put('departamentos/{id}', [EstruturaController::class, 'departamentoUpdate'])->whereNumber('id');
        Route::delete('departamentos/{id}', [EstruturaController::class, 'departamentoDestroy'])->whereNumber('id');

        Route::get('setores-org', [EstruturaController::class, 'setoresIndex']);
        Route::post('setores-org', [EstruturaController::class, 'setorStore']);
        Route::put('setores-org/{id}', [EstruturaController::class, 'setorUpdate'])->whereNumber('id');
        Route::delete('setores-org/{id}', [EstruturaController::class, 'setorDestroy'])->whereNumber('id');

        // Regiões de atendimento.
        Route::get('regioes', [RegiaoController::class, 'index']);
        Route::post('regioes', [RegiaoController::class, 'store']);
        Route::put('regioes/{id}', [RegiaoController::class, 'update'])->whereNumber('id');
        Route::delete('regioes/{id}', [RegiaoController::class, 'destroy'])->whereNumber('id');

        // Inconsistências de cadastro (rua/bairro duplicados por similaridade) — F11.
        // ANTES de cadastros/{tipo} p/ não ser capturado como tipo.
        Route::get('cadastros/inconsistencias', [GeoController::class, 'inconsistencias']);
        // A AÇÃO que fecha o ciclo (T4.1): sem ela a tela acima é um relatório
        // que repete os mesmos falsos positivos para sempre.
        Route::post('cadastros/inconsistencias/ignorar', [GeoController::class, 'ignorarInconsistencia']);
        Route::delete('cadastros/inconsistencias/ignorar', [GeoController::class, 'reconsiderarInconsistencia']);

        // Cadastros de apoio genéricos (parametrizados por tipo).
        Route::get('cadastros/{tipo}', [CadastroApoioController::class, 'index']);
        Route::post('cadastros/{tipo}', [CadastroApoioController::class, 'store']);
        Route::put('cadastros/{tipo}/{id}', [CadastroApoioController::class, 'update'])->whereNumber('id');
        Route::delete('cadastros/{tipo}/{id}', [CadastroApoioController::class, 'destroy'])->whereNumber('id');

        // ── Catálogo oficial do IBGE ──
        // Cidade nova entra por AQUI: nome e código vêm do catálogo, não da
        // digitação. O cod_ibge vira cMun na NF-e — errado, é rejeição da SEFAZ.
        Route::get('municipios-ibge', [MunicipioIbgeController::class, 'index']);
        Route::post('municipios-ibge/adotar', [MunicipioIbgeController::class, 'adotar']);
        Route::get('municipios-ibge/conciliacao', [MunicipioIbgeController::class, 'conciliacao']);
        Route::post('municipios-ibge/conciliacao/aplicar', [MunicipioIbgeController::class, 'aplicarConciliacao']);

        // ── Logradouros oficiais (CNEFE/IBGE) ──
        // Autocompletar do endereço e normalização do que foi digitado à mão.
        Route::get('logradouros-oficiais/sugerir', [LogradouroOficialController::class, 'sugerir']);
        Route::get('logradouros-oficiais/divergencias', [LogradouroOficialController::class, 'divergencias']);
        Route::get('logradouros-oficiais/municipios', [LogradouroOficialController::class, 'municipios']);
        Route::post('logradouros-oficiais/normalizar', [LogradouroOficialController::class, 'normalizar']);
        Route::get('logradouros-oficiais', [LogradouroOficialController::class, 'index']);

        // ── Importação de logradouros (base de CEP) ──
        Route::get('logradouros/importacoes', [ImportacaoLogradouroController::class, 'index']);
        Route::post('logradouros/importacoes', [ImportacaoLogradouroController::class, 'store']);
        Route::get('logradouros/importacoes/{id}', [ImportacaoLogradouroController::class, 'show'])->whereNumber('id');

        // ── Geográfico (cidades/bairros/ruas) — N2 ──
        Route::get('geo/{entidade}', [GeoController::class, 'index']);
        Route::post('geo/{entidade}', [GeoController::class, 'store']);
        Route::put('geo/{entidade}/{id}', [GeoController::class, 'update'])->whereNumber('id');
        Route::delete('geo/{entidade}/{id}', [GeoController::class, 'destroy'])->whereNumber('id');

        // ── Clientes — N2 ──
        Route::get('clientes/exportar', [ClienteController::class, 'exportar']); // antes de /{id}
        // Identidade: sugestões de "quem pode ser esta pessoa" e a fila de
        // revisão dos pares suspeitos. ANTES de /{id} para não virarem id.
        Route::get('clientes/sugestoes', [ClienteController::class, 'sugestoes']);
        Route::get('clientes/revisoes', [ClienteRevisaoController::class, 'index']);
        Route::post('clientes/revisoes/{id}/consolidar', [ClienteRevisaoController::class, 'consolidar'])->whereNumber('id');
        Route::post('clientes/revisoes/{id}/descartar', [ClienteRevisaoController::class, 'descartar'])->whereNumber('id');
        Route::get('clientes', [ClienteController::class, 'index']);
        Route::post('clientes', [ClienteController::class, 'store']);
        Route::get('clientes/{id}', [ClienteController::class, 'show'])->whereNumber('id');
        Route::put('clientes/{id}', [ClienteController::class, 'update'])->whereNumber('id');
        Route::delete('clientes/{id}', [ClienteController::class, 'destroy'])->whereNumber('id');
        // Reativacao: o DELETE acima desativa; este devolve a lista de ativos.
        Route::post('clientes/{id}/reativar', [ClienteController::class, 'reativar'])->whereNumber('id');

        // Sub-recursos do cliente.
        Route::get('clientes/{id}/telefones', [ClienteTelefoneController::class, 'index'])->whereNumber('id');
        Route::post('clientes/{id}/telefones', [ClienteTelefoneController::class, 'store'])->whereNumber('id');
        Route::delete('clientes/{id}/telefones/{telId}', [ClienteTelefoneController::class, 'destroy'])->whereNumber(['id', 'telId']);

        Route::get('clientes/{id}/interacoes', [ClienteSubrecursoController::class, 'interacoes'])->whereNumber('id');
        Route::post('clientes/{id}/interacoes', [ClienteSubrecursoController::class, 'addInteracao'])->whereNumber('id');
        Route::delete('clientes/{id}/interacoes/{subId}', [ClienteSubrecursoController::class, 'delInteracao'])->whereNumber(['id', 'subId']);

        Route::get('clientes/{id}/convenio', [ClienteSubrecursoController::class, 'convenio'])->whereNumber('id');
        Route::put('clientes/{id}/convenio', [ClienteSubrecursoController::class, 'salvarConvenio'])->whereNumber('id');
        Route::post('clientes/{id}/convenio/dependentes', [ClienteSubrecursoController::class, 'addDependente'])->whereNumber('id');
        Route::delete('clientes/{id}/convenio/dependentes/{depId}', [ClienteSubrecursoController::class, 'delDependente'])->whereNumber(['id', 'depId']);

        Route::get('clientes/{id}/precos', [ClienteSubrecursoController::class, 'precos'])->whereNumber('id');
        Route::get('clientes/{id}/historico', [ClienteSubrecursoController::class, 'historico'])->whereNumber('id');

        // ── Produtos — N3 ──
        Route::get('produtos', [ProdutoController::class, 'index']);
        Route::post('produtos', [ProdutoController::class, 'store']);
        Route::get('produtos/{id}', [ProdutoController::class, 'show'])->whereNumber('id');
        Route::put('produtos/{id}', [ProdutoController::class, 'update'])->whereNumber('id');
        Route::delete('produtos/{id}', [ProdutoController::class, 'destroy'])->whereNumber('id');
        Route::get('produtos/{id}/estoque', [EstoqueController::class, 'porProduto'])->whereNumber('id');

        // Config de produto (classes/unidades) — C1.
        Route::get('produto-config/classes', [ProdutoConfigController::class, 'classesIndex']);
        Route::post('produto-config/classes', [ProdutoConfigController::class, 'classeSalvar']);
        Route::put('produto-config/classes/{id}', [ProdutoConfigController::class, 'classeSalvar'])->whereNumber('id');
        Route::delete('produto-config/classes/{id}', [ProdutoConfigController::class, 'classeExcluir'])->whereNumber('id');
        Route::get('produto-config/unidades', [ProdutoConfigController::class, 'unidadesIndex']);
        Route::post('produto-config/unidades', [ProdutoConfigController::class, 'unidadeSalvar']);
        Route::put('produto-config/unidades/{id}', [ProdutoConfigController::class, 'unidadeSalvar'])->whereNumber('id');
        Route::delete('produto-config/unidades/{id}', [ProdutoConfigController::class, 'unidadeExcluir'])->whereNumber('id');

        // Reajuste de preços em massa — C1.
        Route::get('produtos-precos/preview', [ProdutoPrecoController::class, 'preview']);
        Route::put('produtos-precos/aplicar', [ProdutoPrecoController::class, 'aplicar']);

        // ── Estoque — N3 ──
        Route::get('setores', [SetorController::class, 'index']);
        Route::post('setores', [SetorController::class, 'store']);
        Route::put('setores/{id}', [SetorController::class, 'update'])->whereNumber('id');
        Route::delete('setores/{id}', [SetorController::class, 'destroy'])->whereNumber('id');

        Route::get('estoque/saldos', [EstoqueController::class, 'saldos']);
        Route::get('estoque/historico', [EstoqueController::class, 'historico']);
        Route::post('estoque/entrada', [EstoqueController::class, 'entrada']);
        Route::post('estoque/saida', [EstoqueController::class, 'saida']);
        Route::get('estoque/transferencias', [EstoqueController::class, 'transferencias']);
        Route::post('estoque/transferencias', [EstoqueController::class, 'transferir']);
        Route::post('estoque/acerto', [EstoqueController::class, 'acerto']);
        Route::get('estoque/fechamentos', [EstoqueController::class, 'fechamentos']);
        Route::post('estoque/fechamentos', [EstoqueController::class, 'fechar']);

        // Estoque — requisições / inventário / físico / abertura de fechamento.
        Route::get('estoque/requisicoes', [EstoqueController::class, 'requisicoesIndex']);
        Route::post('estoque/requisicoes', [EstoqueController::class, 'requisicaoCriar']);
        Route::get('estoque/inventarios', [EstoqueController::class, 'inventariosIndex']);
        Route::post('estoque/inventarios', [EstoqueController::class, 'inventarioCriar']);
        // "físico" é a contagem (alias do inventário).
        Route::get('estoque/fisico', [EstoqueController::class, 'inventariosIndex']);
        Route::post('estoque/fisico', [EstoqueController::class, 'inventarioCriar']);
        Route::post('estoque/fisico/{id}/efetivar', [EstoqueController::class, 'inventarioEfetivar'])->whereNumber('id');
        Route::post('estoque/fechamentos/abrir', [EstoqueController::class, 'abrirFechamento']);

        // ── Pedidos / Vendas — N4 ── (rotas estáticas antes de /{id})
        Route::get('pedidos/kanban', [PedidoController::class, 'kanban']);
        Route::get('pedidos/situacoes', [PedidoController::class, 'situacoes']);
        // CRUD das colunas do Kanban (situações) — config por grupo.
        Route::post('pedidos/situacoes', [PedidoController::class, 'criarSituacao']);
        Route::put('pedidos/situacoes/reordenar', [PedidoController::class, 'reordenarSituacoes']);
        Route::put('pedidos/situacoes/{id}', [PedidoController::class, 'atualizarSituacao'])->whereNumber('id');
        Route::delete('pedidos/situacoes/{id}', [PedidoController::class, 'excluirSituacao'])->whereNumber('id');
        Route::get('pedidos', [PedidoController::class, 'index']);
        Route::post('pedidos', [PedidoController::class, 'store']);
        Route::get('pedidos/{id}', [PedidoController::class, 'show'])->whereNumber('id');
        Route::put('pedidos/{id}', [PedidoController::class, 'update'])->whereNumber('id');
        Route::delete('pedidos/{id}', [PedidoController::class, 'destroy'])->whereNumber('id');
        Route::put('pedidos/{id}/situacao', [PedidoController::class, 'mudarSituacao'])->whereNumber('id');
        // Comanda impressa (T4.6): o papel que vai com o entregador.
        Route::get('pedidos/{id}/comanda', [PedidoController::class, 'comanda'])->whereNumber('id');
        // Apoios operacionais do disk-gás (T4.8): as colunas existiam em
        // `pedidos` apontando para tabelas que nunca foram criadas.
        Route::post('pedidos/{id}/justificar-atraso', [PedidoController::class, 'justificarAtraso'])->whereNumber('id');
        Route::post('pedidos/{id}/nao-venda', [PedidoController::class, 'registrarNaoVenda'])->whereNumber('id');
        // Emissão fiscal a partir do pedido concluído (NFC-e/NF-e) — F03.
        Route::post('pedidos/{id}/emitir-nfce', [PedidoController::class, 'emitirNfce'])->whereNumber('id');

        // ── Financeiro (a pagar/receber) — N5 ──
        Route::get('financeiro/lancamentos/resumo', [FinanceiroController::class, 'resumo']);
        Route::get('financeiro/lancamentos', [FinanceiroController::class, 'lancamentos']);
        Route::post('financeiro/lancamentos', [FinanceiroController::class, 'criar']);
        Route::delete('financeiro/lancamentos/{id}', [FinanceiroController::class, 'cancelar'])->whereNumber('id');
        // Agrupamento / reparcelamento de títulos (expõe FinanceiroService) — F00.6.
        Route::post('financeiro/lancamentos/agrupar', [FinanceiroController::class, 'agrupar']);
        Route::post('financeiro/lancamentos/{id}/desagrupar', [FinanceiroController::class, 'desagrupar'])->whereNumber('id');
        Route::post('financeiro/lancamentos/{id}/reparcelar', [FinanceiroController::class, 'reparcelar'])->whereNumber('id');

        Route::get('financeiro/planos-conta', [FinanceiroCadastroController::class, 'planosIndex']);
        Route::post('financeiro/planos-conta', [FinanceiroCadastroController::class, 'planoSalvar']);
        Route::put('financeiro/planos-conta/{id}', [FinanceiroCadastroController::class, 'planoSalvar'])->whereNumber('id');
        Route::delete('financeiro/planos-conta/{id}', [FinanceiroCadastroController::class, 'planoExcluir'])->whereNumber('id');

        Route::get('financeiro/centros-custo', [FinanceiroCadastroController::class, 'centrosIndex']);
        Route::post('financeiro/centros-custo', [FinanceiroCadastroController::class, 'centroSalvar']);
        Route::put('financeiro/centros-custo/{id}', [FinanceiroCadastroController::class, 'centroSalvar'])->whereNumber('id');
        Route::delete('financeiro/centros-custo/{id}', [FinanceiroCadastroController::class, 'centroExcluir'])->whereNumber('id');

        // ── Caixa / Conta — N6 ──
        // Fechamento de malote (T4.3) — acerto de valores do entregador.
        // Condicionado a decisao do dono; ver App\Domain\Caixa\MaloteService.
        Route::get('malotes/conferencia', [MaloteController::class, 'conferencia']);
        Route::post('malotes/fechar', [MaloteController::class, 'fechar']);

        // Bina no atendimento (T4.4) — condicionado a decisao do dono.
        Route::get('telefonia/fila', [TelefoniaController::class, 'fila']);
        Route::get('telefonia/buscar', [TelefoniaController::class, 'buscar']);
        Route::post('telefonia/chamadas/{id}/atender', [TelefoniaController::class, 'atender'])->whereNumber('id');
        Route::post('telefonia/chamadas/{id}/rejeitar', [TelefoniaController::class, 'rejeitar'])->whereNumber('id');
        Route::get('caixa/contas', [CaixaController::class, 'contas']);
        Route::post('caixa/contas', [CaixaController::class, 'criarConta']);
        Route::post('caixa/transferencias', [CaixaController::class, 'transferir']);
        Route::post('caixa/movimentos/{movimentoId}/estornar', [CaixaController::class, 'estornar'])->whereNumber('movimentoId');
        // Recibo impresso (T4.6): sem ele o cliente que paga no balcão sai sem
        // comprovante. `grep recibo` no novo retornava zero.
        Route::get('caixa/movimentos/{movimentoId}/recibo', [CaixaController::class, 'recibo'])->whereNumber('movimentoId');
        Route::get('caixa/{contaId}/movimentos', [CaixaController::class, 'movimentos'])->whereNumber('contaId');
        Route::post('caixa/{contaId}/abrir', [CaixaController::class, 'abrir'])->whereNumber('contaId');
        Route::post('caixa/{contaId}/fechar', [CaixaController::class, 'fechar'])->whereNumber('contaId');
        Route::post('caixa/{contaId}/baixar', [CaixaController::class, 'baixar'])->whereNumber('contaId');
        // Baixa em lote e lançamento em caixa fechado (expõe CaixaService) — F00.6.
        Route::post('caixa/{contaId}/baixar-titulos', [CaixaController::class, 'baixarTitulos'])->whereNumber('contaId');
        Route::post('caixa/{contaId}/lancar-fechado', [CaixaController::class, 'lancarFechado'])->whereNumber('contaId');

        // ── Cheques — N6 ──
        Route::get('cheques/recebidos', [ChequeController::class, 'recebidos']);
        Route::get('cheques/emitidos', [ChequeController::class, 'emitidos']);
        Route::post('cheques', [ChequeController::class, 'store']);
        Route::put('cheques/{id}', [ChequeController::class, 'update'])->whereNumber('id');
        Route::delete('cheques/{id}', [ChequeController::class, 'destroy'])->whereNumber('id');
        Route::put('cheques/{id}/situacao', [ChequeController::class, 'mudarSituacao'])->whereNumber('id');
        Route::post('cheques/{id}/encontro-de-contas', [ChequeController::class, 'encontroDeContas'])->whereNumber('id');

        // ── Pagamentos (C4): cartão (NSU) e Gás do Povo ──
        Route::get('cartoes', [PagamentoController::class, 'cartaoIndex']);
        Route::post('cartoes', [PagamentoController::class, 'cartaoRegistrar']);
        Route::get('gasdopovo', [PagamentoController::class, 'gasIndex']);
        Route::post('gasdopovo', [PagamentoController::class, 'gasRegistrar']);
        Route::post('gasdopovo/{id}/sacar', [PagamentoController::class, 'gasSacar'])->whereNumber('id');
        // O PROGRAMA (parametros da empresa, beneficiarios e vendas subsidiadas),
        // distinto dos beneficios acima. Rotas literais antes de `{id}`.
        Route::get('gasdopovo/programa', [PagamentoController::class, 'gasPrograma']);
        Route::get('gasdopovo/beneficiarios', [PagamentoController::class, 'gasBeneficiarios']);
        Route::get('gasdopovo/vendas', [PagamentoController::class, 'gasVendas']);

        // (API-2) Os aliases de ESCRITA cheques/recebidos foram removidos — a SPA
        // usa a rota canônica /cheques (a especie 'R' é enviada no corpo).
        // GET cheques/recebidos permanece: é a LISTAGEM dos recebidos, não um dup.

        // ── Boletos (CNAB) — N7 (gate) ──
        Route::get('boletos/resumo', [BoletoController::class, 'resumo']);
        Route::get('boletos/remessas', [BoletoController::class, 'remessas']);
        Route::get('boletos/remessas/{id}/arquivo', [BoletoController::class, 'baixarRemessa'])->whereNumber('id');
        Route::get('boletos', [BoletoController::class, 'index']);
        // Impressão do boleto (T4.6): sem ela o título não chega ao cliente.
        Route::get('boletos/{id}/pdf', [BoletoController::class, 'pdf'])->whereNumber('id');
        Route::post('boletos', [BoletoController::class, 'gerar']);
        Route::post('boletos/remessa', [BoletoController::class, 'remessa']);
        Route::post('boletos/retorno', [BoletoController::class, 'retorno']);
        // Aliases /cobranca/* (F08) — mesma funcionalidade, nomenclatura do módulo.
        Route::get('cobranca/boletos', [BoletoController::class, 'index']);
        Route::post('cobranca/boletos', [BoletoController::class, 'gerar']);
        Route::get('cobranca/remessas', [BoletoController::class, 'remessas']);
        Route::post('cobranca/remessas', [BoletoController::class, 'remessa']);
        Route::get('cobranca/remessas/{id}/arquivo', [BoletoController::class, 'baixarRemessa'])->whereNumber('id');
        Route::post('cobranca/retorno', [BoletoController::class, 'retorno']);

        // ── PIX — N7 (gate) ──
        Route::get('pix/config', [PixController::class, 'config']);
        Route::post('pix/cobrancas', [PixController::class, 'criar']);

        // ── Convênio — N8 ──
        Route::get('convenios', [ConvenioController::class, 'index']);
        Route::post('convenios', [ConvenioController::class, 'store']);
        Route::get('convenios/{id}/fechamentos', [ConvenioController::class, 'fechamentos'])->whereNumber('id');
        Route::post('convenios/{id}/fechar', [ConvenioController::class, 'fechar'])->whereNumber('id');

        // ── Vale-gás — N8 ──
        Route::get('vale-gas/situacoes', [ValeGasController::class, 'situacoes']);
        Route::get('vale-gas', [ValeGasController::class, 'index']);
        Route::post('vale-gas', [ValeGasController::class, 'store']);
        Route::post('vale-gas/baixar', [ValeGasController::class, 'baixar']);
        // Impressao (item 19): o vale E um documento fisico — sem papel o
        // produto nao existe. Duplicata cobre a venda a prazo.
        Route::get('vale-gas/{id}/pdf', [ValeGasController::class, 'pdf'])->whereNumber('id');
        Route::get('vale-gas/{id}/duplicata', [ValeGasController::class, 'duplicata'])->whereNumber('id');

        // ── Comodato — N8 ──
        Route::get('comodatos', [ComodatoController::class, 'index']);
        Route::post('comodatos', [ComodatoController::class, 'store']);
        // Extrato + versoes do contrato: e o que revela a devolucao parcial.
        Route::get('comodatos/{id}', [ComodatoController::class, 'show'])->whereNumber('id');
        // Contrato (item 20): o documento que protege o patrimonio da revenda.
        // `?versao=N` tira segunda via da versao assinada antes da devolucao.
        Route::get('comodatos/{id}/contrato', [ComodatoController::class, 'contrato'])->whereNumber('id');
        Route::post('comodatos/{id}/devolver', [ComodatoController::class, 'devolver'])->whereNumber('id');
        Route::post('comodatos/{id}/reemitir', [ComodatoController::class, 'reemitir'])->whereNumber('id');
        Route::post('comodatos/{id}/contratos/{contrato}/assinado', [ComodatoController::class, 'marcarAssinado'])
            ->whereNumber('id')->whereNumber('contrato');
        // Recibo da devolucao: a prova, para o CLIENTE, de que ele entregou.
        Route::get('comodatos/{id}/movimentos/{movimento}/recibo', [ComodatoController::class, 'recibo'])
            ->whereNumber('id')->whereNumber('movimento');
        Route::post('comodatos/{id}/movimentos/{movimento}/estornar', [ComodatoController::class, 'estornar'])
            ->whereNumber('id')->whereNumber('movimento');

        // ── Fiscal (NF-e/NFC-e/CF-e) — N9 ──
        Route::get('fiscal/config', [ConfigFiscalController::class, 'show']);
        Route::put('fiscal/config', [ConfigFiscalController::class, 'update']);
        Route::get('notas', [NotaFiscalController::class, 'index']);
        Route::post('notas/emitir', [NotaFiscalController::class, 'emitir']);
        Route::get('notas/{id}', [NotaFiscalController::class, 'show'])->whereNumber('id');
        Route::post('notas/{id}/cancelar', [NotaFiscalController::class, 'cancelar'])->whereNumber('id');
        // DANFE (T4.2/item 8): sem o impresso a mercadoria nao circula legalmente.
        Route::get('notas/{id}/danfe', [NotaFiscalController::class, 'danfe'])->whereNumber('id');
        Route::get('fiscal/nfe/{id}/danfe', [NotaFiscalController::class, 'danfe'])->whereNumber('id');

        // Aliases consumidos pela SPA (fiscal/nfe*) → NotaFiscalController.
        Route::get('fiscal/nfe', [NotaFiscalController::class, 'index']);
        Route::post('fiscal/nfe/{id}/transmitir', [NotaFiscalController::class, 'transmitir'])->whereNumber('id');
        Route::post('fiscal/nfe/{id}/cancelar', [NotaFiscalController::class, 'cancelar'])->whereNumber('id');

        // Eventos fiscais (F09): inutilização de faixa + carta de correção (CCE).
        Route::post('fiscal/inutilizacoes', [NotaFiscalController::class, 'inutilizar']);
        Route::post('notas/{id}/carta-correcao', [NotaFiscalController::class, 'cartaCorrecao'])->whereNumber('id');
        Route::post('fiscal/nfe/{id}/carta-correcao', [NotaFiscalController::class, 'cartaCorrecao'])->whereNumber('id');

        // Fiscal — operações fiscais e malha (config por tipo) — C12.
        Route::get('fiscal/operacoes', [FiscalConfigController::class, 'operacoesIndex']);
        Route::post('fiscal/operacoes', [FiscalConfigController::class, 'operacaoSalvar']);
        Route::put('fiscal/operacoes/{id}', [FiscalConfigController::class, 'operacaoSalvar'])->whereNumber('id');
        Route::delete('fiscal/operacoes/{id}', [FiscalConfigController::class, 'operacaoExcluir'])->whereNumber('id');
        Route::get('fiscal/malha/{tipo}', [FiscalConfigController::class, 'malhaIndex']);
        Route::post('fiscal/malha/{tipo}', [FiscalConfigController::class, 'malhaSalvar']);
        Route::put('fiscal/malha/{tipo}/{id}', [FiscalConfigController::class, 'malhaSalvar'])->whereNumber('id');
        Route::delete('fiscal/malha/{tipo}/{id}', [FiscalConfigController::class, 'malhaExcluir'])->whereNumber('id');
        // NF de entrada (recebida): importar XML → estoque + financeiro a pagar — F00.6.
        Route::get('fiscal/nf-entrada', [NfEntradaController::class, 'index']);
        Route::get('fiscal/nf-entrada/{id}', [NfEntradaController::class, 'show'])->whereNumber('id');
        Route::post('fiscal/nf-entrada/importar', [NfEntradaController::class, 'importar']);
        Route::post('fiscal/nf-entrada/{id}/processar', [NfEntradaController::class, 'processar'])->whereNumber('id');

        Route::get('fiscal/sped', [NotaFiscalController::class, 'sped']);
        Route::get('fiscal/sped-contribuicoes', [NotaFiscalController::class, 'spedContribuicoes']);
        Route::get('fiscal/ibpt', [NotaFiscalController::class, 'ibpt']);

        // ── Monitora (GPS) — N11 (módulo isolado) ──
        Route::get('monitora/veiculos', [MonitoraController::class, 'veiculos']);
        Route::post('monitora/veiculos', [MonitoraController::class, 'criarVeiculo']);
        Route::put('monitora/veiculos/{id}', [MonitoraController::class, 'atualizarVeiculo'])->whereNumber('id');
        Route::post('monitora/veiculos/{id}/posicoes', [MonitoraController::class, 'ingerirPosicao'])->whereNumber('id');
        Route::get('monitora/veiculos/{id}/historico', [MonitoraController::class, 'historico'])->whereNumber('id');
        Route::get('monitora/veiculos/{id}/periodo', [MonitoraController::class, 'periodoDisponivel'])->whereNumber('id');
        Route::get('monitora/veiculos/{id}/viagens', [MonitoraController::class, 'viagens'])->whereNumber('id');
        Route::get('monitora/veiculos/{id}/eventos', [MonitoraController::class, 'relatorioEventos'])->whereNumber('id');
        Route::get('monitora/tipos', [MonitoraController::class, 'tipos']);
        Route::post('monitora/tipos', [MonitoraController::class, 'criarTipo']);
        Route::get('monitora/ultimas-posicoes', [MonitoraController::class, 'ultimasPosicoes']);
        Route::get('monitora/cercas', [MonitoraController::class, 'cercas']);
        Route::post('monitora/cercas', [MonitoraController::class, 'criarCerca']);
        Route::put('monitora/cercas/{id}', [MonitoraController::class, 'atualizarCerca'])->whereNumber('id');
        Route::delete('monitora/cercas/{id}', [MonitoraController::class, 'excluirCerca'])->whereNumber('id');
        // Ferramentas assistidas da aba Cercas. Todas SUGEREM — nenhuma grava:
        // o operador confere no mapa e salva pelo fluxo normal de edicao.
        Route::get('monitora/cercas/conflitos', [MonitoraController::class, 'conflitosDeCerca']);
        Route::post('monitora/cercas/quadra', [MonitoraController::class, 'quadraDaCerca']);
        Route::post('monitora/cercas/{id}/ajustar', [MonitoraController::class, 'ajustarCerca'])->whereNumber('id');
        Route::post('monitora/sync', [MonitoraController::class, 'sincronizar']);

        // ── Central de Logística (L1/L3) — fila, distribuição, bloqueio ──
        Route::get('central/fila', [CentralController::class, 'fila']);
        Route::get('central/entregadores', [CentralController::class, 'entregadores']);
        Route::get('central/pedidos/{id}/sugestoes', [CentralController::class, 'sugestoes'])->whereNumber('id');
        Route::post('central/pedidos/{id}/atribuir', [CentralController::class, 'atribuir'])->whereNumber('id');
        Route::post('central/pedidos/{id}/redistribuir', [CentralController::class, 'redistribuir'])->whereNumber('id');
        Route::post('central/pedidos/{id}/priorizar', [CentralController::class, 'priorizar'])->whereNumber('id');
        Route::post('central/pedidos/{id}/reagendar', [CentralController::class, 'reagendar'])->whereNumber('id');
        Route::post('central/entregadores/{id}/bloquear', [CentralController::class, 'bloquear'])->whereNumber('id');
        Route::delete('central/entregadores/{id}/bloquear', [CentralController::class, 'desbloquear'])->whereNumber('id');
        Route::get('central/config', [CentralController::class, 'config']);
        Route::put('central/config', [CentralController::class, 'salvarConfig']);

        // Taxas de entrega: quanto cobrar e quanto custa. `simular` vem antes
        // de /{id} para não ser capturado como id.
        Route::get('taxas-entrega', [TaxaEntregaController::class, 'index']);
        Route::get('taxas-entrega/simular', [TaxaEntregaController::class, 'simular']);
        Route::post('taxas-entrega', [TaxaEntregaController::class, 'store']);
        Route::put('taxas-entrega/{id}', [TaxaEntregaController::class, 'update'])->whereNumber('id');
        Route::delete('taxas-entrega/{id}', [TaxaEntregaController::class, 'destroy'])->whereNumber('id');

        // F3 — Central de VENDAS. Irmã da central de logística acima (que
        // distribui entrega): esta decide se vende e por quanto.
        Route::get('central-vendas/solicitacoes', [CentralVendasController::class, 'index']);
        Route::get('central-vendas/solicitacoes/{id}', [CentralVendasController::class, 'show'])->whereNumber('id');
        Route::post('central-vendas/solicitacoes/{id}/aprovar', [CentralVendasController::class, 'aprovar'])->whereNumber('id');
        Route::post('central-vendas/solicitacoes/{id}/recusar', [CentralVendasController::class, 'recusar'])->whereNumber('id');
        Route::post('central-vendas/solicitacoes/{id}/faturar', [CentralVendasController::class, 'faturar'])->whereNumber('id');

        // F5 — mercadoria em poder do franqueado (consignacao ou compra). Opera
        // o deposito/central, nao o proprio franqueado: entregar mercadoria a si
        // mesmo derrubaria a conferencia.
        // F2 — cadastro das alcadas. Sem isto a verificacao fail-closed deixa
        // TODO MUNDO com teto zero: a tabela sem CRUD trava o negocio.
        Route::get('alcadas', [AlcadaDescontoController::class, 'index']);
        Route::post('alcadas', [AlcadaDescontoController::class, 'salvar']);
        Route::put('alcadas/{id}', [AlcadaDescontoController::class, 'salvar'])->whereNumber('id');
        Route::delete('alcadas/{id}', [AlcadaDescontoController::class, 'destroy'])->whereNumber('id');

        Route::get('franqueados/{id}/estoque', [CargaFranqueadoController::class, 'emPoder'])->whereNumber('id');
        Route::post('franqueados/{id}/carga', [CargaFranqueadoController::class, 'carregar'])->whereNumber('id');
        Route::post('franqueados/{id}/devolucao', [CargaFranqueadoController::class, 'devolver'])->whereNumber('id');

        // ── Missões de campo (L7/L9) — moldes + auditoria ──
        Route::get('missoes', [MissaoController::class, 'index']);
        Route::post('missoes', [MissaoController::class, 'store']);
        Route::put('missoes/{id}', [MissaoController::class, 'update'])->whereNumber('id');
        Route::post('missoes/{id}/atribuir', [MissaoController::class, 'atribuir'])->whereNumber('id');
        Route::get('missoes/atribuicoes', [MissaoController::class, 'atribuicoes']);
        Route::get('missoes/atribuicoes/{id}', [MissaoController::class, 'detalhe'])->whereNumber('id');
        Route::post('missoes/atribuicoes/{id}/auditar', [MissaoController::class, 'auditar'])->whereNumber('id');
        Route::post('missoes/atribuicoes/{id}/adiamento', [MissaoController::class, 'decidirAdiamento'])->whereNumber('id');
        Route::get('missoes/evidencias/{id}', [MissaoController::class, 'evidencia'])->whereNumber('id');

        // ── Relatórios (Query Services) — N12 ──
        // ── CRM / satélites (C10) ──
        Route::get('pos-vendas', [CrmController::class, 'posVendaIndex']);
        Route::post('pos-vendas', [CrmController::class, 'posVendaSalvar']);
        Route::put('pos-vendas/{id}', [CrmController::class, 'posVendaSalvar'])->whereNumber('id');
        Route::delete('pos-vendas/{id}', [CrmController::class, 'posVendaExcluir'])->whereNumber('id');

        Route::get('promocoes', [CrmController::class, 'promocaoIndex']);
        Route::post('promocoes', [CrmController::class, 'promocaoSalvar']);
        Route::put('promocoes/{id}', [CrmController::class, 'promocaoSalvar'])->whereNumber('id');
        Route::delete('promocoes/{id}', [CrmController::class, 'promocaoExcluir'])->whereNumber('id');

        Route::get('sorteios', [CrmController::class, 'sorteioIndex']);
        Route::post('sorteios', [CrmController::class, 'sorteioSalvar']);
        Route::put('sorteios/{id}', [CrmController::class, 'sorteioSalvar'])->whereNumber('id');
        Route::post('sorteios/{id}/numeros', [CrmController::class, 'sorteioNumero'])->whereNumber('id');
        Route::post('sorteios/{id}/sortear', [CrmController::class, 'sortear'])->whereNumber('id');

        Route::get('metas', [CrmController::class, 'metaIndex']);
        Route::post('metas', [CrmController::class, 'metaSalvar']);
        Route::put('metas/{id}', [CrmController::class, 'metaSalvar'])->whereNumber('id');
        Route::delete('metas/{id}', [CrmController::class, 'metaExcluir'])->whereNumber('id');

        Route::get('checklists', [CrmController::class, 'checklistIndex']);
        Route::post('checklists', [CrmController::class, 'checklistSalvar']);
        Route::put('checklists/{id}', [CrmController::class, 'checklistSalvar'])->whereNumber('id');
        Route::post('checklists/{id}/executar', [CrmController::class, 'checklistExecutar'])->whereNumber('id');
        Route::delete('checklists/{id}', [CrmController::class, 'checklistExcluir'])->whereNumber('id');

        // Mala direta (F12): segmentação de clientes p/ campanha + export CSV.
        Route::get('crm/mala-direta', [MalaDiretaController::class, 'index']);

        // ── Gestão (C11): cupom fiscal SAT/CFe, MCMM, documentos, bens ──
        Route::get('cupons-fiscais', [GestaoController::class, 'cupomIndex']);
        Route::post('cupons-fiscais', [GestaoController::class, 'cupomCriar']);
        Route::post('cupons-fiscais/{id}/emitir', [GestaoController::class, 'cupomEmitir'])->whereNumber('id');

        Route::get('mcmm', [GestaoController::class, 'mcmmIndex']);
        Route::post('mcmm', [GestaoController::class, 'mcmmSalvar']);
        Route::put('mcmm/{id}', [GestaoController::class, 'mcmmSalvar'])->whereNumber('id');
        Route::delete('mcmm/{id}', [GestaoController::class, 'mcmmExcluir'])->whereNumber('id');

        Route::get('documentos', [GestaoController::class, 'documentoIndex']);
        Route::post('documentos', [GestaoController::class, 'documentoSalvar']);
        Route::put('documentos/{id}', [GestaoController::class, 'documentoSalvar'])->whereNumber('id');
        Route::delete('documentos/{id}', [GestaoController::class, 'documentoExcluir'])->whereNumber('id');

        Route::get('bens', [GestaoController::class, 'bemIndex']);
        Route::post('bens', [GestaoController::class, 'bemSalvar']);
        Route::put('bens/{id}', [GestaoController::class, 'bemSalvar'])->whereNumber('id');
        Route::delete('bens/{id}', [GestaoController::class, 'bemExcluir'])->whereNumber('id');

        // Dashboard (home da SPA) — contadores rápidos.
        Route::get('dashboard/resumo', [RelatorioController::class, 'dashboardResumo']);

        Route::get('relatorios/vendas', [RelatorioController::class, 'vendas']);
        Route::get('relatorios/financeiro', [RelatorioController::class, 'financeiro']);
        Route::get('relatorios/dre', [RelatorioController::class, 'dre']);
        Route::get('relatorios/estoque-baixo', [RelatorioController::class, 'estoqueBaixo']);
        Route::get('relatorios/fechamentos-caixa', [RelatorioController::class, 'fechamentosCaixa']);
        Route::get('relatorios/aniversariantes', [RelatorioController::class, 'clientesAniversariantes']);
        Route::get('relatorios/vale-gas', [RelatorioController::class, 'valeGas']);
        Route::get('relatorios/comodatos', [RelatorioController::class, 'comodatos']);
        Route::get('relatorios/comissoes', [RelatorioController::class, 'comissoes']);
        Route::get('relatorios/movimentacao-caixa', [RelatorioController::class, 'movimentacaoCaixa']);
        // Central de relatórios (F10): catálogo + dispatcher genérico por slug.
        // O catálogo/auditoria vêm ANTES do {slug} p/ não serem capturados como slug.
        Route::get('relatorios/catalogo', [RelatorioController::class, 'catalogo']);
        Route::get('relatorios/auditoria', [RelatorioController::class, 'auditoria']); // F11
        Route::get('relatorios/{slug}', [RelatorioController::class, 'mostrar'])->where('slug', '[a-z0-9-]+');
        // Alias da SPA: financeiro/dre → relatórios/dre (mesma função).
        Route::get('financeiro/dre', [RelatorioController::class, 'dre']);
        // Conciliação bancária (OFX) — implementada via ConciliacaoService.
        Route::get('financeiro/conciliacao', [FinanceiroController::class, 'conciliacao']);
        Route::post('financeiro/conciliacao', [FinanceiroController::class, 'conciliacao']);
        // Conciliação contábil (CONSISA) — F08.
        // Regras de classificação automática do extrato (T4.2): é o que torna a
        // importação OFX produtiva em vez de uma lista para classificar à mão.
        Route::get('financeiro/contas/{contaId}/extrato-regras', [FinanceiroController::class, 'extratoRegras'])->whereNumber('contaId');
        Route::post('financeiro/contas/{contaId}/extrato-regras', [FinanceiroController::class, 'criarExtratoRegra'])->whereNumber('contaId');
        Route::put('financeiro/contas/{contaId}/extrato-regras/{id}', [FinanceiroController::class, 'atualizarExtratoRegra'])->whereNumber('contaId')->whereNumber('id');
        Route::delete('financeiro/contas/{contaId}/extrato-regras/{id}', [FinanceiroController::class, 'excluirExtratoRegra'])->whereNumber('contaId')->whereNumber('id');

        Route::get('financeiro/conciliacao-contabil', [FinanceiroController::class, 'conciliacaoContabil']);
        Route::get('conciliacao-contabil', [FinanceiroController::class, 'conciliacaoContabil']);

        // ── RH / Colaboradores — C5 ──
        Route::get('colaboradores', [ColaboradorController::class, 'index']);
        Route::post('colaboradores', [ColaboradorController::class, 'store']);
        Route::get('colaboradores/{id}', [ColaboradorController::class, 'show'])->whereNumber('id');
        Route::put('colaboradores/{id}', [ColaboradorController::class, 'update'])->whereNumber('id');
        Route::delete('colaboradores/{id}', [ColaboradorController::class, 'destroy'])->whereNumber('id');
        Route::post('colaboradores/{id}/reativar', [ColaboradorController::class, 'reativar'])->whereNumber('id');
        Route::get('colaboradores/{id}/familia', [ColaboradorController::class, 'familia'])->whereNumber('id');
        Route::post('colaboradores/{id}/familia', [ColaboradorController::class, 'addFamilia'])->whereNumber('id');
        Route::delete('colaboradores/{id}/familia/{famId}', [ColaboradorController::class, 'delFamilia'])->whereNumber(['id', 'famId']);
        // Recessos e comissões: CRUD completo (T4.7). Eram só GET, e sem POST o
        // RH não lança férias nem altera comissão sem voltar ao legado.
        Route::get('colaboradores/{id}/recessos', [ColaboradorController::class, 'recessos'])->whereNumber('id');
        Route::post('colaboradores/{id}/recessos', [ColaboradorController::class, 'addRecesso'])->whereNumber('id');
        Route::put('colaboradores/{id}/recessos/{recessoId}', [ColaboradorController::class, 'updateRecesso'])->whereNumber('id')->whereNumber('recessoId');
        Route::delete('colaboradores/{id}/recessos/{recessoId}', [ColaboradorController::class, 'deleteRecesso'])->whereNumber('id')->whereNumber('recessoId');

        Route::get('colaboradores/{id}/comissoes', [ColaboradorController::class, 'comissoes'])->whereNumber('id');
        Route::post('colaboradores/{id}/comissoes', [ColaboradorController::class, 'addComissao'])->whereNumber('id');
        Route::put('colaboradores/{id}/comissoes/{comissaoId}', [ColaboradorController::class, 'updateComissao'])->whereNumber('id')->whereNumber('comissaoId');
        Route::delete('colaboradores/{id}/comissoes/{comissaoId}', [ColaboradorController::class, 'deleteComissao'])->whereNumber('id')->whereNumber('comissaoId');
        // RH complementar (C5): exames (ASO), turnos/escala, ponto.
        Route::get('colaboradores/{id}/exames', [ColaboradorController::class, 'exames'])->whereNumber('id');
        Route::post('colaboradores/{id}/exames', [ColaboradorController::class, 'addExame'])->whereNumber('id');
        Route::get('colaboradores/{id}/turnos', [ColaboradorController::class, 'turnos'])->whereNumber('id');
        Route::post('colaboradores/{id}/turnos', [ColaboradorController::class, 'addTurno'])->whereNumber('id');
        Route::get('colaboradores/{id}/pontos', [ColaboradorController::class, 'pontos'])->whereNumber('id');
        Route::post('colaboradores/{id}/pontos', [ColaboradorController::class, 'registrarPonto'])->whereNumber('id');

        // ── Frota / Veículos — C6 ──
        Route::get('veiculos', [VeiculoController::class, 'index']);
        Route::post('veiculos', [VeiculoController::class, 'store']);
        Route::get('veiculos/{id}', [VeiculoController::class, 'show'])->whereNumber('id');
        Route::put('veiculos/{id}', [VeiculoController::class, 'update'])->whereNumber('id');
        Route::delete('veiculos/{id}', [VeiculoController::class, 'destroy'])->whereNumber('id');
        Route::get('veiculos/{id}/abastecimentos', [VeiculoController::class, 'abastecimentos'])->whereNumber('id');
        Route::post('veiculos/{id}/abastecimentos', [VeiculoController::class, 'registrarAbastecimento'])->whereNumber('id');
        // Trocas de óleo e pneus: escrita restaurada (T4.7). Registrar a troca é
        // o que ZERA o alerta de troca vencida — sem POST ele fica aceso para
        // sempre e o operador aprende a ignorá-lo.
        Route::get('veiculos/{id}/trocas-oleo', [VeiculoController::class, 'trocasOleo'])->whereNumber('id');
        Route::post('veiculos/{id}/trocas-oleo', [VeiculoController::class, 'registrarTrocaOleo'])->whereNumber('id');
        Route::delete('veiculos/{id}/trocas-oleo/{trocaId}', [VeiculoController::class, 'excluirTrocaOleo'])->whereNumber('id')->whereNumber('trocaId');

        Route::get('veiculos/{id}/pneus', [VeiculoController::class, 'pneus'])->whereNumber('id');
        Route::post('veiculos/{id}/pneus', [VeiculoController::class, 'registrarPneu'])->whereNumber('id');
        Route::put('veiculos/{id}/pneus/{pneuId}', [VeiculoController::class, 'atualizarPneu'])->whereNumber('id')->whereNumber('pneuId');
        Route::delete('veiculos/{id}/pneus/{pneuId}', [VeiculoController::class, 'excluirPneu'])->whereNumber('id')->whereNumber('pneuId');
        // Entrada/saída de pátio + documentos do veículo (F12).
        Route::get('veiculos/{id}/entradas-saidas', [VeiculoController::class, 'entradasSaidas'])->whereNumber('id');
        Route::post('veiculos/{id}/entradas-saidas', [VeiculoController::class, 'registrarEntradaSaida'])->whereNumber('id');
        Route::get('veiculos/{id}/documentos', [VeiculoController::class, 'documentos'])->whereNumber('id');
        Route::post('veiculos/{id}/documentos', [VeiculoController::class, 'registrarDocumento'])->whereNumber('id');

        // Satélites (relatórios/monitoramento/integrações agregados) → FASE C10.
        // Satélites — status agregado — C10.
        Route::get('satelites/relatorios', [SateliteStatusController::class, 'relatorios']);
        Route::get('satelites/monitoramento', [SateliteStatusController::class, 'monitoramento']);
        Route::get('satelites/integracoes', [SateliteStatusController::class, 'integracoes']);
    });

    // ── App mobile (cliente + entregador) — N10 ──
    // F3 (segurança): sub-grupos por PAPEL do token (`approle`) — token de cliente
    // não alcança rota de entregador (frota/jornada/missões) e vice-versa. As
    // comuns (logout/refresh/devices) valem para qualquer token de app.
    Route::prefix('app/v1')->group(function () {
        Route::post('logout', [AppAuthController::class, 'logout']);
        Route::post('token/refresh', [AppAuthController::class, 'refresh']); // P1 — rotação de token do app
        Route::post('devices', [AppAuthController::class, 'registrarDevice']);

        Route::middleware('approle:cliente')->group(function () {
            // Cliente — LOJA (catálogo/config) — B-1: AppLojaController
            Route::get('init', [AppLojaController::class, 'init']);
            Route::get('produtos', [AppLojaController::class, 'produtos']);
            Route::get('cupom', [AppLojaController::class, 'cupom']);
            Route::post('carrinho/cotacao', [AppLojaController::class, 'cotar']); // F3 — preço server-side
            Route::get('config', [AppLojaController::class, 'config']);           // F3 — config do app
            Route::get('reseller', [AppLojaController::class, 'reseller']);        // F3b — dados da revenda
            Route::get('feriados', [AppLojaController::class, 'feriados']);        // F3b — feriados (agendamento)
            Route::get('poligonos', [AppLojaController::class, 'poligonos']);      // F3b — polígonos de entrega
            // Cliente — PERFIL/ENDEREÇOS — B-1: AppPerfilController
            Route::get('perfil', [AppPerfilController::class, 'perfil']);                    // F3b
            Route::put('perfil', [AppPerfilController::class, 'atualizarPerfil']);            // F3b
            Route::delete('perfil', [AppPerfilController::class, 'excluirConta']);            // F3b
            Route::get('perfil/endereco', [AppPerfilController::class, 'obterEndereco']);   // F3
            Route::put('perfil/endereco', [AppPerfilController::class, 'atualizarEndereco']); // F3
            // Múltiplos endereços de entrega (F3b)
            Route::get('enderecos', [AppPerfilController::class, 'listarEnderecos']);
            Route::post('enderecos', [AppPerfilController::class, 'criarEndereco']);
            Route::put('enderecos/{id}', [AppPerfilController::class, 'editarEndereco'])->whereNumber('id');
            Route::put('enderecos/{id}/favorito', [AppPerfilController::class, 'favoritarEndereco'])->whereNumber('id');
            Route::delete('enderecos/{id}', [AppPerfilController::class, 'excluirEndereco'])->whereNumber('id');
            // Cliente — PEDIDO — B-1: AppPedidoController
            Route::get('pedidos', [AppPedidoController::class, 'historico']);
            Route::post('pedidos', [AppPedidoController::class, 'criarPedido']);
            Route::get('pedidos/{id}', [AppPedidoController::class, 'acompanhar'])->whereNumber('id');
            Route::get('pedidos/{id}/rota-entregador', [AppPedidoController::class, 'rotaEntregador'])->whereNumber('id');
            Route::post('pedidos/{id}/pagar', [AppPedidoController::class, 'pagar'])->whereNumber('id');
            Route::post('pedidos/{id}/pix', [AppPedidoController::class, 'gerarPix'])->whereNumber('id'); // F4
            Route::get('pedidos/{id}/pix/status', [AppPedidoController::class, 'statusPix'])->whereNumber('id'); // F4
            Route::post('pedidos/{id}/cancelar', [AppPedidoController::class, 'cancelar'])->whereNumber('id');
            Route::post('pedidos/{id}/avaliar', [AppPedidoController::class, 'avaliar'])->whereNumber('id');
        });

        // F7 — `idempotente` cobre as escritas do campo: o app em rota enfileira
        // localmente quando perde sinal, e o reenvio (com Idempotency-Key) não
        // repete o efeito. Sem o cabeçalho, passa direto — é opt-in.
        Route::middleware(['approle:entregador', 'idempotente'])->prefix('entregador')->group(function () {
            // Jornada (L4)
            Route::get('veiculos', [AppEntregadorController::class, 'veiculos']);
            Route::get('jornada', [AppEntregadorController::class, 'jornadaAtual']);
            Route::post('jornada/iniciar', [AppEntregadorController::class, 'iniciarJornada']);
            Route::post('jornada/encerrar', [AppEntregadorController::class, 'encerrarJornada']);
            Route::get('dashboard', [AppEntregadorController::class, 'dashboard']);
            Route::get('rota', [AppEntregadorController::class, 'rota']);
            Route::post('rota/iniciar', [AppEntregadorController::class, 'iniciarRota']);

            // Entregas
            Route::get('pedidos', [AppEntregadorController::class, 'pedidos']);
            Route::post('pedidos/{id}/status', [AppEntregadorController::class, 'atualizarStatus'])->whereNumber('id');
            // Ping de posição (P6) — throttle alto (envio frequente do GPS).
            Route::post('posicao', [AppEntregadorController::class, 'posicao'])->middleware('throttle:gps-ping');
            // Ciclo da entrega (P7): aceite/recusa, ocorrência, conclusão com comprovação.
            Route::post('pedidos/{id}/aceitar', [AppEntregadorController::class, 'aceitar'])->whereNumber('id');
            Route::post('pedidos/{id}/recusar', [AppEntregadorController::class, 'recusar'])->whereNumber('id');
            Route::post('pedidos/{id}/ocorrencia', [AppEntregadorController::class, 'ocorrencia'])->whereNumber('id');
            Route::post('pedidos/{id}/concluir', [AppEntregadorController::class, 'concluir'])->whereNumber('id');

            // Missões de campo (L7/L8)
            Route::get('missao', [AppMissaoController::class, 'atual']);
            Route::post('missao/iniciar', [AppMissaoController::class, 'iniciar']);
            Route::post('missao/visitas', [AppMissaoController::class, 'registrarVisita'])->middleware('throttle:missao-visita');
            Route::post('missao/trilha', [AppMissaoController::class, 'trilha'])->middleware('throttle:gps-ping');
            Route::get('missao/proxima-casa', [AppMissaoController::class, 'proximaCasa']);
            Route::post('missao/adiar', [AppMissaoController::class, 'adiar']);
            Route::post('missao/concluir', [AppMissaoController::class, 'concluir']);
            Route::get('missao/produtos', [AppMissaoController::class, 'produtos']);
            Route::post('missao/venda', [AppMissaoController::class, 'venderGas']);
            Route::post('missao/vale-gas', [AppMissaoController::class, 'venderValeGas']);
            Route::post('missao/clientes', [AppMissaoController::class, 'cadastrarCliente']);

            // F4 — solicitação de venda à Central. O franqueado não fatura: ele
            // pede, e a Central cria/aprova/fatura. Nada aqui move estoque.
            Route::get('solicitacoes', [AppSolicitacaoController::class, 'index']);
            Route::post('solicitacoes', [AppSolicitacaoController::class, 'store']);
            Route::post('solicitacoes/{id}/cancelar', [AppSolicitacaoController::class, 'cancelar'])->whereNumber('id');

            // F5 — extrato de remuneracao do proprio usuario (comissao/repasse).
            Route::get('extrato', [AppSolicitacaoController::class, 'extrato']);
            Route::get('estoque', [AppSolicitacaoController::class, 'estoque']);

            // Telas portadas dos apps legados: busca/cadastro de cliente (o que
            // destrava a venda em campo), vale-gas e relatorio do vendedor.
            Route::get('clientes', [AppSolicitacaoController::class, 'clientes']);
            Route::post('clientes', [AppSolicitacaoController::class, 'cadastrarCliente']);
            Route::post('vale-gas/verificar', [AppSolicitacaoController::class, 'verificarValeGas']);
            Route::get('relatorio-vendas', [AppSolicitacaoController::class, 'relatorioVendas']);

            // F8 — cupom em TEXTO para impressora termica. O servidor decide o
            // conteudo; o app so transmite os bytes pela camada Bluetooth.
            Route::get('pedidos/{id}/cupom', [AppSolicitacaoController::class, 'cupomPedido'])->whereNumber('id');

            // F6 — emissão fiscal em campo: SÓ o vendedor industrial. O papel
            // vem do vínculo do colaborador (AppAuthController::abilitiesDe).
            Route::middleware('approle:industrial')->prefix('fiscal')->group(function () {
                Route::post('emitir', [AppFiscalController::class, 'emitir']);
                Route::get('notas/{id}/danfe', [AppFiscalController::class, 'danfe'])->whereNumber('id');
                Route::get('notas/{id}/cupom', [AppFiscalController::class, 'cupomNota'])->whereNumber('id');
            });
        });
    });
});

/*
| ── F0: PONTE PARA OS APPS LEGADOS ──────────────────────────────────────────
|
| MovelApp e NFWEB só falam com o ctrl-web. Estas rotas reproduzem o contrato
| dele (nome do endpoint, form-urlencoded, envelope {status, dados|data} sempre
| em HTTP 200) para que os APKs em campo apontem ao erp-novo sem republicação —
| o MovelApp está em targetSdk 28 e não publica na Play Store hoje.
|
| Duas coisas NÃO são reproduzidas, de propósito:
|  - o IDOR de tenant (revenda_id é conferido contra o token, não obedecido);
|  - a autorização por androidid (quem decide o que se vê é o usuário logado).
|
| Ponte com data para morrer: sai junto com os legados (F9).
*/
Route::prefix('legado')
    // Ordem importa: `dialeto.legado` vem ANTES de `revenda.legado` para
    // envolvê-lo — senão o 403 da revenda divergente sai cru e o app, que só
    // entende HTTP 200, trata como falha de rede em vez de mostrar a mensagem.
    ->middleware(['auth:sanctum', 'tenant', 'dialeto.legado:dados', 'revenda.legado'])
    ->group(function () {
        Route::post('getPedidosPendentes', [PonteMovelAppController::class, 'pedidosPendentes']);
        Route::post('setPedidoSituacao', [PonteMovelAppController::class, 'setPedidoSituacao']);
        Route::post('getPedidosSituacoes', [PonteMovelAppController::class, 'situacoes']);
        Route::post('getVeiculos', [PonteMovelAppController::class, 'veiculos']);
        Route::post('getPedidosMotivosAtrasos', [PonteMovelAppController::class, 'motivosAtraso']);
        // Os 11 endpoints do MovelApp, sem exceção — o app tem de continuar
        // inteiro depois de apontar para ca.
        Route::post('getEmpresas', [PonteMovelAppController::class, 'empresas']);
        Route::post('getUsuarios', [PonteMovelAppController::class, 'usuarios']);
        Route::post('getValeGas', [PonteMovelAppController::class, 'valeGas']);
        Route::post('setVeiculoAtivo', [PonteMovelAppController::class, 'setVeiculoAtivo']);
        Route::post('getPedidosReport', [PonteMovelAppController::class, 'pedidosReport']);
        Route::post('setAndroidMensagem', [PonteMovelAppController::class, 'setAndroidMensagem']);
    });

// F0 — ponte do NFWEB. Envelope `data` (Http.js:164 do app), diferente do
// MovelApp que le `dados`. savePedido vira SOLICITACAO: a regra do cliente e que
// o vendedor pede e a Central decide.
Route::prefix('legado/nfweb')
    ->middleware(['auth:sanctum', 'tenant', 'dialeto.legado:data', 'revenda.legado'])
    ->group(function () {
        Route::post('init', [PonteNfwebController::class, 'init']);
        Route::post('getCliente', [PonteNfwebController::class, 'getCliente']);
        Route::post('savePedido', [PonteNfwebController::class, 'savePedido']);
        Route::post('getParcelasVencidasCliente', [PonteNfwebController::class, 'parcelasVencidas']);
        Route::get('pedidoConsulta', [PonteNfwebController::class, 'pedidoConsulta']);
        Route::get('nfeConsulta', [PonteNfwebController::class, 'nfeConsulta']);
        Route::get('visualizarDanfe', [PonteNfwebController::class, 'visualizarDanfe']);
        // As 18 rotas do legado, sem exceção: o app tem de continuar inteiro
        // depois de apontar para ca.
        Route::post('login', [PonteNfwebController::class, 'login']);
        Route::get('getCadastros', [PonteNfwebController::class, 'getCadastros']);
        Route::post('saveCliente', [PonteNfwebController::class, 'saveCliente']);
        Route::post('saveClienteObs', [PonteNfwebController::class, 'saveClienteObs']);
        Route::post('changeVeiculo', [PonteNfwebController::class, 'changeVeiculo']);
        Route::post('changeRegistrationId', [PonteNfwebController::class, 'changeRegistrationId']);
        Route::get('pedidosReport', [PonteNfwebController::class, 'pedidosReport']);
        Route::get('pedidoDuplicata', [PonteNfwebController::class, 'pedidoDuplicata']);
        Route::get('visualizarBoleto', [PonteNfwebController::class, 'visualizarBoleto']);
        Route::get('baixarDanfe', [PonteNfwebController::class, 'baixarDanfe']);
        Route::get('enviarEmail', [PonteNfwebController::class, 'enviarEmail']);
    });

/*
| ── SuperAdmin (P4) — administração CROSS-TENANT da plataforma ──
| Guard 'platform' (token Sanctum sobre platform_admins), SEPARADO do tenant.
| O SuperAdmin não resolve tenant (não usa o middleware 'tenant'): opera sobre
| todas as empresas via SuperAdminService, com TODA ação auditada. É a única
| superfície que cruza o sigilo entre empresas — por isso fica isolada aqui.
*/
Route::prefix('superadmin')->group(function () {
    Route::post('login', [SuperAdminAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:platform', 'throttle:api'])->group(function () {
        Route::post('logout', [SuperAdminAuthController::class, 'logout']);
        Route::get('me', [SuperAdminAuthController::class, 'me']);

        // Dashboard + auditoria cross-tenant.
        Route::get('dashboard', [SuperAdminPainelController::class, 'dashboard']);
        Route::get('auditoria', [SuperAdminPainelController::class, 'auditoria']);

        // Empresas (cross-tenant) — suspender/reativar, assinatura, overrides.
        Route::get('empresas', [SuperAdminEmpresaController::class, 'index']);
        Route::post('empresas/{id}/suspender', [SuperAdminEmpresaController::class, 'suspender'])->whereNumber('id');
        Route::post('empresas/{id}/reativar', [SuperAdminEmpresaController::class, 'reativar'])->whereNumber('id');
        Route::put('empresas/{id}/assinatura', [SuperAdminEmpresaController::class, 'definirAssinatura'])->whereNumber('id');
        Route::put('empresas/{id}/assinatura/status', [SuperAdminEmpresaController::class, 'alterarStatus'])->whereNumber('id');
        Route::get('empresas/{id}/recursos', [SuperAdminEmpresaController::class, 'recursos'])->whereNumber('id');
        Route::put('empresas/{id}/override', [SuperAdminEmpresaController::class, 'override'])->whereNumber('id');
        Route::delete('empresas/{id}/override/{chave}', [SuperAdminEmpresaController::class, 'removerOverride'])->whereNumber('id');

        // Planos (catálogo global).
        Route::get('planos', [SuperAdminPlanoController::class, 'index']);
        Route::post('planos', [SuperAdminPlanoController::class, 'store']);
        Route::put('planos/{id}', [SuperAdminPlanoController::class, 'update'])->whereNumber('id');

        // Ferramenta de MIGRAÇÃO de sistemas antigos. Cross-tenant por natureza
        // (lê o banco legado inteiro e pode criar empresas), daí ficar aqui.
        Route::get('migracoes', [SuperAdminMigracaoController::class, 'index']);
        Route::post('migracoes', [SuperAdminMigracaoController::class, 'store']);
        Route::get('migracoes/{id}', [SuperAdminMigracaoController::class, 'show'])->whereNumber('id');
        Route::post('migracoes/{id}/conectar', [SuperAdminMigracaoController::class, 'conectar'])->whereNumber('id');
        Route::post('migracoes/{id}/diagnosticar', [SuperAdminMigracaoController::class, 'diagnosticar'])->whereNumber('id');
        Route::put('migracoes/{id}/mapeamento', [SuperAdminMigracaoController::class, 'mapeamento'])->whereNumber('id');
        Route::post('migracoes/{id}/simular', [SuperAdminMigracaoController::class, 'simular'])->whereNumber('id');
        Route::post('migracoes/{id}/executar', [SuperAdminMigracaoController::class, 'executar'])->whereNumber('id');
        Route::get('migracoes/{id}/validar', [SuperAdminMigracaoController::class, 'validar'])->whereNumber('id');
        Route::get('migracoes/{id}/descartes', [SuperAdminMigracaoController::class, 'descartes'])->whereNumber('id');
        Route::get('migracoes/{id}/descartes.csv', [SuperAdminMigracaoController::class, 'descartesCsv'])->whereNumber('id');

        // Cidades da plataforma (catálogo global).
        Route::get('cidades', [SuperAdminPainelController::class, 'cidades']);
        Route::post('cidades', [SuperAdminPainelController::class, 'cidadeStore']);
        Route::put('cidades/{id}', [SuperAdminPainelController::class, 'cidadeUpdate'])->whereNumber('id');
        Route::delete('cidades/{id}', [SuperAdminPainelController::class, 'cidadeDestroy'])->whereNumber('id');
    });
});
