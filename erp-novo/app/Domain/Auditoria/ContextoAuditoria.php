<?php

namespace App\Domain\Auditoria;

use App\Domain\Tenant\TenantEnvelopeRuntime;
use App\Models\Saas\TenantCompany;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * F2-06 — ponto único que responde "de qual tenant e de qual requisição".
 *
 * As quatro trilhas gravavam ator e empresa, mas nenhuma gravava tenant nem
 * correlação. Com N revendas, "empresa 2" não identifica ninguém sozinho, e sem
 * um fio comum entre as linhas reconstruir "o que aconteceu naquele clique"
 * vira adivinhação por timestamp.
 *
 * O `correlation_id` já existia no `TenantEnvelope` (vem do header
 * `X-Request-Id`) — só não chegava até a auditoria. Aqui ele é reusado quando há
 * envelope, e derivado do header ou gerado quando não há: uma requisição de
 * login, por exemplo, acontece ANTES de existir tenant, e ainda assim precisa
 * ser correlacionável.
 */
class ContextoAuditoria
{
    /**
     * Memoriza por ciclo: as várias linhas de uma requisição compartilham o fio.
     *
     * A memória é indexada pela requisição, e não guardada solta, porque
     * `scoped` não garante instância nova a cada requisição: no boot o container
     * já resolve o serviço, e sob Octane a mesma instância atende requisições
     * seguidas. Um único campo aqui faria a primeira correlação vazar para todas
     * as requisições posteriores — o oposto do que esta classe existe para
     * garantir.
     *
     * @var \WeakMap<object, string>|null
     */
    private ?\WeakMap $porRequisicao = null;

    /** Fio das execuções sem requisição (console, fila, tinker). */
    private ?string $semRequisicao = null;

    public function __construct(private TenantEnvelopeRuntime $runtime) {}

    /** Tenant da requisição, ou null antes de haver envelope (login, console). */
    public function tenantAccountId(): ?int
    {
        return $this->runtime->current()?->tenantAccountId;
    }

    /**
     * Identificador da requisição.
     *
     * Ordem: envelope > header `X-Request-Id` > gerado. O envelope vem primeiro
     * porque é o mesmo valor que os jobs despachados carregam — usar outro aqui
     * romperia justamente o fio entre a ação HTTP e o trabalho assíncrono dela.
     */
    public function correlationId(): string
    {
        // O envelope vence sempre e não precisa de memória: ele já é único por
        // requisição, e é o mesmo valor que os jobs despachados carregam.
        $doEnvelope = $this->runtime->current()?->correlationId;
        if ($doEnvelope !== null && trim($doEnvelope) !== '') {
            return $doEnvelope;
        }

        $requisicao = $this->requisicaoAtual();

        if ($requisicao === null) {
            return $this->semRequisicao ??= (string) Str::uuid();
        }

        $this->porRequisicao ??= new \WeakMap;
        if (isset($this->porRequisicao[$requisicao])) {
            return $this->porRequisicao[$requisicao];
        }

        $doHeader = $requisicao->header('X-Request-Id');

        return $this->porRequisicao[$requisicao] = is_string($doHeader) && trim($doHeader) !== ''
            ? mb_substr(trim($doHeader), 0, 64)
            : (string) Str::uuid();
    }

    /** Null fora de requisição (console, fila) — ali o fio é o da execução. */
    private function requisicaoAtual(): ?\Illuminate\Http\Request
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        try {
            return app()->bound('request') ? app('request') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Campos comuns a toda linha de trilha.
     *
     * @return array{tenant_account_id:?int, correlation_id:string}
     */
    public function campos(): array
    {
        return [
            'tenant_account_id' => $this->tenantAccountId(),
            'correlation_id' => $this->correlationId(),
        ];
    }

    /**
     * Campos para a trilha de PLATAFORMA.
     *
     * O SuperAdmin opera sem tenant resolvido — é assim por desenho, senão ele
     * não conseguiria cruzar empresas. Então o tenant não pode vir do envelope:
     * é derivado da empresa ALVO, que é o que identifica de quem é o dado
     * tocado. Sem empresa alvo (ação global, como criar um plano), fica nulo.
     *
     * @return array{tenant_account_id:?int, correlation_id:string}
     */
    public function camposDePlataforma(?int $empresaId): array
    {
        return [
            'tenant_account_id' => $empresaId === null ? null : TenantCompany::query()
                ->where('empresa_id', $empresaId)
                ->where('status', TenantCompany::STATUS_APPROVED)
                ->value('tenant_account_id'),
            'correlation_id' => $this->correlationId(),
        ];
    }
}
