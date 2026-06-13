<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Empresaconfignfe
 *
 * @property-read \App\Empresa $empresa
 * @property-read \App\Cliente $empresaNfeCliente
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @mixin \Eloquent
 */
class Empresaconfignfe extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfcecliente_id', 'emiteboletoauto',
        'pedidoemitenfce', 'pedidoemitenfceconfirma'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function empresaNfeCliente()
    {
        return $this->belongsTo('App\Cliente');
    }

}
