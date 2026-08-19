<?php

namespace App\Models\Cliente;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Apoio\ClienteContatoSituacao;
use App\Models\Apoio\ClienteContatoTipo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteInteracao extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['cliente_id' => 'clientes'];

    use HasFactory;

    protected $table = 'clienteinteracoes';

    protected $fillable = ['cliente_id', 'user_id', 'tipo_id', 'situacao_id', 'descricao', 'acao'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(ClienteContatoTipo::class, 'tipo_id');
    }

    public function situacao(): BelongsTo
    {
        return $this->belongsTo(ClienteContatoSituacao::class, 'situacao_id');
    }
}
