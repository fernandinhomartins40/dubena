<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Checklistresposta
 *
 * @property string $ALERTA
 * @property int $CHECKLISTPERGUNTA_ID
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string $RESPOSTA
 * @property int $TIPOPERGUNTA
 * @property string|null $UPDATED_AT
 * @property-read \App\Checklistpergunta $checkListPerguntas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Checklistpesquisaresposta[] $checkListPesquisaResposta
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistresposta whereALERTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistresposta whereCHECKLISTPERGUNTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistresposta whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistresposta whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistresposta whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistresposta whereRESPOSTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistresposta whereTIPOPERGUNTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistresposta whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Checklistresposta extends Model
{

    protected $fillable = ['checklistpergunta_id', 'descricao', 'tipopergunta', 'resposta', 'alerta'];

    public function checkListPerguntas()
    {
        return $this->belongsTo('App\Checklistpergunta','checklistpergunta_id');
    }

    public function checkListPesquisaResposta()
    {
        return $this->hasMany('App\Checklistpesquisaresposta');
    }

}
