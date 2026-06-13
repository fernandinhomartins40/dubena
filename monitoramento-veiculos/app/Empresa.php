<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{

    protected $fillable = ['grupo_id', 'razao_social', 'nome_fantasia', 'cnpj',
        'inscricao_estadual', 'ativo', 'uf', 'logo',
        'nome_informal', 'latitude', 'longitude', 'keygooglemaps', 'tempoparado'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function users()
    {
        return $this->belongsToMany('App\User')->withTimestamps();
    }
    public function setors()
    {
      return $this->hasMany('App\Setor');
    }
    public function activesetors()
    {
      return $this->hasMany('App\Setor')->where('ativo', true)->orderBy('descricao');
    }
    public function veiculos()
    {
      return $this->hasMany('App\Veiculo');
    }
    public function activeveiculos()
    {
      return $this->hasMany('App\Veiculo')->where('ativo', true)->orderBy('descricao');
    }
    public function ultimaposicaos()
    {
      return $this->hasMany('App\Ultimaposicao');
    }
}
