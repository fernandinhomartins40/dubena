<?php

use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Api\Admin\BoletoController;
use App\Http\Controllers\Api\Admin\CadastroApoioController;
use App\Http\Controllers\Api\Admin\CaixaController;
use App\Http\Controllers\Api\Admin\ChequeController;
use App\Http\Controllers\Api\Admin\ClienteController;
use App\Http\Controllers\Api\Admin\ClienteSubrecursoController;
use App\Http\Controllers\Api\Admin\ClienteTelefoneController;
use App\Http\Controllers\Api\Admin\ColaboradorController;
use App\Http\Controllers\Api\Admin\ComodatoController;
use App\Http\Controllers\Api\Admin\ConfigFiscalController;
use App\Http\Controllers\Api\Admin\ConvenioController;
use App\Http\Controllers\Api\Admin\EmpresaConfigController;
use App\Http\Controllers\Api\Admin\EmpresaController;
use App\Http\Controllers\Api\Admin\EstoqueController;
use App\Http\Controllers\Api\Admin\FinanceiroCadastroController;
use App\Http\Controllers\Api\Admin\FinanceiroController;
use App\Http\Controllers\Api\Admin\FiscalConfigController;
use App\Http\Controllers\Api\Admin\GeoController;
use App\Http\Controllers\Api\Admin\GrupoController;
use App\Http\Controllers\Api\Admin\LookupController;
use App\Http\Controllers\Api\Admin\MonitoraController;
use App\Http\Controllers\Api\Admin\NotaFiscalController;
use App\Http\Controllers\Api\Admin\PedidoController;
use App\Http\Controllers\Api\Admin\PixController;
use App\Http\Controllers\Api\Admin\ProdutoConfigController;
use App\Http\Controllers\Api\Admin\ProdutoController;
use App\Http\Controllers\Api\Admin\ProdutoPrecoController;
use App\Http\Controllers\Api\Admin\RegiaoController;
use App\Http\Controllers\Api\Admin\RelatorioController;
use App\Http\Controllers\Api\Admin\SateliteStatusController;
use App\Http\Controllers\Api\Admin\SetorController;
use App\Http\Controllers\Api\Admin\ValeGasController;
use App\Http\Controllers\Api\Admin\VeiculoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Mobile\AppAuthController;
use App\Http\Controllers\Api\Mobile\AppClienteController;
use App\Http\Controllers\Api\Mobile\AppEntregadorController;
use App\Http\Controllers\Api\PixWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Rotas da API (JSON). Toda a SPA e os apps consomem por aqui.
| Contrato: JSON uniforme, número cru, sem View/Redirect.
*/

// Autenticação (pública).
Route::post('/login', [AuthController::class, 'login']);

// Webhook PIX (PÚBLICO — o PSP chama de fora; segurança no controller/service) — N7.
Route::post('/pix/webhook', [PixWebhookController::class, 'handle']);

// Login do app mobile (PÚBLICO) — N10. Token real por usuário/colaborador.
Route::post('/app/v1/login', [AppAuthController::class, 'login']);

// Rotas autenticadas (Sanctum) + tenant resolvido por requisição.
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
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

        // Grupos (redes) — C1.
        Route::get('grupos', [GrupoController::class, 'index']);
        Route::post('grupos', [GrupoController::class, 'store']);
        Route::put('grupos/{id}', [GrupoController::class, 'update'])->whereNumber('id');
        Route::delete('grupos/{id}', [GrupoController::class, 'destroy'])->whereNumber('id');

        // Regiões de atendimento.
        Route::get('regioes', [RegiaoController::class, 'index']);
        Route::post('regioes', [RegiaoController::class, 'store']);
        Route::put('regioes/{id}', [RegiaoController::class, 'update'])->whereNumber('id');
        Route::delete('regioes/{id}', [RegiaoController::class, 'destroy'])->whereNumber('id');

        // Cadastros de apoio genéricos (parametrizados por tipo).
        Route::get('cadastros/{tipo}', [CadastroApoioController::class, 'index']);
        Route::post('cadastros/{tipo}', [CadastroApoioController::class, 'store']);
        Route::put('cadastros/{tipo}/{id}', [CadastroApoioController::class, 'update'])->whereNumber('id');
        Route::delete('cadastros/{tipo}/{id}', [CadastroApoioController::class, 'destroy'])->whereNumber('id');

        // ── Geográfico (cidades/bairros/ruas) — N2 ──
        Route::get('geo/{entidade}', [GeoController::class, 'index']);
        Route::post('geo/{entidade}', [GeoController::class, 'store']);
        Route::put('geo/{entidade}/{id}', [GeoController::class, 'update'])->whereNumber('id');
        Route::delete('geo/{entidade}/{id}', [GeoController::class, 'destroy'])->whereNumber('id');

        // ── Clientes — N2 ──
        Route::get('clientes', [ClienteController::class, 'index']);
        Route::post('clientes', [ClienteController::class, 'store']);
        Route::get('clientes/{id}', [ClienteController::class, 'show'])->whereNumber('id');
        Route::put('clientes/{id}', [ClienteController::class, 'update'])->whereNumber('id');
        Route::delete('clientes/{id}', [ClienteController::class, 'destroy'])->whereNumber('id');

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

        // Estoque — requisição/inventário/físico + abertura de fechamento: módulo
        // completo na FASE C4 (precisam de tabelas novas). Stub 501 até lá.
        // Estoque — requisições / inventário / físico / abertura de fechamento — C11.
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
        Route::get('pedidos', [PedidoController::class, 'index']);
        Route::post('pedidos', [PedidoController::class, 'store']);
        Route::get('pedidos/{id}', [PedidoController::class, 'show'])->whereNumber('id');
        Route::put('pedidos/{id}', [PedidoController::class, 'update'])->whereNumber('id');
        Route::delete('pedidos/{id}', [PedidoController::class, 'destroy'])->whereNumber('id');
        Route::put('pedidos/{id}/situacao', [PedidoController::class, 'mudarSituacao'])->whereNumber('id');

        // ── Financeiro (a pagar/receber) — N5 ──
        Route::get('financeiro/lancamentos/resumo', [FinanceiroController::class, 'resumo']);
        Route::get('financeiro/lancamentos', [FinanceiroController::class, 'lancamentos']);
        Route::post('financeiro/lancamentos', [FinanceiroController::class, 'criar']);
        Route::delete('financeiro/lancamentos/{id}', [FinanceiroController::class, 'cancelar'])->whereNumber('id');

        Route::get('financeiro/planos-conta', [FinanceiroCadastroController::class, 'planosIndex']);
        Route::post('financeiro/planos-conta', [FinanceiroCadastroController::class, 'planoSalvar']);
        Route::put('financeiro/planos-conta/{id}', [FinanceiroCadastroController::class, 'planoSalvar'])->whereNumber('id');
        Route::delete('financeiro/planos-conta/{id}', [FinanceiroCadastroController::class, 'planoExcluir'])->whereNumber('id');

        Route::get('financeiro/centros-custo', [FinanceiroCadastroController::class, 'centrosIndex']);
        Route::post('financeiro/centros-custo', [FinanceiroCadastroController::class, 'centroSalvar']);
        Route::put('financeiro/centros-custo/{id}', [FinanceiroCadastroController::class, 'centroSalvar'])->whereNumber('id');
        Route::delete('financeiro/centros-custo/{id}', [FinanceiroCadastroController::class, 'centroExcluir'])->whereNumber('id');

        // ── Caixa / Conta — N6 ──
        Route::get('caixa/contas', [CaixaController::class, 'contas']);
        Route::post('caixa/contas', [CaixaController::class, 'criarConta']);
        Route::post('caixa/transferencias', [CaixaController::class, 'transferir']);
        Route::post('caixa/movimentos/{movimentoId}/estornar', [CaixaController::class, 'estornar'])->whereNumber('movimentoId');
        Route::get('caixa/{contaId}/movimentos', [CaixaController::class, 'movimentos'])->whereNumber('contaId');
        Route::post('caixa/{contaId}/abrir', [CaixaController::class, 'abrir'])->whereNumber('contaId');
        Route::post('caixa/{contaId}/fechar', [CaixaController::class, 'fechar'])->whereNumber('contaId');
        Route::post('caixa/{contaId}/baixar', [CaixaController::class, 'baixar'])->whereNumber('contaId');

        // ── Cheques — N6 ──
        Route::get('cheques/recebidos', [ChequeController::class, 'recebidos']);
        Route::get('cheques/emitidos', [ChequeController::class, 'emitidos']);
        Route::post('cheques', [ChequeController::class, 'store']);
        Route::put('cheques/{id}', [ChequeController::class, 'update'])->whereNumber('id');
        Route::delete('cheques/{id}', [ChequeController::class, 'destroy'])->whereNumber('id');
        Route::put('cheques/{id}/situacao', [ChequeController::class, 'mudarSituacao'])->whereNumber('id');

        // Aliases da SPA: cheques/recebidos (CRUD) → ChequeController.
        Route::post('cheques/recebidos', [ChequeController::class, 'store']);
        Route::put('cheques/recebidos/{id}', [ChequeController::class, 'update'])->whereNumber('id');
        Route::delete('cheques/recebidos/{id}', [ChequeController::class, 'destroy'])->whereNumber('id');

        // ── Boletos (CNAB) — N7 (gate) ──
        Route::get('boletos/resumo', [BoletoController::class, 'resumo']);
        Route::get('boletos', [BoletoController::class, 'index']);
        Route::post('boletos', [BoletoController::class, 'gerar']);
        Route::post('boletos/remessa', [BoletoController::class, 'remessa']);
        Route::post('boletos/retorno', [BoletoController::class, 'retorno']);

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

        // ── Comodato — N8 ──
        Route::get('comodatos', [ComodatoController::class, 'index']);
        Route::post('comodatos', [ComodatoController::class, 'store']);
        Route::post('comodatos/{id}/devolver', [ComodatoController::class, 'devolver'])->whereNumber('id');

        // ── Fiscal (NF-e/NFC-e/CF-e) — N9 ──
        Route::get('fiscal/config', [ConfigFiscalController::class, 'show']);
        Route::put('fiscal/config', [ConfigFiscalController::class, 'update']);
        Route::get('notas', [NotaFiscalController::class, 'index']);
        Route::post('notas/emitir', [NotaFiscalController::class, 'emitir']);
        Route::get('notas/{id}', [NotaFiscalController::class, 'show'])->whereNumber('id');
        Route::post('notas/{id}/cancelar', [NotaFiscalController::class, 'cancelar'])->whereNumber('id');

        // Aliases consumidos pela SPA (fiscal/nfe*) → NotaFiscalController.
        Route::get('fiscal/nfe', [NotaFiscalController::class, 'index']);
        Route::post('fiscal/nfe/{id}/transmitir', [NotaFiscalController::class, 'transmitir'])->whereNumber('id');
        Route::post('fiscal/nfe/{id}/cancelar', [NotaFiscalController::class, 'cancelar'])->whereNumber('id');

        // Fiscal — operações fiscais e malha (config por tipo) — C12.
        Route::get('fiscal/operacoes', [FiscalConfigController::class, 'operacoesIndex']);
        Route::post('fiscal/operacoes', [FiscalConfigController::class, 'operacaoSalvar']);
        Route::put('fiscal/operacoes/{id}', [FiscalConfigController::class, 'operacaoSalvar'])->whereNumber('id');
        Route::delete('fiscal/operacoes/{id}', [FiscalConfigController::class, 'operacaoExcluir'])->whereNumber('id');
        Route::get('fiscal/malha/{tipo}', [FiscalConfigController::class, 'malhaIndex']);
        Route::post('fiscal/malha/{tipo}', [FiscalConfigController::class, 'malhaSalvar']);
        Route::put('fiscal/malha/{tipo}/{id}', [FiscalConfigController::class, 'malhaSalvar'])->whereNumber('id');
        Route::delete('fiscal/malha/{tipo}/{id}', [FiscalConfigController::class, 'malhaExcluir'])->whereNumber('id');
        Route::get('fiscal/sped', [NotaFiscalController::class, 'sped']);

        // ── Monitora (GPS) — N11 (módulo isolado) ──
        Route::get('monitora/veiculos', [MonitoraController::class, 'veiculos']);
        Route::post('monitora/veiculos', [MonitoraController::class, 'criarVeiculo']);
        Route::post('monitora/veiculos/{id}/posicoes', [MonitoraController::class, 'ingerirPosicao'])->whereNumber('id');
        Route::get('monitora/ultimas-posicoes', [MonitoraController::class, 'ultimasPosicoes']);
        Route::get('monitora/cercas', [MonitoraController::class, 'cercas']);
        Route::post('monitora/cercas', [MonitoraController::class, 'criarCerca']);
        Route::post('monitora/sync', [MonitoraController::class, 'sincronizar']);

        // ── Relatórios (Query Services) — N12 ──
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
        // Alias da SPA: financeiro/dre → relatórios/dre (mesma função).
        Route::get('financeiro/dre', [RelatorioController::class, 'dre']);
        // Conciliação bancária (OFX): FASE C8. Stub 501.
        // Conciliação bancária (OFX) — C8.
        Route::get('financeiro/conciliacao', [FinanceiroController::class, 'conciliacao']);
        Route::post('financeiro/conciliacao', [FinanceiroController::class, 'conciliacao']);

        // ── RH / Colaboradores — C5 ──
        Route::get('colaboradores', [ColaboradorController::class, 'index']);
        Route::post('colaboradores', [ColaboradorController::class, 'store']);
        Route::get('colaboradores/{id}', [ColaboradorController::class, 'show'])->whereNumber('id');
        Route::put('colaboradores/{id}', [ColaboradorController::class, 'update'])->whereNumber('id');
        Route::delete('colaboradores/{id}', [ColaboradorController::class, 'destroy'])->whereNumber('id');
        Route::get('colaboradores/{id}/familia', [ColaboradorController::class, 'familia'])->whereNumber('id');
        Route::post('colaboradores/{id}/familia', [ColaboradorController::class, 'addFamilia'])->whereNumber('id');
        Route::delete('colaboradores/{id}/familia/{famId}', [ColaboradorController::class, 'delFamilia'])->whereNumber(['id', 'famId']);
        Route::get('colaboradores/{id}/recessos', [ColaboradorController::class, 'recessos'])->whereNumber('id');
        Route::get('colaboradores/{id}/comissoes', [ColaboradorController::class, 'comissoes'])->whereNumber('id');

        // ── Módulos de fases futuras consumidos pela SPA (stub 501 documentado) ──

        // ── Frota / Veículos — C6 ──
        Route::get('veiculos', [VeiculoController::class, 'index']);
        Route::post('veiculos', [VeiculoController::class, 'store']);
        Route::get('veiculos/{id}', [VeiculoController::class, 'show'])->whereNumber('id');
        Route::put('veiculos/{id}', [VeiculoController::class, 'update'])->whereNumber('id');
        Route::delete('veiculos/{id}', [VeiculoController::class, 'destroy'])->whereNumber('id');
        Route::get('veiculos/{id}/abastecimentos', [VeiculoController::class, 'abastecimentos'])->whereNumber('id');
        Route::post('veiculos/{id}/abastecimentos', [VeiculoController::class, 'registrarAbastecimento'])->whereNumber('id');
        Route::get('veiculos/{id}/trocas-oleo', [VeiculoController::class, 'trocasOleo'])->whereNumber('id');
        Route::get('veiculos/{id}/pneus', [VeiculoController::class, 'pneus'])->whereNumber('id');

        // Satélites (relatórios/monitoramento/integrações agregados) → FASE C10.
        // Satélites — status agregado — C10.
        Route::get('satelites/relatorios', [SateliteStatusController::class, 'relatorios']);
        Route::get('satelites/monitoramento', [SateliteStatusController::class, 'monitoramento']);
        Route::get('satelites/integracoes', [SateliteStatusController::class, 'integracoes']);
    });

    // ── App mobile (cliente + entregador) — N10 ──
    Route::prefix('app/v1')->group(function () {
        Route::post('logout', [AppAuthController::class, 'logout']);
        Route::post('devices', [AppAuthController::class, 'registrarDevice']);

        // Cliente
        Route::get('produtos', [AppClienteController::class, 'produtos']);
        Route::post('pedidos', [AppClienteController::class, 'criarPedido']);
        Route::post('pedidos/{id}/pagar', [AppClienteController::class, 'pagar'])->whereNumber('id');

        // Entregador
        Route::get('entregador/pedidos', [AppEntregadorController::class, 'pedidos']);
        Route::post('entregador/pedidos/{id}/status', [AppEntregadorController::class, 'atualizarStatus'])->whereNumber('id');
    });
});
