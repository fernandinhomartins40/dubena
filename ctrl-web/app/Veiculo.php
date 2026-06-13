<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Veiculo
 *
 * @property int $ALERTASDIASANTES
 * @property string|null $ATIVO
 * @property int $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float $KMATUAL
 * @property float $KMINICIAL
 * @property float $KMTROCAOLEO
 * @property float $KMULTIMATROCAOLEO
 * @property string $PLACA
 * @property string $PNEUS
 * @property float $PNEUSVIDAUTILKM
 * @property int $TIPOCOMBUSTIVEL_ID
 * @property string|null $UPDATED_AT
 * @property string|null $USARASTREAMENTO
 * @property int $VEICULOTIPO_ID
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Tipocombustivel $tipocombustivel
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Veiculodocumento[] $veiculodocumentos
 * @property-read \App\Veiculotipo $veiculotipo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereALERTASDIASANTES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereKMATUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereKMINICIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereKMTROCAOLEO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereKMULTIMATROCAOLEO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo wherePLACA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo wherePNEUS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo wherePNEUSVIDAUTILKM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereTIPOCOMBUSTIVELID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereUSARASTREAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculo whereVEICULOTIPOID($value)
 * @mixin \Eloquent
 */
class Veiculo extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'veiculotipo_id', 'tipocombustivel_id',
        'colaborador_id', 'placa', 'descricao', 'kminicial', 'kmatual',
        'kmtrocaoleo', 'kmultimatrocaoleo', 'pneus', 'pneusvidautilkm', 'alertasdiasantes',
        'ativo', 'usarastreamento', 'placauf'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function veiculotipo()
    {
        return $this->belongsTo('App\Veiculotipo');
    }

    public function tipocombustivel()
    {
        return $this->belongsTo('App\Tipocombustivel');
    }

    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }

    public function veiculodocumentos()
    {
        return $this->hasMany('App\Veiculodocumento');
    }

    public function selectVeiculos($empresa)
    {
        return $this->where(['empresa_id'=>$empresa,'ativo'=>1])->pluck('placa','id');
    }
    public function setors()
    {
        return $this->belongsToMany('App\Setor', 'setor_veiculo', 'veiculo_id', 'setor_id');
    }
}
