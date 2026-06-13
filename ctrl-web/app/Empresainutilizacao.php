<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Empresainutilizacao
 *
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $NFESERIE
 * @property int $NFETIPOAMBIENTE
 * @property int $NFIN
 * @property int $NFMODELO
 * @property int|null $NFSITUACAO_ID
 * @property int $NINI
 * @property string|null $PROTOCOLO
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property string $XJUST
 * @property string|null $XMLENVIO
 * @property string|null $XMLRETORNO
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Nfsituacao $nfsituacao
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereNFESERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereNFETIPOAMBIENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereNFIN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereNFMODELO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereNFSITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereNINI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao wherePROTOCOLO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereXJUST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereXMLENVIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresainutilizacao whereXMLRETORNO($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 */
class Empresainutilizacao extends Model
{
    use \App\Services\RevisionsTraitService;

    protected $identity = 'empresa_id';

    // protected $revisionCreationsEnabled = true;

    protected $fillable = [
        'grupo_id', 'empresa_id', 'user_id', 'datahora', 'xmlenvio', 
        'xjust', 'nini', 'nfin', 'xmlretorno', 'nfsituacao_id', 
        'nfmodelo', 'nfeserie', 'nfetipoambiente', 'protocolo'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function nfsituacao()
    {
        return $this->belongsTo('App\Nfsituacao');
    }
}
