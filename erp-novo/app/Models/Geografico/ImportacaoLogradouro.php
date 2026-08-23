<?php

namespace App\Models\Geografico;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de que uma cidade teve os logradouros importados da base de CEP.
 *
 * Existe para responder "esta cidade já foi importada?" — a varredura custa
 * centenas de requisições e, sem esse registro, reimportar seria o default
 * acidental. `termos_truncados` guarda a medida honesta de incompletude:
 * quantas buscas bateram o teto da fonte mesmo depois do refino.
 */
class ImportacaoLogradouro extends Model
{
    use BelongsToGrupo;

    protected $table = 'importacoes_logradouro';

    protected $fillable = [
        'grupo_id', 'cidade_id', 'fonte', 'ruas_criadas', 'bairros_criados',
        'ruas_atualizadas', 'consultas', 'termos_truncados', 'situacao',
        'erro', 'executado_por',
    ];

    protected function casts(): array
    {
        return [
            'ruas_criadas' => 'integer',
            'bairros_criados' => 'integer',
            'ruas_atualizadas' => 'integer',
            'consultas' => 'integer',
            'termos_truncados' => 'integer',
        ];
    }

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class);
    }
}
