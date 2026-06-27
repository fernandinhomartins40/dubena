<?php

namespace App\Models\Organizacao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Departamento — agrupamento funcional dentro de uma unidade (A3). Escopo por
 * empresa. Herda empresa_id do pai (unidade) via $tenantParent.
 */
class Departamento extends Model
{
    use BelongsToTenant;

    protected $table = 'departamentos';

    protected $fillable = ['empresa_id', 'unidade_id', 'nome', 'ativo'];

    /** @var array<string, string> herança de empresa_id em ETL/seed/testes sem tenant. */
    protected array $tenantParent = ['unidade_id' => 'unidades'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function setores(): HasMany
    {
        return $this->hasMany(SetorOrg::class);
    }
}
