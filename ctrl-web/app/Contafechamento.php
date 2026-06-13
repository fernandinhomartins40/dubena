<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contafechamento
 *
 * @property int|null $ABERTURA_USER_ID
 * @property string|null $ATIVO
 * @property int $CONTA_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORAABERTURA
 * @property string|null $DATAHORAFECHAMENTO
 * @property string $FECHADO
 * @property int|null $FECHAMENTO_USER_ID
 * @property int $ID
 * @property float|null $SALDOFINAL
 * @property float $SALDOINICIAL
 * @property string|null $UPDATED_AT
 * @property-read \App\Conta $conta
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contamovimento[] $contaMovimentos
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereABERTURAUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereDATAHORAABERTURA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereDATAHORAFECHAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereFECHADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereFECHAMENTOUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereSALDOFINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereSALDOINICIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contafechamento whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Contafechamento extends Model
{

    protected $fillable = ['conta_id', 'datahoraabertura', 'datahorafechamento', 'saldoinicial',
        'saldofinal', 'fechado', 'ativo'];

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }
    public function contaMovimentos()
    {
        return $this->hasMany('App\Contamovimento');
    }
}
