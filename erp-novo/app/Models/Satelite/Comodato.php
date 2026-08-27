<?php

namespace App\Models\Satelite;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comodato extends Model
{
    use Auditavel, BelongsToTenant;
    use HasFactory;

    protected $table = 'comodatos';

    /** Nós emprestamos ao cliente — o vasilhame é nosso e deve voltar. */
    public const CONCEDIDO = 'CONCEDIDO';

    /** A distribuidora emprestou para nós — o vasilhame é dela, nós devemos. */
    public const RECEBIDO = 'RECEBIDO';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'produto_id', 'setor_id', 'sentido',
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

    /**
     * O que a revenda emprestou e tem a receber de volta.
     *
     * É o escopo da vigilância e das estatísticas de patrimônio na rua: cobrar
     * giro de quem nos empresta não faz sentido, e contar o casco da
     * distribuidora como "em poder de clientes" inverte o sinal da conta.
     */
    public function scopeConcedidos($q)
    {
        return $q->where('sentido', self::CONCEDIDO);
    }

    /** O que a revenda deve devolver à distribuidora. */
    public function scopeRecebidos($q)
    {
        return $q->where('sentido', self::RECEBIDO);
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
