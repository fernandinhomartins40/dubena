<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Banco
 *
 * @property string|null $ATIVO
 * @property string $CODIGO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int|null $GRUPO_ID
 * @property int $ID
 * @property string|null $SITE
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Bancolayoutretorno[] $bancoLayout
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebido[] $chequeRecebido
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Conta[] $conta
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Banco whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Banco whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Banco whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Banco whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Banco whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Banco whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Banco whereSITE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Banco whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Banco extends Model
{

  protected $fillable = ['grupo_id', 'codigo', 'descricao', 'site', 'ativo'];

  public function empresasGrupo()
  {
    return $this->belongsTo('App\EmpresasGrupo');
  }

  public function bancoLayout()
  {
    return $this->hasMany('App\Bancolayoutretorno');
  }

  public function chequeRecebido()
  {
    return $this->hasMany('App\Chequerecebido');
  }

  public function conta()
  {
    return $this->hasMany('App\Conta');
  }


}
