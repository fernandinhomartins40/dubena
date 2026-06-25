<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Carta de Correção Eletrônica (CCE) de uma nota (F09) — escopada por empresa. */
class CartaCorrecao extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id). */
    protected $tenantParent = ['nota_fiscal_id' => 'notas_fiscais'];

    protected $table = 'cartas_correcao';

    protected $fillable = [
        'empresa_id', 'nota_fiscal_id', 'sequencia', 'correcao',
        'protocolo', 'registrada', 'motivo',
    ];

    protected function casts(): array
    {
        return [
            'sequencia' => 'integer',
            'registrada' => 'boolean',
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class, 'nota_fiscal_id');
    }
}
