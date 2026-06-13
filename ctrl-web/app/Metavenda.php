<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Metavenda
 *
 * @property string|null $ACAO
 * @property string|null $CAUSA
 * @property string|null $CREATED_AT
 * @property string $DATAMETA
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property float $QUANTIDADECONVENIO
 * @property float $QUANTIDADEDESAFIO
 * @property float $QUANTIDADEPERFIL
 * @property float $QUANTIDADEVALEGAS
 * @property int $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property float $VALORCONVENIO
 * @property float $VALORDESAFIO
 * @property float $VALORMETA
 * @property float $VALORPERFIL
 * @property float $VALORVALEGAS
 * @property-read \App\Setor $Setor
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereCAUSA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereDATAMETA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereQUANTIDADECONVENIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereQUANTIDADEDESAFIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereQUANTIDADEPERFIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereQUANTIDADEVALEGAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereVALORCONVENIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereVALORDESAFIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereVALORMETA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereVALORPERFIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Metavenda whereVALORVALEGAS($value)
 * @mixin \Eloquent
 */
class Metavenda extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'produto_id', 'setor_id', 'datameta',
        'valormeta', 'valordesafio', 'valorperfil', 'quantidade', 'quantidadedesafio',
        'quantidadeperfil', 'causa', 'acao', 'valorvalegas', 'quantidadevalegas', 'valorconvenio', 'quantidadeconvenio'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function Setor()
    {
        return $this->belongsTo('App\Setor');
    }

}
