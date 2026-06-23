<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** NF de entrada (recebida do fornecedor) — C7c. Escopo por empresa. */
class NfRecebida extends Model
{
    use BelongsToTenant;

    protected $table = 'nf_recebidas';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'chave', 'numero', 'serie',
        'emitente_cnpj', 'emitente_nome', 'data_emissao',
        'valor_produtos', 'valor_total', 'situacao', 'movimentou_estoque',
        'financeiro_id', 'xml',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'valor_produtos' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'movimentou_estoque' => 'boolean',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NfRecebidaItem::class);
    }
}
