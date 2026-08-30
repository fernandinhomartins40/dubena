<?php

namespace App\Domain\Saas;

use App\Models\Empresa;
use App\Models\Saas\Assinatura;
use App\Models\Saas\AssinaturaEvento;
use App\Models\Saas\Plano;
use App\Models\Saas\RecursoOverride;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SuperAdminService (P4) — o ÚNICO ponto autorizado a cruzar tenants.
 *
 * O guard 'platform' NÃO seta `app.empresa_id` nem resolve tenant: a RLS do
 * Postgres não restringe (sem variável = não filtra) e o global scope de tenant
 * fica inativo — o SuperAdmin enxerga todas as empresas. Models tenant-scoped
 * (Assinatura/RecursoOverride/AssinaturaEvento) são lidos com withoutTenant() por
 * garantia; `Empresa` é a raiz do tenant (não tem o trait) e já é cross-tenant.
 * Em troca, TODA mutação é AUDITADA (AuditoriaPlataforma) e invalida o cache de
 * licença do tenant afetado. Nenhuma operação cross-tenant existe fora deste service.
 */
class SuperAdminService
{
    public function __construct(
        private AuditoriaPlataforma $auditoria,
        private LicencaService $licenca,
    ) {}

    // ───────────────────────── Empresas (cross-tenant) ─────────────────────────

    /**
     * Lista todas as empresas da plataforma com a assinatura/plano corrente.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function empresas(?string $busca = null): Collection
    {
        return Empresa::query()
            ->with(['grupo:id,descricao'])
            ->when($busca, fn ($q, $b) => $q->where(fn ($w) => $w
                ->where('razao_social', 'ilike', '%'.$b.'%')
                ->orWhere('nome_fantasia', 'ilike', '%'.$b.'%')
                ->orWhere('cnpj', 'ilike', '%'.$b.'%')))
            ->orderBy('razao_social')
            ->get()
            ->map(function (Empresa $e) {
                $assinatura = $this->assinaturaCorrente($e->id);

                return [
                    'id' => $e->id,
                    'razao_social' => $e->razao_social,
                    'nome_fantasia' => $e->nome_fantasia,
                    'cnpj' => $e->cnpj,
                    'grupo' => $e->grupo?->descricao,
                    'ativo' => (bool) $e->ativo,
                    'plano' => $assinatura?->plano?->slug,
                    'status_assinatura' => $assinatura?->status,
                ];
            });
    }

    /** Suspende (desativa) uma empresa — bloqueia login/uso de todos os seus usuários. */
    public function suspenderEmpresa(int $empresaId): Empresa
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $antes = ['ativo' => (bool) $empresa->ativo];
        $empresa->forceFill(['ativo' => false])->save();

        $this->auditoria->registrar('empresa.suspensa', $empresaId, 'empresas', $empresaId, $antes, ['ativo' => false]);

        return $empresa->refresh();
    }

    /** Reativa uma empresa suspensa. */
    public function reativarEmpresa(int $empresaId): Empresa
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $antes = ['ativo' => (bool) $empresa->ativo];
        $empresa->forceFill(['ativo' => true])->save();

        $this->auditoria->registrar('empresa.reativada', $empresaId, 'empresas', $empresaId, $antes, ['ativo' => true]);

        return $empresa->refresh();
    }

    // ───────────────────────── Assinaturas (cross-tenant) ─────────────────────────

    /**
     * Define o plano/assinatura de uma empresa (cria ou atualiza a corrente).
     *
     * @param  array<string,mixed>  $dados  status/inicio/fim/trial_ate
     */
    public function definirAssinatura(int $empresaId, int $planoId, array $dados = []): Assinatura
    {
        return DB::transaction(function () use ($empresaId, $planoId, $dados) {
            $empresa = Empresa::query()->findOrFail($empresaId);
            $plano = Plano::query()->findOrFail($planoId);

            $assinatura = $this->assinaturaCorrente($empresaId);
            $antes = $assinatura ? ['plano_id' => $assinatura->plano_id, 'status' => $assinatura->status] : null;

            $payload = array_merge([
                'empresa_id' => $empresa->id,
                'plano_id' => $plano->id,
                'status' => $dados['status'] ?? Assinatura::STATUS_ATIVA,
                'inicio' => $dados['inicio'] ?? now()->toDateString(),
                'fim' => $dados['fim'] ?? null,
                'trial_ate' => $dados['trial_ate'] ?? null,
            ], []);

            if ($assinatura) {
                $assinatura->forceFill($payload)->save();
            } else {
                // Cria sem depender do tenant ativo (SuperAdmin não tem tenant).
                $assinatura = Assinatura::withoutTenant()->create($payload);
            }

            AssinaturaEvento::withoutTenant()->create([
                'empresa_id' => $empresa->id,
                'assinatura_id' => $assinatura->id,
                'tipo' => $antes ? 'plano.alterado' : 'criada',
                'detalhes' => ['antes' => $antes, 'depois' => $payload],
            ]);

            $this->auditoria->registrar('assinatura.definida', $empresaId, 'assinaturas', $assinatura->id, $antes, $payload);
            $this->licenca->invalidar($empresaId);

            return $assinatura->refresh();
        });
    }

    /** Altera o status de uma assinatura (ativa/inadimplente/cancelada). */
    public function alterarStatusAssinatura(int $empresaId, string $status): ?Assinatura
    {
        $assinatura = $this->assinaturaCorrente($empresaId);
        if (! $assinatura) {
            return null;
        }

        $antes = ['status' => $assinatura->status];
        $assinatura->forceFill(['status' => $status])->save();

        AssinaturaEvento::withoutTenant()->create([
            'empresa_id' => $empresaId,
            'assinatura_id' => $assinatura->id,
            'tipo' => 'status.alterado',
            'detalhes' => ['de' => $antes['status'], 'para' => $status],
        ]);

        $this->auditoria->registrar('assinatura.status', $empresaId, 'assinaturas', $assinatura->id, $antes, ['status' => $status]);
        $this->licenca->invalidar($empresaId);

        return $assinatura->refresh();
    }

    // ───────────────────────── Overrides de recurso (cross-tenant) ─────────────────────────

    /**
     * Liga/desliga um recurso por empresa (cortesia/piloto/bloqueio).
     *
     * F2-03: `motivo` é obrigatório e `expiraEm` opcional. Override sem prazo
     * vira permanente por esquecimento — é assim que um piloto de 30 dias custa
     * dois anos. Sem prazo continua possível, mas passa a ser uma escolha
     * declarada, e o motivo diz a quem revisar por que ela foi feita.
     */
    public function definirOverride(
        int $empresaId,
        string $recursoChave,
        bool $habilitado,
        string $motivo,
        ?\DateTimeInterface $expiraEm = null,
    ): RecursoOverride {
        abort_unless(RecursoCatalogo::existe($recursoChave), 422, 'Recurso desconhecido.');
        abort_if(trim($motivo) === '', 422, 'Motivo é obrigatório para sobrepor o plano.');

        $override = RecursoOverride::withoutTenant()->updateOrCreate(
            ['empresa_id' => $empresaId, 'recurso_chave' => $recursoChave],
            ['habilitado' => $habilitado, 'motivo' => $motivo, 'expira_em' => $expiraEm],
        );

        $this->auditoria->registrar('recurso.override', $empresaId, 'recurso_overrides', $override->id, null, [
            'recurso' => $recursoChave,
            'habilitado' => $habilitado,
            'motivo' => $motivo,
            'expira_em' => $expiraEm?->format(DATE_ATOM),
        ]);
        $this->licenca->invalidar($empresaId);

        return $override;
    }

    /**
     * Define o teto numérico de um limite por empresa (F2-03).
     *
     * `$valor` nulo = ilimitado para aquela empresa. Mesmo rito do override de
     * recurso: motivo obrigatório e prazo opcional.
     */
    public function definirLimiteOverride(
        int $empresaId,
        string $limiteChave,
        ?int $valor,
        string $motivo,
        ?\DateTimeInterface $expiraEm = null,
    ): LimiteOverride {
        abort_unless(RecursoCatalogo::limiteExiste($limiteChave), 422, 'Limite desconhecido.');
        abort_if(trim($motivo) === '', 422, 'Motivo é obrigatório para sobrepor o limite do plano.');

        $override = LimiteOverride::withoutTenant()->updateOrCreate(
            ['empresa_id' => $empresaId, 'limite_chave' => $limiteChave],
            ['valor' => $valor, 'motivo' => $motivo, 'expira_em' => $expiraEm],
        );

        $this->auditoria->registrar('limite.override', $empresaId, 'limite_overrides', $override->id, null, [
            'limite' => $limiteChave,
            'valor' => $valor,
            'motivo' => $motivo,
            'expira_em' => $expiraEm?->format(DATE_ATOM),
        ]);
        $this->licenca->invalidar($empresaId);

        return $override;
    }

    /** Remove um override de limite (volta a valer o teto do plano). */
    public function removerLimiteOverride(int $empresaId, string $limiteChave): void
    {
        LimiteOverride::withoutTenant()
            ->where('empresa_id', $empresaId)->where('limite_chave', $limiteChave)->delete();

        $this->auditoria->registrar('limite.override.removido', $empresaId, 'limite_overrides', null, ['limite' => $limiteChave], null);
        $this->licenca->invalidar($empresaId);
    }

    /** Remove um override (volta a valer o plano). */
    public function removerOverride(int $empresaId, string $recursoChave): void
    {
        RecursoOverride::withoutTenant()
            ->where('empresa_id', $empresaId)->where('recurso_chave', $recursoChave)->delete();

        $this->auditoria->registrar('recurso.override.removido', $empresaId, 'recurso_overrides', null, ['recurso' => $recursoChave], null);
        $this->licenca->invalidar($empresaId);
    }

    // ───────────────────────── Planos (global) ─────────────────────────

    /**
     * Cria/atualiza um plano e seus recursos.
     *
     * @param  array<string,mixed>  $dados
     * @param  list<string>  $recursos
     */
    public function salvarPlano(array $dados, array $recursos, ?int $id = null): Plano
    {
        return DB::transaction(function () use ($dados, $recursos, $id) {
            $plano = $id
                ? tap(Plano::query()->findOrFail($id))->update($dados)
                : Plano::query()->create($dados);

            $validos = array_values(array_filter($recursos, fn ($c) => RecursoCatalogo::existe($c)));
            $plano->recursos()->whereNotIn('recurso_chave', $validos ?: ['__none__'])->delete();
            foreach ($validos as $chave) {
                $plano->recursos()->updateOrCreate(['recurso_chave' => $chave], []);
            }

            $this->auditoria->registrar($id ? 'plano.editado' : 'plano.criado', null, 'planos', $plano->id, null, [
                'slug' => $plano->slug, 'recursos' => $validos,
            ]);

            return $plano->refresh()->load('recursos');
        });
    }

    // ───────────────────────── Dashboard (read-only) ─────────────────────────

    /** @return array<string,mixed> visão agregada da plataforma. */
    public function dashboard(): array
    {
        return [
            'empresas_total' => Empresa::query()->count(),
            'empresas_ativas' => Empresa::query()->where('ativo', true)->count(),
            'assinaturas_ativas' => Assinatura::withoutTenant()->whereIn('status', [Assinatura::STATUS_TRIAL, Assinatura::STATUS_ATIVA])->count(),
            'assinaturas_inadimplentes' => Assinatura::withoutTenant()->where('status', Assinatura::STATUS_INADIMPLENTE)->count(),
            'planos' => Plano::query()->where('ativo', true)->count(),
            'por_plano' => Assinatura::withoutTenant()
                ->whereIn('status', [Assinatura::STATUS_TRIAL, Assinatura::STATUS_ATIVA])
                ->selectRaw('plano_id, count(*) as total')
                ->groupBy('plano_id')->pluck('total', 'plano_id'),
        ];
    }

    /** Assinatura corrente (trial/ativa) de uma empresa, sem escopo de tenant. */
    private function assinaturaCorrente(int $empresaId): ?Assinatura
    {
        return Assinatura::withoutTenant()
            ->with('plano')
            ->where('empresa_id', $empresaId)
            ->whereIn('status', [Assinatura::STATUS_TRIAL, Assinatura::STATUS_ATIVA])
            ->orderByDesc('id')
            ->first();
    }
}
