<?php

namespace App\Models\Cobranca;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemessaCnab extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'remessas_cnab';

    protected $fillable = [
        'empresa_id', 'banco_codigo', 'numero_remessa', 'arquivo',
        'total_boletos', 'valor_total', 'situacao',
    ];

    protected function casts(): array
    {
        return [
            'banco_codigo' => 'integer',
            'numero_remessa' => 'integer',
            'total_boletos' => 'integer',
            'valor_total' => 'decimal:2',
        ];
    }
}
