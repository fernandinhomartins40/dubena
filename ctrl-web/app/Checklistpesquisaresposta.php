<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Checklistpesquisaresposta
 *
 * @property int $CHECKLISTPERGUNTA_ID
 * @property int $CHECKLISTPESQUISA_ID
 * @property int $CHECKLISTRESPOSTA_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property string $RESPOSTA
 * @property string|null $UPDATED_AT
 * @property-read \App\Checklistpergunta $checkListPergunta
 * @property-read \App\Checklistpesquisa $checkListPesquisa
 * @property-read \App\Checklistresposta $checkListResposta
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisaresposta whereCHECKLISTPERGUNTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisaresposta whereCHECKLISTPESQUISAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisaresposta whereCHECKLISTRESPOSTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisaresposta whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisaresposta whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisaresposta whereRESPOSTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisaresposta whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Checklistpesquisaresposta extends Model
{

    protected $fillable = ['checklistpesquisa_id', 'checklistpergunta_id', 
        'checklistresposta_id', 'resposta'];

    public function checkListPesquisa()
    {
        return $this->belongsTo('App\Checklistpesquisa');
    }
    
    public function checkListPergunta()
    {
        return $this->belongsTo('App\Checklistpergunta','checklistpergunta_id');
    }
    
    public function checkListResposta()
    {
        return $this->belongsTo('App\Checklistresposta');
    }
    
}
