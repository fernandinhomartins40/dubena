<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Nfclastrib extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'codigo', 'nome', 'descricao',
        'ind_gtribregular', 'ind_gcredpresoper', 'ind_gmonopadrao', 'ind_gmonoreten', 'ind_gmonoret', 'ind_gmonodif', 'ind_gestornocred'
    ];

    public function empresasGrupos()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }
}
