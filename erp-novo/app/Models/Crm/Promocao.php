<?php

namespace App\Models\Crm;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/** Promoção / campanha — C10. Escopo por grupo. */
class Promocao extends Model
{
    use BelongsToGrupo;

    protected $table = 'promocoes';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'codigo', 'inicio', 'fim', 'desconto_percentual', 'ativo'];

    /** Promoção válida hoje = ativa e dentro da janela inicio..fim. */
    public function scopeVigente($query)
    {
        $hoje = now()->toDateString();

        return $query->where('ativo', true)
            ->whereDate('inicio', '<=', $hoje)
            ->whereDate('fim', '>=', $hoje);
    }

    protected function casts(): array
    {
        return ['inicio' => 'date', 'fim' => 'date', 'desconto_percentual' => 'decimal:2', 'ativo' => 'boolean'];
    }
}
