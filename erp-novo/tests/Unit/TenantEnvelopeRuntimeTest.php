<?php

namespace Tests\Unit;

use App\Domain\Tenant\TenantAccessDeniedException;
use App\Domain\Tenant\TenantEnvelope;
use App\Domain\Tenant\TenantEnvelopeRuntime;
use Tests\TestCase;

class TenantEnvelopeRuntimeTest extends TestCase
{
    public function test_worker_sequencial_nao_retira_contexto_do_job_anterior(): void
    {
        $runtime = new TenantEnvelopeRuntime;
        $first = new TenantEnvelope(1, 11, 101, [101], [101], 'first');
        $second = new TenantEnvelope(2, 22, 202, [202], [202], 'second');

        $this->assertSame(1, $runtime->run($first, fn () => $runtime->current()?->tenantAccountId));
        $this->assertNull($runtime->current());
        $this->assertSame(2, $runtime->run($second, fn () => $runtime->current()?->tenantAccountId));
        $this->assertNull($runtime->current());
    }

    public function test_limpa_contexto_mesmo_quando_o_job_falha(): void
    {
        $runtime = new TenantEnvelopeRuntime;
        $envelope = new TenantEnvelope(1, 11, 101, [101], [101], 'failure');

        try {
            $runtime->run($envelope, fn () => throw new TenantAccessDeniedException('falha simulada'));
        } catch (TenantAccessDeniedException) {
        }

        $this->assertNull($runtime->current());
    }
}
