<?php

namespace Tests\Unit;

use App\Domain\Tenant\TenantAccessDeniedException;
use App\Domain\Tenant\TenantEnvelope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TenantEnvelopeTest extends TestCase
{
    public function test_serializa_a_fronteira_explicita_sem_consultar_contexto_global(): void
    {
        $envelope = new TenantEnvelope(11, 21, 31, [31, 32], [31], 'correlation-1');

        $copy = TenantEnvelope::fromPayload($envelope->toPayload());

        $this->assertTrue($copy->canRead(32));
        $this->assertFalse($copy->canOperate(32));
        $this->assertSame(11, $copy->tenantAccountId);
    }

    public function test_nega_grant_operacional_ausente_e_envelope_invalido(): void
    {
        $envelope = new TenantEnvelope(11, 21, 31, [31], [31], 'correlation-1');

        $this->expectException(TenantAccessDeniedException::class);
        $envelope->requireOperation(32);
    }

    public function test_exige_empresa_ativa_com_grant_operacional(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TenantEnvelope(11, 21, 31, [31], [32], 'correlation-1');
    }
}
