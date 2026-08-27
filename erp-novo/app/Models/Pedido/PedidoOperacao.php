<?php

namespace App\Models\Pedido;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoOperacao extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'pedidooperacoes';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'convenio', 'gasbolso', 'disk', 'venda_direta', 'pdv', 'ativo'];

    protected function casts(): array
    {
        return [
            'convenio' => 'boolean',
            'gasbolso' => 'boolean',
            'disk' => 'boolean',
            'venda_direta' => 'boolean',
            'pdv' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
