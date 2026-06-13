<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contamovimentoestorno
 *
 * @property string $ATIVO
 * @property int|null $CONTA_ID
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
 * @property string $MOTIVO
 * @property float $MULTA
 * @property string|null $ORIGEM
 * @property string $PAGARRECEBER
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property float $VALOR
 * @property float $VALOREFETIVADO
 * @property-read \App\Conta $conta
 * @property-read \App\Contafechamento $contaFechamento
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contamovimentoestorno[] $contaMovimentoEstorno
 * @property-read \App\Contamovimentotipo $contaMovimentoTipo
 * @property-read \App\Contatransferencia $contaTransferencia
 * @property-read \App\Financeiroparcela $financeiroParcela
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereCONTAFECHAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereCONTAMOVIMENTOTIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereCONTATRANSFERENCIAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereDATAHORABAIXA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereDESCONTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereJUROS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereMOTIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereMULTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereORIGEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno wherePAGARRECEBER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentoestorno whereVALOREFETIVADO($value)
 * @mixin \Eloquent
 */
class Contamovimentoestorno extends Model
{
    
protected $fillable = ['contamovimentotipo_id', 'financeiroparcela_id', 'contatransferencia_id',
        'conta_id', 'datahorabaixa', 'valor', 'multa', 'juros', 'desconto', 'valorefetivado',
        'pagarreceber', 'ativo', 'contafechamento_id', 'descricao', 'origem', 'user_id', 'motivo'];

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function contaMovimentoTipo()
    {
        return $this->belongsTo('App\Contamovimentotipo');
    }

    public function financeiroParcela()
    {
        return $this->belongsTo('App\Financeiroparcela', 'financeiroparcela_id');
    }

    public function contaTransferencia()
    {
        return $this->belongsTo('App\Contatransferencia');
    }
    public function contaFechamento()
    {
        return $this->belongsTo('App\Contafechamento');
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
