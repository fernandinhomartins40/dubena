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
            $empresaId = \Illuminate\Support\Facades\DB::table($tabelaPai)
                ->where('id', $valorFk)
                ->value('empresa_id');
            if ($empresaId !== null) {
                return (int) $empresaId;
            }
        }

        return null;
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
