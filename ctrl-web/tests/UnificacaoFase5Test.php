<?php

use Tests\TestCase;

/**
 * Testes de caracterização — FASE 5 (unificação web + API).
 *
 * Provam que as rotas da API do app mobile (ex-api-app-gc) agora são servidas
 * pelo PRÓPRIO ERP, com os contratos PRESERVADOS (retrocompatibilidade com os
 * apps já publicados nas lojas). Não dependem de dados no banco.
 *
 * PHPUnit 7.5 / Laravel 5.8.
 */
class UnificacaoFase5Test extends TestCase
{
    /**
     * A rota pública /api/video/get (do módulo Api) responde pelo ERP.
     */
    public function testRotaPublicaVideoGetRespondePeloErp()
    {
        $response = $this->get('/api/video/get');
        // Contrato preservado: rota existe e não dá erro de servidor.
        $this->assertLessThan(500, $response->getStatusCode());
    }

    /**
     * /api/getToken (SecretController portado) valida a app_key e responde no
     * formato esperado pelo app (status NOK quando inválida) — contrato mantido.
     */
    public function testGetTokenRejeitaAppKeyInvalidaComContratoPreservado()
    {
        $response = $this->json('GET', '/api/getToken', ['app_key' => 'invalida']);
        $response->assertStatus(404);
        $response->assertJson(['status' => 'NOK']);
    }

    /**
     * As classes do módulo Api foram portadas e são autoloadáveis no ERP.
     */
    public function testClassesDoModuloApiExistem()
    {
        $this->assertTrue(class_exists(\App\Api\Http\Controllers\SecretController::class));
        $this->assertTrue(class_exists(\App\Api\Models\Pedido::class));
        $this->assertTrue(class_exists(\App\Api\Models\ApiModel::class));
        $this->assertTrue(class_exists(\App\Api\Repository\PedidoRepository::class));
        $this->assertTrue(class_exists(\App\Api\Http\Middleware\Access::class));
    }

    /**
     * Os models do módulo Api usam a conexão sgcm_api (tabelas espelho por ora).
     */
    public function testModelsDoModuloApiUsamConexaoSgcmApi()
    {
        $pedido = new \App\Api\Models\Pedido();
        $this->assertEquals('sgcm_api', $pedido->getConnectionName());
    }
}
