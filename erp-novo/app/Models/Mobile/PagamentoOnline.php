<?php

namespace App\Models\Mobile;

use App\Domain\Mobile\SituacaoPagamento;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Pedido\Pedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagamentoOnline extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'pagamentos_online';

    protected $fillable = [
        'empresa_id', 'pedido_id', 'cliente_id', 'gateway', 'tid', 'nsu',
        'autorizacao', 'bandeira', 'valor', 'parcelas', 'situacao', 'mensagem',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'parcelas' => 'integer',
            'situacao' => SituacaoPagamento::class,
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
