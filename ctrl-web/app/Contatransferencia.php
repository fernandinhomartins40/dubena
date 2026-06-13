<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contatransferencia
 *
 * @property int|null $CONTAMOVIMENTOTIPO_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORAPROCESSO
 * @property int $DESTINOCONTA_ID
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $ORIGEMCONTA_ID
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contamovimento[] $contaMovimentos
 * @property-read \App\Conta $destinoConta
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Conta $origemConta
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereCONTAMOVIMENTOTIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereDATAHORAPROCESSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereDESTINOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereORIGEMCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatransferencia whereVALOR($value)
 * @mixin \Eloquent
 */
class Contatransferencia extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'origemconta_id', 'destinoconta_id', 
        'valor', 'datahoraprocesso'];

    public function origemConta()
    {
        return $this->belongsTo('App\Conta', 'origemconta_id');
    }

    public function destinoConta()
    {
        return $this->belongsTo('App\Conta', 'destinoconta_id');
    }

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function contaMovimentos()
    {
        return $this->hasMany('App\Contamovimento');
    }

}
