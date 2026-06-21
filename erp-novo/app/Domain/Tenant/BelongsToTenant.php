<?php

namespace App\Domain\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

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
            if ($context->empresaId() !== null && empty($model->getAttribute('empresa_id'))) {
                $model->setAttribute('empresa_id', $context->empresaId());
            }
            if ($context->grupoId() !== null
                && in_array('grupo_id', $model->getFillable(), true)
                && empty($model->getAttribute('grupo_id'))) {
                $model->setAttribute('grupo_id', $context->grupoId());
            }
        });
    }

    /** Builder sem o escopo de tenant (uso administrativo/ETL). */
    public static function withoutTenant(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}

/**
 * Global scope que aplica `where empresa_id = <tenant ativo>` quando há tenant.
 * Sem tenant resolvido (ex.: CLI/ETL), não filtra — a responsabilidade de
 * escopar fica de quem chama.
 */
class TenantScope implements Scope
{
    public function __construct(private TenantContext $context)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        if ($this->context->empresaId() !== null) {
            $builder->where($model->getTable().'.empresa_id', $this->context->empresaId());
        }
    }
}
