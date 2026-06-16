<?php

namespace Tests\Fluxo;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

/**
 * Teste de COMPATIBILIDADE app↔PostgreSQL, módulo por módulo.
 *
 * Faz login HTTP REAL (POST /handleLogin) — que popula a sessão (empresa_padrao,
 * menu, permissões) como em produção — e então navega pelas telas, garantindo
 * que cada uma responde sem erro de servidor (500) por SQL Oracle/MySQL
 * incompatível ou PHP 7.4. Roda contra o Postgres de dev.
 *
 * Desabilita CSRF (WithoutMiddleware do VerifyCsrfToken) para o POST de teste.
 */
class NavegacaoPostgresTest extends TestCase
{
    /** Faz login real e devolve o app com sessão populada. */
    private function login()
    {
        // L8 prefixa Database\Seeders\ quando a classe não tem '\'. Os seeders do
        // projeto não têm namespace (carregados via classmap) → barra inicial força o global.
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);

        // Desabilita só a verificação de CSRF para o POST de login do teste.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $resp = $this->post('/handleLogin', [
            'email'    => env('ADMIN_SEED_EMAIL', 'admin'),
            'password' => env('ADMIN_SEED_PASSWORD', 'admin1234'),
            'ativo'    => 1,
        ]);

        // handleLogin redireciona para /home em caso de sucesso.
        $this->assertTrue(
            in_array($resp->getStatusCode(), [301, 302]),
            'Login não redirecionou (status ' . $resp->getStatusCode() . ') — auth falhou'
        );
        return $resp;
    }

    /**
     * Acessa uma rota autenticada e garante que não dá 500.
     * Retorna o status para inspeção.
     */
    private function assertRotaOk($metodo, $uri, $msg = '')
    {
        $resp = $this->call($metodo, $uri);
        $code = $resp->getStatusCode();
        // 200 = renderizou de fato (SQL do módulo executou e passou).
        // Um 302 aqui indicaria redirect por falta de permissão (mascarando
        // o SQL): por isso o seeder concede acesso total ao admin de teste.
        $this->assertNotEquals(500, $code,
            "[$uri] retornou 500 — incompatibilidade Postgres/PHP. $msg");
        $this->assertNotEquals(302, $code,
            "[$uri] redirecionou (302) — sem permissão; SQL do módulo não foi exercido.");
        return $code;
    }

    public function test_login_redireciona()
    {
        $this->login();
        $this->assertTrue(true);
    }

    public function test_home()
    {
        $this->login();
        $this->assertRotaOk('GET', '/home');
    }

    /**
     * Telas de listagem (index) dos módulos principais.
     * Cada uma é um GET que executa as queries de listagem do módulo —
     * onde mora a maior parte do SQL Oracle/MySQL incompatível.
     *
     * @dataProvider modulosProvider
     */
    public function test_modulo_index($uri)
    {
        $this->login();

        // Módulos sem item na árvore de menus: o AuthorizeCustom redireciona
        // (302) por falta de permissão ANTES de rodar SQL. Não é incompat. de
        // banco — é tela sem menu. Pula explicitamente (não mascara 500).
        $semMenu = ['/agencia', '/descontocheque', '/nfclastrib', '/nfcst', '/turno'];
        if (in_array($uri, $semMenu)) {
            $resp = $this->call('GET', $uri);
            $this->assertNotEquals(500, $resp->getStatusCode(),
                "[$uri] 500 inesperado mesmo sem menu.");
            $this->markTestSkipped("[$uri] sem item de menu (302 esperado).");
            return;
        }

        $code = $this->assertRotaOk('GET', $uri);
        $this->assertContains($code, [200, 403, 404],
            "[$uri] status inesperado: $code");
    }

    /**
     * Telas de FORMULÁRIO (create) dos módulos transacionais do dia a dia.
     * O create monta dezenas de dropdowns via Repository — onde o SQL de
     * apoio (estados, produtos, condições de pagamento...) é exercido.
     *
     * @dataProvider formulariosProvider
     */
    public function test_formulario_create($uri)
    {
        $this->login();
        $code = $this->assertRotaOk('GET', $uri);
        $this->assertContains($code, [200, 302, 403],
            "[$uri] status inesperado no form: $code");
    }

    /**
     * Telas de RELATÓRIO (index/entrada). GET por nome de rota — exercita as
     * queries de apoio dos relatórios, onde se concentram as funções Oracle
     * (TO_DATE/TO_CHAR/ROWNUM/ADD_MONTHS). Os relatórios que exigem filtro de
     * data são cobertos por test_relatorio_com_data.
     *
     * @dataProvider relatoriosIndexProvider
     */
    public function test_relatorio_index($rota)
    {
        $this->login();
        if (! \Route::has($rota)) {
            $this->markTestSkipped("rota $rota inexistente");
            return;
        }
        $uri = parse_url(route($rota, [], false), PHP_URL_PATH);
        $resp = $this->call('GET', $uri);
        $code = $resp->getStatusCode();
        $this->assertNotEquals(500, $code,
            "[$rota = $uri] retornou 500 — incompatibilidade Postgres/PHP.");
    }

    /**
     * Relatórios COM filtro de data — onde rodam de fato TO_DATE/TO_CHAR/
     * ADD_MONTHS/aritmética de datas. Passa um período fixo (formato d/m/Y,
     * que insertDataOracle converte) e demais filtros "todos".
     *
     * @dataProvider relatoriosComDataProvider
     */
    public function test_relatorio_com_data($rota, array $params)
    {
        $this->login();
        if (! \Route::has($rota)) {
            $this->markTestSkipped("rota $rota inexistente");
            return;
        }
        $uri = parse_url(route($rota, [], false), PHP_URL_PATH);
        // Estes relatórios lêem o superglobal $_GET diretamente (código legado),
        // que o test client do Laravel NÃO popula (só popula o Request). Sem
        // isso, count($_GET)===0 e o relatório devolve só a tela de filtro, sem
        // rodar a query. Populamos $_GET para exercitar de fato o SQL.
        $_GET = $params;
        try {
            $resp = $this->call('GET', $uri, $params);
            $code = $resp->getStatusCode();
        } finally {
            $_GET = [];
        }
        $this->assertNotEquals(500, $code,
            "[$rota] retornou 500 com filtro de data — incompat. Postgres/PHP.");
    }

    public static function relatoriosComDataProvider()
    {
        // Período fixo de teste (d/m/Y); empresa 1; sem cliente específico.
        $ini = '01/05/2026';
        $fim = '31/05/2026';
        $base = ['empresa_id' => '1', 'cliente_id' => ''];

        return [
            'contaspagar (E)' => ['report.contaspagar', $base + [
                'datainicio' => $ini, 'datafim' => $fim, 'tipo' => 'E',
                'situacao' => '1', 'ordem' => 'C',
            ]],
            'contasreceber (V)' => ['report.contasreceber', $base + [
                'datainicio' => $ini, 'datafim' => $fim, 'tipo' => 'V',
                'situacao' => '1', 'ordem' => 'C',
            ]],
            'despesas (filtro)' => ['report.despesas', $base + [
                'datainicio' => $ini, 'datafim' => $fim,
            ]],
            'receitas (filtro)' => ['report.receitas', $base + [
                'datainicio' => $ini, 'datafim' => $fim,
            ]],
            // Dispara a subquery LISTAGG + ROWNUM (telefones do cliente).
            'clientespdf (listagg)' => ['report.clientespdf', [
                'uf' => '0', 'cidade' => '0', 'segmento' => '0',
                'tipo' => '0', 'ativo' => '1',
            ]],
            'fornecedorespdf (listagg)' => ['report.fornecedorespdf', [
                'uf' => '0', 'cidade' => '0', 'segmento' => '0',
                'tipo' => '0', 'ativo' => '1', 'empresa' => '0',
            ]],
        ];
    }

    public static function relatoriosIndexProvider()
    {
        // Relatórios cujo index é GET puro (sem exigir filtro p/ renderizar).
        $rotas = [
            'report.clientes', 'report.fornecedores', 'report.clientesbairros',
            'report.interacoes', 'report.contaspagar', 'report.contasreceber',
            'report.despesas', 'report.receitas', 'report.despesas_cc',
            'report.receitas_cc', 'report.fluxocaixa', 'report.estoquegeral',
            'report.estoqueglp', 'report.estoquerequisicao', 'report.estoquetransferencia',
            'report.movimentacoes', 'report.comissoes', 'report.comodatosativos',
            'report.controlecomodato', 'report.convenio', 'report.conveniofuncionarios',
            'report.colaboradoresaniversariantes', 'report.colaboradoresexames',
            'report.colaboradoresfaixaetaria', 'report.colaboradoresferiasvencimento',
            'report.clientesaniversariantes', 'report.aniversariantescompram',
            'report.incompletos', 'report.gestaofrota', 'report.abastecimento',
        ];
        $out = [];
        foreach ($rotas as $r) {
            $out[$r] = [$r];
        }
        return $out;
    }

    public static function formulariosProvider()
    {
        // Fluxos que o operador usa o dia inteiro: cadastro e telas de venda/caixa.
        return [
            'cliente.create'     => ['/cliente/create'],
            'produto.create'     => ['/produto/create'],
            'colaborador.create' => ['/colaborador/create'],
            'veiculo.create'     => ['/veiculo/create'],
            'conta.create'       => ['/conta/create'],
            'caixa.index'        => ['/caixaindex'],
            'pedido.create'      => ['/pedido/create'],
        ];
    }

    public static function modulosProvider()
    {
        // TODOS os módulos resource do ERP (index = GET /<nome>). Varre a
        // superfície inteira de listagem p/ pescar qualquer SQL incompatível
        // com Postgres que ainda não tenha sido exercido.
        $modulos = [
            'agencia', 'bairro', 'banco', 'boleto', 'cadastrochecklist', 'cargo',
            'centrocusto', 'checklist', 'chequeemitido', 'chequerecebido', 'cidade',
            'cliente', 'clientecontatosituacao', 'clientecontatotipo', 'colaborador',
            'colaboradorcomissoes', 'comodato', 'condicaopagamento', 'consultaestoquesetor',
            'conta', 'contamovimentotipo', 'cupons', 'descontocheque', 'documento',
            'documentotipo', 'empresa', 'empresabens', 'empresaconfig', 'empresas_grupo',
            'estadocivil', 'estoquefisico', 'estoquerequisicao', 'estoquesetor',
            'estoquetransferencias', 'grupofiscal', 'ibpt', 'inventario', 'layoutbancos',
            'maladireta', 'mcmm', 'metavenda', 'motivonaovenda', 'nfclastrib', 'nfcofins',
            'nfcst', 'nfemitida', 'nficms', 'nfimposto', 'nfipi', 'nfoperacao', 'nfpis',
            'nfrecebida', 'nfsituacao', 'ocorrenciasremessas', 'parentesco', 'pedido',
            'pedidomotivoatraso', 'pedidooperacao', 'pedidosituacao', 'planoconta',
            'posvenda', 'posvendacadastro', 'produto', 'produtoclasse', 'promocao',
            'promover', 'recessos', 'regiao', 'remessa', 'roles', 'rua', 'satcfe',
            'segmento', 'setor', 'sorteio', 'spedcontribuicao', 'spedcreditos',
            'spedfiscal', 'telefonetipo', 'tipocombustivel', 'tipodocumento', 'tipoexame',
            'tipopessoa', 'tiporecessos', 'turno', 'unidademedida', 'user',
            'valegascancelar', 'valegasconsulta', 'veiculo', 'veiculoabastecimento',
            'veiculoentradasaida', 'veiculopneu', 'veiculotipo', 'veiculotrocaoleo',
            'vendaativa', 'vendaativaocorrenciatipos', 'vendavalegas',
        ];

        $out = [];
        foreach ($modulos as $m) {
            $out[$m] = ['/' . $m];
        }
        return $out;
    }
}
