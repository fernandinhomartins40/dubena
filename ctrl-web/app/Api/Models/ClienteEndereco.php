<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\ClienteEndereco
 *
 * @property-read \App\ClienteImportacao $cliente
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int $numero
 * @property string $complemento
 * @property string $descricao
 * @property string $cep
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $cliente_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereCep($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereComplemento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereDescricao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereNumero($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereUpdatedAt($value)
 * @property string $rua
 * @property string $titulo
 * @property string $bairro
 * @property string $uf
 * @property string $cidade
 * @property string|null $pontoreferencia
 * @property int $ativo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereBairro($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereCidade($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco wherePontoreferencia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereRua($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereTitulo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteEndereco whereUf($value)
 */
class ClienteEndereco extends ApiModel
{
    protected $table = 'clienteenderecos';

    protected $fillable = [
        'numero', 'complemento', 'rua', 'titulo', 'cep', 'latitude', 'longitude',
        'cliente_id', 'bairro', 'uf', 'cidade', 'pontoreferencia', 'ativo'
    ];

    public function cliente()
    {
        return $this->belongsTo(ClienteImportacao::class);
    }
}


