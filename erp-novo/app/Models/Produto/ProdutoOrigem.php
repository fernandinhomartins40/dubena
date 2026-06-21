<?php

namespace App\Models\Produto;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoOrigem extends Model
{
    use HasFactory;

    protected $table = 'produtoorigens';

    protected $fillable = ['produto_id', 'uf', 'ind_import', 'cuf_orig', 'p_orig'];

    protected function casts(): array
    {
        return ['ind_import' => 'integer', 'cuf_orig' => 'integer', 'p_orig' => 'decimal:4'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
