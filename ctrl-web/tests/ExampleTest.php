<?php

use Tests\TestCase;

/**
 * FASE 2: corrigido. O boilerplate original estendia `TestCase` (global), mas
 * o tests/TestCase.php deste projeto está no namespace `Tests\` — então este
 * arquivo nunca rodava e derrubava toda a suíte. Agora é um sanity check do boot.
 */
class ExampleTest extends TestCase
{
    /**
     * O container da aplicação inicializa (framework + providers carregam).
     */
    public function testAplicacaoInicializa()
    {
        $app = $this->createApplication();
        $this->assertNotNull($app);
        $this->assertTrue($app->bound('config'));
    }
}
