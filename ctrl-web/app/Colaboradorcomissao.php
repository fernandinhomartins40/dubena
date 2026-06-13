<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Colaboradorcomissao
 *
 * @property string|null $ATIVO
 * @property int $COLABORADOR_ID
 * @property int $CONDICAOPAGAMENTO_ID
 * @property string|null $CREATED_AT
 * @property string $DATAFIM
 * @property string $DATAINICIO
 * @property int $EMPRESA_ID
 * @property float $EMPRESAVALOR
 * @property int $ID
 * @property float $PERCENTUAL
 * @property int $PRODUTO_ID
 * @property int $SETOR_ID
 * @property int|null $TIPOCOMISSAO
 * @property string|null $UPDATED_AT
 * @property-read \App\Colaborador $colaborador
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Comissaoexcecoes[] $comissaoexcecoes
 * @property-read \App\Condicaopagamento $condicaopagamento
 * @property-read \App\Empresa $empresa
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereDATAFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereDATAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereEMPRESAVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao wherePERCENTUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereTIPOCOMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorcomissao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Colaboradorcomissao extends Model
{

    protected $fillable = ['colaborador_id', 'condicaopagamento_id', 'produto_id', 'setor_id',
        'percentual', 'empresavalor', 'datainicio', 'datafim', 'ativo', 'empresa_id', 'tipocomissao', 'percentualapp', 'empresavalorapp', 'tonelagem'];

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }

    public function condicaopagamento()
    {
        return $this->belongsTo('App\Condicaopagamento');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    public function comissaoexcecoes()
    {
        return $this->hasMany('App\Comissaoexcecoes');
    }
}
