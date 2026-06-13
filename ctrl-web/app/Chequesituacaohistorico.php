<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequesituacaohistorico
 *
 * @property int $CHEQUERECEBIDO_ID
 * @property int $CHEQUESITUACAO_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORAPROCESSO
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Chequerecebido $chequeRecebido
 * @property-read \App\Chequesituacao $chequeSituacao
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacaohistorico whereCHEQUERECEBIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacaohistorico whereCHEQUESITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacaohistorico whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacaohistorico whereDATAHORAPROCESSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacaohistorico whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacaohistorico whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Chequesituacaohistorico extends Model
{
    protected $fillable = ['chequesituacao_id', 'chequerecebido_id', 'datahoraprocesso'];

    public function chequeSituacao()
    {
    	return $this->belongsTo("App\Chequesituacao", 'chequesituacao_id');
    }

    public function chequeRecebido()
    {
    	return $this->belongsTo("App\Chequerecebido", 'chequerecebido_id');
    }
}
