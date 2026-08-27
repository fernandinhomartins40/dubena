<?php

namespace Tests\Unit;

use App\Domain\Tenant\TenantEnvelope;
use App\Domain\Tenant\TenantEnvelopeDispatch;
use App\Domain\Tenant\TenantEnvelopeRuntime;
use Tests\TestCase;

class TenantEnvelopeDispatchTest extends TestCase
{
    public function test_reutiliza_envelope_ja_resolvido_no_dispatch(): void
    {
        $runtime = app(TenantEnvelopeRuntime::class);
        $envelope = new TenantEnvelope(1, 2, 3, [3], [3], 'dispatch-test');

        $runtime->run($envelope, function () use ($envelope): void {
            $this->assertSame($envelope, app(TenantEnvelopeDispatch::class)->capture());
        });
    }
}
