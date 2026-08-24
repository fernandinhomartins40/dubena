<?php

namespace App\Models\Geografico;

use Illuminate\Database\Eloquent\Model;

/**
 * Controle do que já foi importado do CNEFE, por município.
 *
 * Catálogo público (sem tenant), com o código IBGE como PK — chave natural, a
 * mesma que nomeia o arquivo no FTP do IBGE.
 */
class ImportacaoCnefe extends Model
{
    protected $table = 'importacoes_cnefe';

    protected $primaryKey = 'cod_ibge';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'cod_ibge', 'municipio', 'uf', 'logradouros', 'bairros', 'enderecos', 'versao',
    ];

    protected function casts(): array
    {
        return [
            'cod_ibge' => 'integer',
            'logradouros' => 'integer',
            'bairros' => 'integer',
            'enderecos' => 'integer',
        ];
    }
}
