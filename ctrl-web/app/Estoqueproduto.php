<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoqueproduto
 *
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property float $QUANTIDADEMAXIMA
 * @property float $QUANTIDADEMINIMA
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto whereQUANTIDADEMAXIMA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto whereQUANTIDADEMINIMA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoqueproduto whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estoqueproduto extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'produto_id', 'quantidade',
        'quantidademinima', 'quantidademaxima'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

}
