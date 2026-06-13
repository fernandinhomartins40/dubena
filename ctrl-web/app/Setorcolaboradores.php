<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Setorcolaboradores
 *
 * @property int $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property int $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setorcolaboradores whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setorcolaboradores whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setorcolaboradores whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setorcolaboradores whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setorcolaboradores whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Setorcolaboradores extends Model
{

    protected $fillable = ['setor_id', 'colaborador_id'];

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }


}
