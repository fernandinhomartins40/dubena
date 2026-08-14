<?php

namespace App\Models\Migracao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha que a migração não conseguiu trazer, com o dado original preservado.
 *
 * É o registro que sustenta a promessa de não descartar nada em silêncio: o
 * usuário vê o que ficou de fora, por quê, e pode exportar ou reprocessar.
 */
class MigracaoDescarte extends Model
{
    protected $table = 'migracao_descartes';

    protected $fillable = [
        'migracao_id', 'migrador', 'entidade', 'motivo', 'chave_origem', 'dados',
    ];

    protected function casts(): array
    {
        return ['dados' => 'array'];
    }

    public function migracao(): BelongsTo
    {
        return $this->belongsTo(Migracao::class);
    }
}
