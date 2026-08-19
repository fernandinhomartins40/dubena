<?php

namespace App\Models\Satelite;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comodato extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'comodatos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'produto_id', 'setor_id',
        'quantidade', 'quantidade_devolvida', 'situacao', 'data_emprestimo', 'data_devolucao',
        // Quem assinou o contrato — o ComodatoPdfService imprime, e contrato
        // sem signatário não vale como documento.
        'nome_representante', 'cpf_representante', 'rg_representante', 'data_vencimento',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'quantidade_devolvida' => 'decimal:3',
            'data_emprestimo' => 'date',
            'data_devolucao' => 'date',
            'data_vencimento' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
