<?php

namespace App\Models\Rh;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Exceção de comissão por segmento — C5. tipo_excecao 1=percentual, 2=repasse. */
class ComissaoExcecao extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['colaborador_comissao_id' => 'colaborador_comissoes'];

    protected $table = 'comissao_excecoes';

    protected $fillable = [
        'colaborador_comissao_id', 'segmento_id', 'tipo_excecao',
        'valor_excecao', 'valor_excecao_app',
    ];

    protected function casts(): array
    {
        return [
            'tipo_excecao' => 'integer',
            'valor_excecao' => 'decimal:4',
            'valor_excecao_app' => 'decimal:4',
        ];
    }

    public function comissao(): BelongsTo
    {
        return $this->belongsTo(ColaboradorComissao::class, 'colaborador_comissao_id');
    }
}
