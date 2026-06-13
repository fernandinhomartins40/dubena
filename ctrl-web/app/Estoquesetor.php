<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquesetor
 *
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property float $QUANTIDADEMAXIMA
 * @property float $QUANTIDADEMINIMA
 * @property int $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupos
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereQUANTIDADEMAXIMA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereQUANTIDADEMINIMA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetor whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estoquesetor extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'produto_id', 'setor_id', 
        'quantidade', 'quantidademinima', 'quantidademaxima'];

    public function empresasGrupos()
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

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }
}
