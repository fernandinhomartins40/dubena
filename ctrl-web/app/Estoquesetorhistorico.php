<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquesetorhistorico
 *
 * @property string|null $CREATED_AT
 * @property float|null $CUSTOMEDIO
 * @property string $DATAHORA
 * @property string $DATAHORACOMPETENCIA
 * @property int|null $EMPRESA_ID
 * @property string $ENTIDADE
 * @property int $ENTIDADE_ID
 * @property int|null $GRUPO_ID
 * @property int $ID
 * @property string $MOTIVO
 * @property string $MOVIMENTACAO
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property int $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereCUSTOMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereDATAHORACOMPETENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereENTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereENTIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereMOTIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereMOVIMENTACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetorhistorico whereUSERID($value)
 * @mixin \Eloquent
 */
class Estoquesetorhistorico extends Model
{
    protected $fillable = ['user_id', 'setor_id', 'produto_id', 'movimentacao', 'quantidade', 'customedio', 
        'motivo', 'datahora', 'datahoracompetencia', 'entidade', 'entidade_id', 'grupo_id', 'empresa_id'];

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }
    
    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

}
