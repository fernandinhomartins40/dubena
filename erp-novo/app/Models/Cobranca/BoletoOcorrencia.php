<?php

namespace App\Models\Cobranca;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoletoOcorrencia extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['boleto_id' => 'boletos'];

    use HasFactory;

    protected $table = 'boleto_ocorrencias';

    protected $fillable = ['boleto_id', 'codigo', 'descricao', 'valor', 'data_ocorrencia'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2', 'data_ocorrencia' => 'date'];
    }

    public function boleto(): BelongsTo
    {
        return $this->belongsTo(Boleto::class);
    }
}
