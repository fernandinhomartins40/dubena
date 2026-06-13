<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Checklistpergunta
 *
 * @property int $CHECKLIST_ID
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property int $TIPOPERGUNTA
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Checklistpesquisaresposta[] $checkListPesquisaResposta
 * @property-read \App\Checklist $checklist
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Checklistresposta[] $checklistresposta
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpergunta whereCHECKLISTID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpergunta whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpergunta whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpergunta whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpergunta whereTIPOPERGUNTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpergunta whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Checklistpergunta extends Model
{

    protected $fillable = ['checklist_id', 'descricao', 'tipopergunta'];

    public function checklist()
    {
        return $this->belongsTo('App\Checklist','checklist_id');
    }

    public function checkListPesquisaResposta()
    {
        return $this->hasMany('App\Checklistpesquisaresposta');
    }

    
    public function checklistresposta()
    {
        return $this->hasMany('App\Checklistresposta');
    }
}
