<?php

namespace App\Models\Satelite;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Convenio extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'convenios';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'descricao',
        'dia_fechamento', 'dia_vencimento', 'ativo',
    ];

    protected function casts(): array
    {
        return ['dia_fechamento' => 'integer', 'dia_vencimento' => 'integer', 'ativo' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function fechamentos(): HasMany
    {
        return $this->hasMany(ConvenioFechamento::class);
    }
}
