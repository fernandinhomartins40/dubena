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
        \Artisan::call('db:seed', ['--class' => 'DeployAdminSeeder', '--force' => true]);

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
        $code = $this->assertRotaOk('GET', $uri);
        $this->assertContains($code, [200, 302, 403, 404],
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

    public function formulariosProvider()
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

    public function modulosProvider()
    {
        // Prioridade: módulos centrais do ERP de distribuição de gás.
        return [
            'cliente'           => ['/cliente'],
            'pedido'            => ['/pedido'],
            'produto'           => ['/produto'],
            'colaborador'       => ['/colaborador'],
            'empresa'           => ['/empresa'],
            'user'              => ['/user'],
            'veiculo'           => ['/veiculo'],
            'estoquefisico'     => ['/estoquefisico'],
            'estoquesetor'      => ['/estoquesetor'],
            'nfemitida'         => ['/nfemitida'],
            'nfrecebida'        => ['/nfrecebida'],
            'conta'             => ['/conta'],
            'planoconta'        => ['/planoconta'],
            'centrocusto'       => ['/centrocusto'],
            'banco'             => ['/banco'],
            'cidade'            => ['/cidade'],
            'bairro'            => ['/bairro'],
            'condicaopagamento' => ['/condicaopagamento'],
            'unidademedida'     => ['/unidademedida'],
            'roles'             => ['/roles'],
        ];
    }
}
