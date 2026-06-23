<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Meta de venda por colaborador/competência — C10. Escopo por empresa. */
class MetaVenda extends Model
{
    use BelongsToTenant;

    protected $table = 'meta_vendas';

    protected $fillable = ['empresa_id', 'colaborador_id', 'competencia', 'meta_valor', 'realizado_valor'];

    protected function casts(): array
    {
        return ['meta_valor' => 'decimal:2', 'realizado_valor' => 'decimal:2'];
    }
}
