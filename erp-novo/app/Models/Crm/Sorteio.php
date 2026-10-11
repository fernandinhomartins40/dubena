<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sorteio — C10. Escopo por grupo. */
class Sorteio extends Model
{
    use BelongsToGrupo;

    protected $table = 'sorteios';

    // `situacao`, `numero_sorteado`, `sorteado_*` e `ganhador_numero_id` NÃO são
    // preenchíveis: só o `SorteioService` decide o resultado. Pelo `$fillable`,
    // um PUT do formulário poderia "sortear" escolhendo o ganhador.
    // `tenant_account_id` fica: é por ele que `BelongsToGrupo` sabe que deve
    // carimbar a chave do envelope (o controller nunca o aceita do payload).
    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'premio', 'data_sorteio'];

    protected function casts(): array
    {
        return ['data_sorteio' => 'date', 'sorteado_em' => 'datetime'];
    }

    public function ganhador(): BelongsTo
    {
        return $this->belongsTo(SorteioNumero::class, 'ganhador_numero_id');
    }

    public function condutor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sorteado_por');
    }

    public function numeros(): HasMany
    {
        return $this->hasMany(SorteioNumero::class);
    }
}
