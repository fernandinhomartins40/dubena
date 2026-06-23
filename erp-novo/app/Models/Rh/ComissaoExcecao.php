<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Exceção de comissão por segmento — C5. tipo_excecao 1=percentual, 2=repasse. */
class ComissaoExcecao extends Model
{
    protected $table = 'comissao_excecoes';

    protected $fillable = [
        'colaborador_comissao_id', 'segmento_id', 'tipo_excecao',
        'valor_excecao', 'valor_excecao_app',
    ];

    protected function casts(): array
    {
        return [
            'tipo_excecao' => 'integer',
            'valor_excecao' => 'decimal:4',
            'valor_excecao_app' => 'decimal:4',
        ];
    }

    public function comissao(): BelongsTo
    {
        return $this->belongsTo(ColaboradorComissao::class, 'colaborador_comissao_id');
    }
}
