<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


/*
Tipos de perfis
1 - Caixa
2 - Aplicação
3 - Provisão;
4 - Antecip. Clientes
5 - Dívidas c/ Banco
6 - Antecip. Fornecedores
7 - Emprést. para Terceiros

*/
/**
 * App\Contatipo
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $PERFIL
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Conta[] $conta
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatipo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatipo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatipo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatipo whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatipo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatipo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatipo wherePERFIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatipo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Contatipo extends Model
{

    protected $fillable = ['descricao', 'grupo_id', 'empresa_id', 'perfil', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function conta()
    {
        return $this->hasMany('App\Conta');
    }

}
