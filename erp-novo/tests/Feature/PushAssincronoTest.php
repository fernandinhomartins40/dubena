<?php

namespace Tests\Feature;

use App\Domain\Mobile\Contracts\PushTransport;
use App\Domain\Mobile\Drivers\FakePushTransport;
use App\Domain\Mobile\Jobs\EnviarPushJob;
use App\Domain\Mobile\PushService;
use App\Models\Empresa;
use App\Models\Mobile\AppDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P0 — Push assíncrono. O PushService deixa de chamar o FCM síncrono no request:
 * resolve os tokens-alvo e ENFILEIRA o EnviarPushJob (transporte FCM v1, isolado
 * atrás de PushTransport). Cobre: enfileiramento, no-op sem token, binding do
 * driver (fake por padrão) e a trait tenant-aware do job.
 */
class PushAssincronoTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_service_enfileira_job_quando_ha_tokens(): void
    {
        Queue::fake();

        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        AppDevice::query()->create([
            'user_id' => $user->id, 'empresa_id' => $empresa->id,
            'device_id' => 'd1', 'push_token' => 'tok-1', 'ativo' => true,
        ]);

        $enfileirados = app(PushService::class)->paraUsuario($user->id, 'Oi', 'Corpo', ['pedido_id' => '7']);

        $this->assertSame(1, $enfileirados);
        Queue::assertPushed(EnviarPushJob::class, function (EnviarPushJob $job) {
            return $job->tokens === ['tok-1'] && $job->titulo === 'Oi' && $job->dados['pedido_id'] === '7';
        });
    }

    public function test_push_service_nao_enfileira_sem_tokens(): void
    {
        Queue::fake();

        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->assertSame(0, app(PushService::class)->paraUsuario($user->id, 'x', 'y'));
        Queue::assertNothingPushed();
    }

    public function test_transporte_padrao_e_fake_e_e_no_op(): void
    {
        $this->assertInstanceOf(FakePushTransport::class, app(PushTransport::class));
        // Fake não faz rede e devolve 0 enviados.
        $this->assertSame(0, app(PushTransport::class)->enviar(['a', 'b'], 't', 'c'));
    }

    public function test_job_sem_enforcement_nao_depende_do_contexto_legado(): void
    {
        // O nome do teste ja diz o modo: fixa-lo evita que o resultado dependa
        // da variavel de ambiente com que a suite foi executada.
        config()->set('saas_transformation.enforcement.tenant_envelope', false);

        $job = new EnviarPushJob(['tok'], 't', 'c');
        $this->assertNull($job->tenantEnvelopePayload);
        $job->handle(new FakePushTransport);
    }
}
