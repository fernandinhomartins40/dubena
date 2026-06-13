<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExampleTest extends TestCase
{
    /**
     * FASE 2: corrigido. O boilerplate original fazia `visit('/')->see('Laravel 5')`
     * — texto inexistente na home, então sempre falhava. Agora é sanity check do boot.
     */
    public function testAplicacaoInicializa()
    {
        $app = $this->createApplication();
        $this->assertNotNull($app);
        $this->assertTrue($app->bound('config'));
    }
}
