<?php

namespace App\Models\Organizacao;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unidade (filial) — nó da árvore organizacional sob a empresa (A3). Pode ter
 * pai (parent_id) para formar a hierarquia de filiais. Escopo por empresa.
 */
class Unidade extends Model
{
    use BelongsToTenant;

    protected $table = 'unidades';

    protected $fillable = ['empresa_id', 'parent_id', 'tipo', 'nome', 'cnpj', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function filhas(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function departamentos(): HasMany
    {
        return $this->hasMany(Departamento::class);
    }
}
