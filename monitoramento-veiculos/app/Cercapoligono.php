<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cercapoligono extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'cerca_id',
        'latitude', 'longitude'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }
    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }
    public function cerca()
    {
        return $this->belongsTo('App\Cerca');
    }
}
