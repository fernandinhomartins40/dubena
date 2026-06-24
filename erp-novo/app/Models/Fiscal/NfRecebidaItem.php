<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item de NF de entrada — C7c. */
class NfRecebidaItem extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['nf_recebida_id' => 'nf_recebidas'];

    protected $table = 'nf_recebida_itens';

    protected $fillable = [
        'nf_recebida_id', 'produto_id', 'codigo_fornecedor', 'descricao',
        'ncm', 'cfop', 'quantidade', 'valor_unitario', 'valor_total',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:4',
            'valor_unitario' => 'decimal:6',
            'valor_total' => 'decimal:2',
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NfRecebida::class, 'nf_recebida_id');
    }
}
