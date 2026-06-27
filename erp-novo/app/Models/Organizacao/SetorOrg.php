<?php

namespace App\Models\Organizacao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Setor/Equipe — time de execução dentro de um departamento (A3). Base para o
 * escopo "ver só minha equipe". Tabela `setores_org` (não colide com `setores`
 * do estoque). Escopo por empresa.
 */
class SetorOrg extends Model
{
    use BelongsToTenant;

    protected $table = 'setores_org';

    protected $fillable = ['empresa_id', 'departamento_id', 'nome', 'ativo'];

    /** @var array<string, string> herança de empresa_id em ETL/seed/testes sem tenant. */
    protected array $tenantParent = ['departamento_id' => 'departamentos'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }
}
