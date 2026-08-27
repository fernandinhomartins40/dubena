<?php

namespace Tests\Feature;

use Tests\TestCase;

class SaasCatalogarCommandTest extends TestCase
{
    public function test_recusa_banco_que_nao_prova_rls_efetiva(): void
    {
        $this->artisan('saas:catalogar --connection=sqlite --output=storage/framework/catalogo-invalido.json')
            ->expectsOutputToContain('exige PostgreSQL')
            ->assertExitCode(1);
    }
}
