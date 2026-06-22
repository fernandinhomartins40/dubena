<?php

namespace App\Models\Satelite;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Financeiro\Financeiro;
use App\Models\Pedido\Pedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ConvenioFechamento extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'convenio_fechamentos';

    protected $fillable = [
        'empresa_id', 'convenio_id', 'financeiro_id',
        'periodo_inicio', 'periodo_fim', 'valor_total', 'total_pedidos', 'situacao',
    ];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fim' => 'date',
            'valor_total' => 'decimal:2',
            'total_pedidos' => 'integer',
        ];
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    public function financeiro(): BelongsTo
    {
        return $this->belongsTo(Financeiro::class);
    }

    public function pedidos(): BelongsToMany
    {
        return $this->belongsToMany(Pedido::class, 'convenio_fechamento_pedidos')->withPivot('valor');
    }
}
