<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Checklisttipo
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Checklistform[] $checkListForm
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklisttipo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklisttipo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklisttipo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklisttipo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklisttipo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Checklisttipo extends Model
{


    protected $fillable = ['descricao', 'ativo'];

    public function checkListForm()
    {
        return $this->hasMany('App\Checklistform');
    }

}
