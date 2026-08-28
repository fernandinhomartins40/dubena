<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Número de sorteio atribuído a um cliente — C10. Leaf puro: acessado via o
 * Sorteio pai (grupo-scoped). Sem coluna de tenant própria; isolamento pelo pai.
 */
class SorteioNumero extends Model
{
    protected $table = 'sorteio_numeros';

    protected $fillable = ['sorteio_id', 'cliente_id', 'numero'];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->tenant_account_id === null && $model->sorteio_id !== null) {
                $model->tenant_account_id = DB::table('sorteios')->whereKey($model->sorteio_id)->value('tenant_account_id');
            }
        });
    }

    public function sorteio(): BelongsTo
    {
        return $this->belongsTo(Sorteio::class);
    }
}
