<?php

namespace App\Models\Geografico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Município do catálogo oficial do IBGE (5.570 + Distrito Federal).
 *
 * Catálogo NACIONAL: sem grupo_id/empresa_id e fora da RLS, como `estados`.
 * Não confundir com `Cidade` (N2), que é o cadastro do GRUPO usado no endereço
 * do cliente — esta tabela é a referência que dá o código correto para a NF-e.
 *
 * A PK é o próprio `cod_ibge`: chave natural, estável e nacional.
 */
class MunicipioIbge extends Model
{
    protected $table = 'municipios_ibge';

    protected $primaryKey = 'cod_ibge';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['cod_ibge', 'nome', 'uf', 'nome_busca', 'cod_uf'];

    protected function casts(): array
    {
        return ['cod_ibge' => 'integer', 'cod_uf' => 'integer'];
    }

    /** Cidades de grupos que apontam para este município. */
    public function cidades(): HasMany
    {
        return $this->hasMany(Cidade::class, 'municipio_ibge', 'cod_ibge');
    }
}
