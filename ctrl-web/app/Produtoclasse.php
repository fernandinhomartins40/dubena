<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Produtoclasse
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $TIPO
 * @property string|null $UPDATED_AT
 * @property-read \App\EmpresasGrupo $empresasGrupos
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoclasse whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoclasse whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoclasse whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoclasse whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoclasse whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoclasse whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoclasse whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Produtoclasse extends Model
{
    // Retirada dos campos Entrada e Saída foi acordada com flavio e luiz, uma vez que esse campo não está 
    // sendo utilizado em nenhuma tela do sistema a não ser a de classe de produtos.
    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'tipo'];

    public function empresasGrupos()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function selectClasse($grupo)
    {
        return $this->where('grupo_id',$grupo)->pluck('descricao','id');
    }

}
