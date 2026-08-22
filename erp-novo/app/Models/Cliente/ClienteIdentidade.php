<?php

namespace App\Models\Cliente;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um traço de identidade de um cliente (telefone, CPF, nome fonético, endereço).
 *
 * Uma linha por traço: o cliente tem vários telefones, e a busca por traço vira
 * um índice simples em vez de OR sobre N colunas.
 */
class ClienteIdentidade extends Model
{
    use BelongsToTenant;

    protected $table = 'cliente_identidades';

    protected $fillable = [
        'empresa_id', 'cliente_id', 'tipo', 'valor', 'origem', 'verificado',
    ];

    protected function casts(): array
    {
        return ['verificado' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
