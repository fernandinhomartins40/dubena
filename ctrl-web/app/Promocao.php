<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Promocao
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DATAHORAFIM
 * @property string $DATAHORAINICIO
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int|null $PREMIOPRODUTO_ID
 * @property int|null $PRODUTO_ID
 * @property int|null $QUANTIDADEPEDIDOS
 * @property int|null $QUANTIDADEPREMIOS
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Clientepromocao[] $clientePromocao
 * @property-read \App\Empresa $empresas
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Produto $premio
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereDATAHORAFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereDATAHORAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao wherePREMIOPRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereQUANTIDADEPEDIDOS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereQUANTIDADEPREMIOS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Promocao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Promocao extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'produto_id', 'premioproduto_id', 'descricao',
        'datahorainicio', 'datahorafim', 'quantidadepedidos', 'quantidadepremios', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresas()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function premio()
    {
        return $this->belongsTo('App\Produto', 'premioproduto_id');
    }

    public function clientePromocao()
    {
        return $this->hasMany('App\Clientepromocao');
    }

}
