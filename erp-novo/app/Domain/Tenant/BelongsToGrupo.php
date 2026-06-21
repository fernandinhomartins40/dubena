<?php

namespace App\Domain\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Trait para models escopados por GRUPO (grupo_id) — cadastros de apoio
 * compartilhados entre as empresas de um mesmo grupo (segmentos, bancos, etc.).
 *
 * Análogo ao BelongsToTenant, mas filtra por grupo_id (o legado escopa esses
 * cadastros por grupo, não por empresa). O grupo_id vem do TenantContext.
 */
trait BelongsToGrupo
{
    public static function bootBelongsToGrupo(): void
    {
        $context = app(TenantContext::class);

        static::addGlobalScope(new GrupoScope($context));

        static::creating(function (Model $model) use ($context) {
            if ($context->grupoId() !== null && empty($model->getAttribute('grupo_id'))) {
                $model->setAttribute('grupo_id', $context->grupoId());
            }
        });
    }

    /** Builder sem o escopo de grupo (uso administrativo/ETL). */
    public static function withoutGrupo(): Builder
    {
        return static::query()->withoutGlobalScope(GrupoScope::class);
    }
}

/** Global scope: aplica `where grupo_id = <grupo ativo>` quando há tenant. */
class GrupoScope implements Scope
{
    public function __construct(private TenantContext $context)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        if ($this->context->grupoId() !== null) {
            $builder->where($model->getTable().'.grupo_id', $this->context->grupoId());
        }
    }
}
