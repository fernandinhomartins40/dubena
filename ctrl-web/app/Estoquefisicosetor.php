<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquefisicosetor
 *
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $ESTOQUEFISICO_ID
 * @property string $ESTOQUEREMOVER
 * @property string $ESTOQUEZERAR
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADEDIFERENCA
 * @property float $QUANTIDADEFISICA
 * @property float $QUANTIDADESISTEMA
 * @property int $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Estoquefechamento $estoqueFechamento
 * @property-read \App\Estoquefisico $estoqueFisico
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereESTOQUEFISICOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereESTOQUEREMOVER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereESTOQUEZERAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereQUANTIDADEDIFERENCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereQUANTIDADEFISICA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereQUANTIDADESISTEMA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisicosetor whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estoquefisicosetor extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'setor_id', 'estoquefechamento_id',
        'produto_id', 'estoquefisico_id', 'quantidadesistema', 'quantidadefisica',
        'quantidadediferenca', 'estoquezerar', 'estoqueremover'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function estoqueFisico()
    {
        return $this->belongsTo('App\Estoquefisico');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function estoqueFechamento()
    {
        return $this->belongsTo('App\Estoquefechamento');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

}
