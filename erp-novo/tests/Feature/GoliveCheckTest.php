<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F16 — portão de prontidão de produção (golive:check). Valida a semântica
 * PASS/WARN/FAIL: gates em Fake são WARN (não bloqueiam por padrão), mas
 * config insegura (APP_DEBUG on) é FAIL.
 */
class GoliveCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_debug_ligado_bloqueia_o_golive(): void
    {
        config(['app.debug' => true]);

        $this->artisan('golive:check')
            ->assertExitCode(1)
            ->expectsOutputToContain('PORTÃO FECHADO');
    }

    public function test_config_segura_com_gates_fake_libera_com_avisos(): void
    {
        // Config "homologável": debug off, sem FAIL; gates Fake geram só WARN.
        config([
            'app.debug' => false,
            'services.fiscal.driver' => 'fake',
            'services.cobranca.driver' => 'fake',
        ]);
        Empresa::factory()->create();

        $this->artisan('golive:check')
            ->assertExitCode(0)
            ->expectsOutputToContain('PORTÃO LIBERADO');
    }

    public function test_modo_strict_trata_avisos_como_bloqueio(): void
    {
        // Mesmo sem FAIL, em --strict os WARN (gates Fake) bloqueiam.
        config(['app.debug' => false, 'services.fiscal.driver' => 'fake', 'services.cobranca.driver' => 'fake']);
        Empresa::factory()->create();

        $this->artisan('golive:check --strict')
            ->assertExitCode(1)
            ->expectsOutputToContain('modo strict');
    }

    public function test_pix_habilitado_com_driver_fake_e_falha_critica(): void
    {
        config([
            'app.debug' => false,
            'services.pix.enabled' => true,
            'services.pix.driver' => 'fake',
        ]);
        Empresa::factory()->create();

        $this->artisan('golive:check')
            ->assertExitCode(1)
            ->expectsOutputToContain('PIX habilitado com driver Fake');
    }

    public function test_pix_driver_desconhecido_e_falha_critica(): void
    {
        config([
            'app.debug' => false,
            'services.pix.enabled' => false,
            'services.pix.driver' => 'psp-inexistente',
        ]);
        Empresa::factory()->create();

        $this->artisan('golive:check')
            ->assertExitCode(1)
            ->expectsOutputToContain("driver 'psp-inexistente' não implementado");
    }

    public function test_producao_e_estrita_sem_depender_da_opcao_humana(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => false, 'services.fiscal.driver' => 'fake', 'services.cobranca.driver' => 'fake']);
        Empresa::factory()->create();

        $this->artisan('golive:check')
            ->assertExitCode(1)
            ->expectsOutputToContain('modo strict');
    }

    public function test_certificado_ausente_falha_quando_fiscal_real(): void
    {
        // Gate fiscal real + empresa sem certificado → FAIL (bloqueia).
        config(['app.debug' => false, 'services.fiscal.driver' => 'nfephp']);
        Empresa::factory()->create();

        $this->artisan('golive:check')
            ->assertExitCode(1)
            ->expectsOutputToContain('Certificado A1');
    }
}
