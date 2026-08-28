<?php

namespace App\Domain\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

/**
 * Trait para models escopados por tenant (empresa_id).
 *
 * Substitui o padrão legado `->where('empresa_id', Session::get('empresa_padrao')->id)`
 * repetido em dezenas de controllers: aqui o filtro vira um global scope automático,
 * e o empresa_id/grupo_id é preenchido na criação a partir do TenantContext.
 *
 * Uso: `class Cliente extends Model { use BelongsToTenant; }`
 * Para consultar SEM o escopo (ex.: rotinas administrativas/ETL): `Model::withoutTenant()`.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        $context = app(TenantContext::class);

        // Filtro automático por empresa_id quando há tenant resolvido.
        static::addGlobalScope(new TenantScope($context));

        // Preenche empresa_id/grupo_id na criação se ainda não vierem definidos.
        static::creating(function (Model $model) use ($context) {
            if (empty($model->getAttribute('empresa_id'))) {
                // 1ª escolha: tenant ativo (caminho normal, dentro de um request).
                if ($context->empresaId() !== null) {
                    $model->setAttribute('empresa_id', $context->empresaId());
                } else {
                    // 2ª escolha (ETL/jobs/seed/testes — SEM tenant resolvido): herda
                    // o empresa_id do PAI quando o model é criado via relação
                    // ($pai->filhos()->create([...])). Sem isso, a filha tenant-scoped
                    // nasceria com empresa_id NULL e ficaria invisível a um tenant ativo.
                    $herdado = static::empresaIdDoPai($model);
                    if ($herdado !== null) {
                        $model->setAttribute('empresa_id', $herdado);
                    }
                }
            }

            if ($context->grupoId() !== null
                && in_array('grupo_id', $model->getFillable(), true)
                && empty($model->getAttribute('grupo_id'))) {
                $model->setAttribute('grupo_id', $context->grupoId());
            }

            // A chave SaaS nunca vem do payload. Em escrita tenant-aware ela
            // vem do envelope; em ETL sem envelope, somente do pai ja ligado.
            if (in_array('tenant_account_id', $model->getFillable(), true)
                && empty($model->getAttribute('tenant_account_id'))) {
                $runtime = app(TenantEnvelopeRuntime::class);
                $tenantAccountId = $runtime->current()?->tenantAccountId
                    ?? static::tenantAccountIdDoPai($model);
                if ($tenantAccountId !== null) {
                    $model->setAttribute('tenant_account_id', $tenantAccountId);
                }
            }
        });
    }

    /** Builder sem o escopo de tenant (uso administrativo/ETL). */
    public static function withoutTenant(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Tenta descobrir o empresa_id do PAI quando a filha é criada via relação,
     * sem tenant ativo. Usa o mapa `$tenantParent` do model (FK => tabela do pai),
     * lendo o empresa_id do pai pela FK já preenchida pela relação. Uma consulta
     * leve, percorrida só no caminho ETL/jobs/seed/testes. Retorna null se não der.
     */
    protected static function empresaIdDoPai(Model $model): ?int
    {
        // Convenção opcional: protected array $tenantParent = ['fk' => 'tabela_pai'];
        $mapa = property_exists($model, 'tenantParent') ? $model->tenantParent : [];

        foreach ($mapa as $fk => $tabelaPai) {
            $valorFk = $model->getAttribute($fk);
            if ($valorFk === null) {
                continue;
            }
            $empresaId = DB::table($tabelaPai)
                ->where('id', $valorFk)
                ->value('empresa_id');
            if ($empresaId !== null) {
                return (int) $empresaId;
            }
        }

        return null;
    }

    /** Herda a chave SaaS do pai apenas no caminho sem envelope (ETL/seed). */
    protected static function tenantAccountIdDoPai(Model $model): ?int
    {
        $mapa = property_exists($model, 'tenantParent') ? $model->tenantParent : [];

        foreach ($mapa as $fk => $tabelaPai) {
            $valorFk = $model->getAttribute($fk);
            if ($valorFk === null) {
                continue;
            }
            $tenantAccountId = DB::table($tabelaPai)
                ->where('id', $valorFk)
                ->value('tenant_account_id');
            if ($tenantAccountId !== null) {
                return (int) $tenantAccountId;
            }
        }

        return null;
    }
}

/**
 * Global scope que restringe as consultas às empresas VISÍVEIS do tenant.
 *
 * Não é `empresa_id = <ativa>`, e sim `empresa_id IN (<visíveis>)` — o mesmo que
 * o ctrl-web faz em `whereIn('pedido.empresa_id', $empresas->pluck('id'))`. Numa
 * empresa só as duas formas coincidem; numa rede com filiais, a diferença é o
 * dono ver a operação inteira em vez de só a unidade em que está posicionado.
 *
 * Sem tenant resolvido (CLI/ETL), não filtra — quem chama é responsável pelo
 * escopo.
 */
class TenantScope implements Scope
{
    public function __construct(private TenantContext $context) {}

    public function apply(Builder $builder, Model $model): void
    {
        $visiveis = $this->context->empresasVisiveis();
        if ($visiveis === []) {
            return;
        }

        $coluna = $model->getTable().'.empresa_id';

        // Uma empresa: `=` em vez de `IN` — o plano do Postgres é melhor e o
        // SQL fica legível no log.
        count($visiveis) === 1
            ? $builder->where($coluna, $visiveis[0])
            : $builder->whereIn($coluna, $visiveis);
    }
}
