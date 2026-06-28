<?php

namespace App\Models\Cliente;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Endereço de entrega do cliente (1:N) — F3b. Paridade com o legado: o app permite
 * múltiplos endereços com um favorito. Escopo por empresa (BelongsToTenant).
 */
class ClienteEndereco extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'cliente_enderecos';

    /** @var array<string,string> herança de empresa_id ao criar via relação/sem tenant. */
    protected $tenantParent = ['cliente_id' => 'clientes'];

    protected $fillable = [
        'empresa_id', 'cliente_id', 'titulo', 'endereco', 'numero', 'complemento',
        'ponto_referencia', 'bairro', 'cidade', 'cep', 'uf', 'latitude', 'longitude', 'favorito',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'favorito' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
