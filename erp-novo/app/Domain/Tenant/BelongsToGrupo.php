<?php

namespace App\Domain\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $runtime = app(TenantEnvelopeRuntime::class);

        static::addGlobalScope(new GrupoScope($context));

        static::creating(function (Model $model) use ($context, $runtime) {
            if ($context->grupoId() !== null && empty($model->getAttribute('grupo_id'))) {
                $model->setAttribute('grupo_id', $context->grupoId());
            }

            // Modelos cuja TABELA tem a chave SaaS participam da ponte. O valor
            // vem do envelope em execucao, nunca de input do request ou do
            // grupo legado.
            //
            // PIE P2-D-07: olhar só o `$fillable` deixava de fora quem não o
            // declarou (ConfigGlobal): a linha nascia sem chave, a policy a
            // recusava e `GET /config-global` dava 500 em toda revenda nova —
            // levando junto o rastreamento e a chave do Maps. Mesma detecção
            // de `BelongsToTenant::temChaveSaas`, que já tinha sido corrigido.
            if (static::grupoTemChaveSaas($model)
                && empty($model->getAttribute('tenant_account_id'))
                && $runtime->current() !== null) {
                $model->setAttribute('tenant_account_id', $runtime->current()->tenantAccountId);
            }
        });
    }

    protected static function grupoTemChaveSaas(Model $model): bool
    {
        static $cache = [];
        $chave = ($model->getConnectionName() ?? DB::getDefaultConnection()).'|'.$model->getTable();

        return $cache[$chave] ??= in_array('tenant_account_id', $model->getFillable(), true)
            || Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), 'tenant_account_id');
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
    public function __construct(private TenantContext $context) {}

    public function apply(Builder $builder, Model $model): void
    {
        if ($this->context->grupoId() !== null) {
            $builder->where($model->getTable().'.grupo_id', $this->context->grupoId());
        }
    }
}
