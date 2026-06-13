<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquefechamentosetor
 *
 * @property string|null $CREATED_AT
 * @property float $CUSTOMEDIO
 * @property int $EMPRESA_ID
 * @property int $ESTOQUEFECHAMENTO_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float $PRECOVENDA
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property int $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Estoquefechamento $estoqueFechamento
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereCUSTOMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereESTOQUEFECHAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor wherePRECOVENDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamentosetor whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estoquefechamentosetor extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'setor_id', 'estoquefechamento_id',
        'produto_id', 'quantidade', 'customedio', 'precovenda'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    public function estoqueFechamento()
    {
        return $this->belongsTo('App\Estoquefechamento');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

}
