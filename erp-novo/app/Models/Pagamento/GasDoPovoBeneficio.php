<?php

namespace App\Models\Pagamento;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Benefício "Gás do Povo" (auxílio governamental) — C4. Escopo por empresa. */
class GasDoPovoBeneficio extends Model
{
    use BelongsToTenant;

    protected $table = 'gasdopovo_beneficios';

    protected $fillable = ['empresa_id', 'cliente_id', 'nis', 'competencia', 'valor', 'situacao', 'pedido_id'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2'];
    }
}
