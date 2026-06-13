<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Pedidomotivoatraso
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Pedidosituacaohistorico[] $pedidoSituacaoHisorico
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidomotivoatraso whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidomotivoatraso whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidomotivoatraso whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidomotivoatraso whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidomotivoatraso whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidomotivoatraso whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Pedidomotivoatraso extends Model
{

    protected $fillable = ['grupo_id', 'descricao', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function pedidoSituacaoHisorico()
    {
        return $this->hasMany('App\Pedidosituacaohistorico');
    }

}
