<?php

namespace App\Domain\Tenant;

/**
 * Contexto de tenant da requisição atual.
 *
 * Substitui o `Session::get('empresa_padrao')` do legado (presente em ~126
 * controllers): em vez de estado preso à sessão web, o tenant (empresa + grupo)
 * é resolvido por requisição a partir do usuário autenticado / do token, e fica
 * disponível via injeção de dependência (singleton no container) ou facade.
 *
 * Regra de negócio do legado preservada: quase tudo é escopado por empresa_id e
 * grupo_id (grupo = "rede" de empresas; empresa = a revenda/tenant operacional).
 */
class TenantContext
{
    private ?int $empresaId = null;

    private ?int $grupoId = null;

    /** Define o tenant ativo (empresa + grupo). */
    public function set(int $empresaId, int $grupoId): void
    {
        $this->empresaId = $empresaId;
        $this->grupoId = $grupoId;
    }

    /** Limpa o tenant (ex.: logout / contexto sem tenant). */
    public function clear(): void
    {
        $this->empresaId = null;
        $this->grupoId = null;
    }

    public function hasTenant(): bool
    {
        return $this->empresaId !== null && $this->grupoId !== null;
    }

    /** id da empresa ativa, ou null se não resolvido. */
    public function empresaId(): ?int
    {
        return $this->empresaId;
    }

    /** id do grupo ativo, ou null se não resolvido. */
    public function grupoId(): ?int
    {
        return $this->grupoId;
    }

    /**
     * id da empresa ativa, exigindo que exista (uso em Services que dependem de tenant).
     *
     * @throws TenantNotResolvedException
     */
    public function requireEmpresaId(): int
    {
        if ($this->empresaId === null) {
            throw new TenantNotResolvedException('Empresa (tenant) não resolvida no contexto da requisição.');
        }

        return $this->empresaId;
    }

    /**
     * id do grupo ativo, exigindo que exista.
     *
     * @throws TenantNotResolvedException
     */
    public function requireGrupoId(): int
    {
        if ($this->grupoId === null) {
            throw new TenantNotResolvedException('Grupo (tenant) não resolvido no contexto da requisição.');
        }

        return $this->grupoId;
    }
}
