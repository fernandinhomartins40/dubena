<?php

namespace App\Domain\Saas;

use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Monitora\Veiculo;
use App\Models\Saas\TenantCompany;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * F2-03 — porta única que recusa a criação quando o teto contratado acabou.
 *
 * `LicencaService` responde "qual é o teto"; aqui se responde "quanto já existe"
 * e se aplica a recusa. Separado de propósito: a contagem é a parte que muda por
 * limite e que precisa dizer, explicitamente, O QUE está sendo contado — deixar
 * isso implícito em cada controller é como o mesmo limite passa a significar
 * coisas diferentes em lugares diferentes.
 *
 * Responde 402 (Payment Required), a mesma semântica do módulo não contratado:
 * não é falta de permissão (403), é falta de contrato.
 *
 * Governado por `SAAS_ENFORCE_LICENCA`, como o resto da licença — desligado, não
 * recusa nada.
 */
class LimiteContratado
{
    public function __construct(private LicencaService $licenca) {}

    /**
     * Recusa se criar mais um estourar o teto.
     *
     * @param  int|null  $empresaId  empresa cujo plano decide (default: ativa)
     *
     * @throws HttpException 402 quando o teto foi atingido
     */
    public function exigirEspaco(string $limiteChave, ?int $empresaId = null, int $aCriar = 1): void
    {
        if (! config('saas_transformation.enforcement.licenca')) {
            return;
        }

        $uso = $this->usoAtual($limiteChave, $empresaId);
        if ($this->licenca->dentroDoLimite($limiteChave, $uso, $empresaId, $aCriar)) {
            return;
        }

        $teto = $this->licenca->limite($limiteChave, $empresaId);
        $rotulo = RecursoCatalogo::LIMITES[$limiteChave] ?? $limiteChave;

        throw new HttpException(402, sprintf(
            'Limite do plano atingido — %s: %d de %s em uso.',
            $rotulo,
            $uso,
            $teto === null ? 'ilimitado' : (string) $teto,
        ));
    }

    /**
     * Quanto já existe para o limite.
     *
     * Cada contagem declara seu recorte, porque a ambiguidade é onde o limite
     * deixa de significar a mesma coisa em lugares diferentes:
     *
     *  - `empresas`: unidades ATIVAS do mesmo TENANT (não do grupo legado) —
     *    o contrato é com o tenant, e é ele que paga;
     *  - `usuarios`: usuários ATIVOS da empresa. Inativo não ocupa vaga, senão
     *    desligar alguém não liberaria espaço;
     *  - `veiculos_monitorados`: veículos com rastreamento na empresa.
     */
    public function usoAtual(string $limiteChave, ?int $empresaId = null): int
    {
        $empresaId ??= app(TenantContext::class)->empresaId();
        if ($empresaId === null) {
            return 0;
        }

        return match ($limiteChave) {
            'empresas' => $this->empresasDoTenant($empresaId),
            'usuarios' => User::query()
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->count(),
            'veiculos_monitorados' => Veiculo::withoutTenant()
                ->where('empresa_id', $empresaId)
                ->count(),
            // Limite desconhecido não inventa contagem: `dentroDoLimite` decide
            // com uso zero, e o teto do plano continua valendo.
            default => 0,
        };
    }

    /** Unidades ativas do tenant dono desta empresa (ou do grupo, se não houver tenant). */
    private function empresasDoTenant(int $empresaId): int
    {
        $company = TenantCompany::query()
            ->where('empresa_id', $empresaId)
            ->where('status', TenantCompany::STATUS_APPROVED)
            ->first();

        if ($company === null) {
            // Fora da fronteira SaaS: cai no grupo legado, que é o recorte que
            // existia antes do tenant e continua sendo o mais próximo da rede.
            $grupoId = Empresa::withoutGlobalScopes()->whereKey($empresaId)->value('grupo_id');

            return Empresa::withoutGlobalScopes()
                ->where('grupo_id', $grupoId)
                ->where('ativo', true)
                ->count();
        }

        // Colunas qualificadas: `tenant_account_id` e `status` existem nas duas
        // tabelas do JOIN, e sem o prefixo o SQL fica ambíguo.
        return TenantCompany::query()
            ->join('empresas', 'empresas.id', '=', 'tenant_companies.empresa_id')
            ->where('tenant_companies.tenant_account_id', $company->tenant_account_id)
            ->where('tenant_companies.status', TenantCompany::STATUS_APPROVED)
            ->where('empresas.ativo', true)
            ->count();
    }
}
