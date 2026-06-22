<?php

namespace App\Models\Cobranca;

use App\Domain\Cobranca\SituacaoPix;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixCobranca extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'pix_cobrancas';

    protected $fillable = [
        'empresa_id', 'financeiroparcela_id', 'cliente_id', 'txid', 'e2eid',
        'valor', 'copia_e_cola', 'qrcode', 'expira_em', 'situacao', 'pago_em',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'expira_em' => 'datetime',
            'pago_em' => 'datetime',
            'situacao' => SituacaoPix::class,
        ];
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(FinanceiroParcela::class, 'financeiroparcela_id');
    }
}
