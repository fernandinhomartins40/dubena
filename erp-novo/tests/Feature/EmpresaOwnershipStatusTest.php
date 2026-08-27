<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaOwnershipStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresa_nova_fica_explicitamente_sem_titularidade_classificada(): void
    {
        $empresa = Empresa::factory()->create()->refresh();

        $this->assertSame(Empresa::OWNERSHIP_UNRESOLVED, $empresa->ownership_status);
    }
}
