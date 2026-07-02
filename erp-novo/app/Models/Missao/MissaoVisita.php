<?php

namespace App\Models\Missao;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use App\Models\Pedido\Pedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Residência visitada durante uma missão (L7). Status ∈ visitada/ausente/
 * interessado/venda/frustrada; quando vira VENDA, aponta o pedido gerado (L8).
 * Tenant-scoped.
 */
class MissaoVisita extends Model
{
    use BelongsToTenant;

    protected $table = 'missao_visitas';

    public const STATUS = ['visitada', 'ausente', 'interessado', 'venda', 'frustrada'];

    protected $fillable = [
        'empresa_id', 'missao_atribuicao_id', 'latitude', 'longitude', 'status',
        'cliente_id', 'pedido_id', 'iniciada_em', 'finalizada_em', 'duracao_seg', 'observacao',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'iniciada_em' => 'datetime',
            'finalizada_em' => 'datetime',
            'duracao_seg' => 'integer',
        ];
    }

    public function atribuicao(): BelongsTo
    {
        return $this->belongsTo(MissaoAtribuicao::class, 'missao_atribuicao_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(MissaoEvidencia::class, 'missao_visita_id');
    }
}
