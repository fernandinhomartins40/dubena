<?php

namespace App\Models\Frota;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Documento do veículo (CRLV/seguro) com vencimento (F12) — tenant via empresa_id. */
class VeiculoDocumento extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai. */
    protected $tenantParent = ['veiculo_id' => 'veiculos'];

    protected $table = 'veiculo_documentos';

    protected $fillable = ['empresa_id', 'veiculo_id', 'tipo', 'numero', 'emissao', 'vencimento', 'observacao'];

    protected function casts(): array
    {
        return ['emissao' => 'date', 'vencimento' => 'date'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
