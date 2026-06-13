<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Atualizacaoprecos
 *
 * @property string|null $CREATED_AT
 * @property string|null $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $MUDOUBASE
 * @property int $PRODUTO_ID
 * @property string $TIPO
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property float $VALOR
 * @property string $VARIACAO
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $grupo
 * @property-read \App\Produto $produto
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereMUDOUBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Atualizacaoprecos whereVARIACAO($value)
 * @mixin \Eloquent
 */
class Atualizacaoprecos extends Model
{
    protected $fillable = ["created_at", "descricao", "empresa_id", "grupo_id", "mudoubase", "produto_id",
         "tipo", "updated_at", "user_id", "valor", "variacao"];
    
    public function grupo()
    {
        return $this->belongsTo('App\EmpresasGrupo','grupo_id');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }
}
