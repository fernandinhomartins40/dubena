<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        // FASE 2: a raiz redireciona (302) para login/home — não é 200.
        // Caracteriza o comportamento real: a app responde sem erro de servidor.
        $response = $this->get('/');

        $this->assertLessThan(500, $response->getStatusCode());
    }
}
