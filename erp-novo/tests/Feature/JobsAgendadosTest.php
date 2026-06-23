<?php

namespace Tests\Feature;

use App\Domain\Cobranca\SituacaoPix;
use App\Domain\Tenant\TenantContext;
use App\Models\Cobranca\PixCobranca;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * FASE C9 — comandos agendados (cron) recriados do legado. Testáveis no CI: a
 * lógica de negócio (expirar PIX, apurar vencidos) roda sem dependência externa.
 */
class JobsAgendadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pix_expirar_expira_apenas_vencidas(): void
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $vencida = PixCobranca::create([
            'empresa_id' => $empresa->id, 'txid' => str_repeat('a', 32), 'valor' => 50,
            'situacao' => SituacaoPix::ATIVA->value, 'expira_em' => now()->subHour(),
        ]);
        $valida = PixCobranca::create([
            'empresa_id' => $empresa->id, 'txid' => str_repeat('b', 32), 'valor' => 50,
            'situacao' => SituacaoPix::ATIVA->value, 'expira_em' => now()->addHour(),
        ]);

        $this->artisan('pix:expirar')->assertSuccessful();

        $this->assertSame(SituacaoPix::EXPIRADA, $vencida->refresh()->situacao);
        $this->assertSame(SituacaoPix::ATIVA, $valida->refresh()->situacao);
    }

    public function test_notificar_vencidos_roda(): void
    {
        $this->artisan('financeiro:notificar-vencidos')->assertSuccessful();
    }

    public function test_comandos_estao_agendados(): void
    {
        $saida = Artisan::call('schedule:list');
        $this->assertSame(0, $saida);
        $texto = Artisan::output();
        foreach (['pix:expirar', 'financeiro:notificar-vencidos', 'vendas:diaria', 'notify:inconsistencias'] as $cmd) {
            $this->assertStringContainsString($cmd, $texto);
        }
    }

    public function test_venda_diaria_e_inconsistencias_rodam(): void
    {
        $this->artisan('vendas:diaria')->assertSuccessful();
        $this->artisan('notify:inconsistencias')->assertSuccessful();
    }
}
