<?php

namespace App\Models\Apoio;

/**
 * Tipo de documento de VEÍCULO — CRLV, seguro, ANTT (T4.5).
 *
 * Não confundir com o tipo da gestão documental (`Documentotipo` no legado):
 * são módulos diferentes, com controllers diferentes.
 */
class TipoDocumentoVeiculo extends CadastroApoio
{
    protected $table = 'tipos_documento_veiculo';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo', 'exige_validade'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'exige_validade' => 'boolean'];
    }
}
