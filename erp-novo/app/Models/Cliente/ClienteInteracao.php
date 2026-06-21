<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteInteracao extends Model
{
    use HasFactory;

    protected $table = 'clienteinteracoes';

    protected $fillable = ['cliente_id', 'user_id', 'tipo_id', 'situacao_id', 'descricao', 'acao'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
