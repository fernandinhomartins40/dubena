<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquesetoracerto
 *
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $OBSERVACAO
 * @property int $PRODUTO_ID
 * @property int $QUANTIDADEANTIGA
 * @property int $QUANTIDADENOVA
 * @property int $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupos
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereQUANTIDADEANTIGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereQUANTIDADENOVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquesetoracerto whereUSERID($value)
 * @mixin \Eloquent
 */
class Estoquesetoracerto extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'produto_id', 'setor_id',
        'quantidadeantiga', 'quantidadenova', 'datahora', 'observacao', 'user_id'];

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

    public function user()
    {
        return $this->belongsTo('App\User');
    }

}
