<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientePreco extends Model
{
    use HasFactory;

    protected $table = 'clienteprecos';

    protected $fillable = ['cliente_id', 'produto_id', 'preco', 'desconto'];

    protected function casts(): array
    {
        return ['preco' => 'decimal:2', 'desconto' => 'decimal:2'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
