<?php

namespace App\Models\Cliente;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de uma consolidação: o cliente `cliente_id` foi absorvido por
 * `principal_id`.
 *
 * O absorvido NÃO é apagado — fica desativado e apontando para o vencedor, de
 * modo que um id antigo (num link, num relatório impresso, num pedido do
 * legado) ainda resolva para o cadastro certo.
 */
class ClienteVinculo extends Model
{
    use BelongsToTenant;

    protected $table = 'cliente_vinculos';

    protected $fillable = [
        'empresa_id', 'cliente_id', 'principal_id', 'escore', 'tracos',
        'decidido_por', 'user_id',
    ];

    protected function casts(): array
    {
        return ['tracos' => 'array', 'escore' => 'integer'];
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'principal_id');
    }

    public function absorvido(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
