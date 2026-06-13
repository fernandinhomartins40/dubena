<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contamovimento
 *
 * @property string|null $ATIVO
 * @property int $CONTA_ID
 * @property int|null $CONTAFECHAMENTO_ID
 * @property int|null $CONTAMOVIMENTOTIPO_ID
 * @property int|null $CONTATRANSFERENCIA_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORABAIXA
 * @property float $DESCONTO
 * @property string|null $DESCRICAO
 * @property int|null $FINANCEIROPARCELA_ID
 * @property int $ID
 * @property float $JUROS
 * @property float $MULTA
 * @property string|null $ORIGEM
 * @property string $PAGARRECEBER
 * @property string $RETROATIVO
 * @property string|null $UPDATED_AT
 * @property int|null $USER_ID
 * @property float $VALOR
 * @property float $VALOREFETIVADO
 * @property-read \App\Conta $conta
 * @property-read \App\Contafechamento $contaFechamento
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contamovimentoestorno[] $contaMovimentoEstorno
 * @property-read \App\Contamovimentotipo $contaMovimentoTipo
 * @property-read \App\Contatransferencia $contaTransferencia
 * @property-read \App\Contafechamento $fechamento
 * @property-read \App\Financeiroparcela $financeiroParcela
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereCONTAFECHAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereCONTAMOVIMENTOTIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereCONTATRANSFERENCIAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereDATAHORABAIXA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereDESCONTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereJUROS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereMULTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereORIGEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento wherePAGARRECEBER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereRETROATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimento whereVALOREFETIVADO($value)
 * @mixin \Eloquent
 */
class Contamovimento extends Model
{
    
    protected $fillable = ['contamovimentotipo_id', 'financeiroparcela_id', 'contatransferencia_id',
        'conta_id', 'datahorabaixa', 'valor', 'multa', 'juros', 'desconto', 'valorefetivado',
        'pagarreceber', 'ativo', 'contafechamento_id', 'descricao', 'origem', 'user_id', 'retroativo', 'ofxuniqueid'];

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function contaMovimentoTipo()
    {
        return $this->belongsTo('App\Contamovimentotipo', 'contamovimentotipo_id');
    }

    public function financeiroParcela()
    {
        return $this->belongsTo('App\Financeiroparcela', 'financeiroparcela_id');
    }

    public function contaTransferencia()
    {
        return $this->belongsTo('App\Contatransferencia', 'contatransferencia_id');
    }
    public function contaFechamento()
    {
        return $this->belongsTo('App\Contafechamento');
    }
    public function fechamento()
    {
        return $this->belongsTo('App\Contafechamento', 'contafechamento_id');
    }
    public function contaMovimentoEstorno()
    {
        return $this->hasMany('App\Contamovimentoestorno');
    }
    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
