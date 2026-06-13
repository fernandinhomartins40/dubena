<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Colaborador extends Model
{

    
    protected $fillable = ['grupo_id', 'empresa_id', 'nome', 'email', 'datanascimento', 'dataadmissao',
        'datadesligamento', 'sexo', 'cpf', 'rg', 'rgorgao', 'rguf', 'estadocivil_id', 'rua_id',
        'numero', 'complemento', 'cidade_id', 'cep', 'bairro_id', 'setor_id', 'ativo', 'tipopessoa_id',
        'uf', 'observacoes', 'fantasia', 'cnpj', 'inscricao_estadual',
        'ponto_referencia', 'observacoes', 'simples', 'indicador_ie', 'ctps', 'cargo_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function bairro()
    {
        return $this->belongsTo('App\Bairro');
    }

    public function cidade()
    {
        return $this->belongsTo('App\Cidade');
    }

    public function rguf()
    {
        return $this->belongsTo('App\Estado', 'rguf');
    }

    public function telefones()
    {
        return $this->hasMany('App\Colaboradortelefone');
    }

    public function estadocivil()
    {
        return $this->belongsTo('App\Estadocivil');
    }

    public function colaboradorfamilias()
    {
        return $this->hasMany('App\Colaboradorfamilia');
    }

    public function colaboradorferias()
    {
        return $this->hasMany('App\Colaboradorferias');
    }

    public function colaboradorexames()
    {
        return $this->hasMany('App\Colaboradorexame');
    }

    public function ultimoexame()
    {
        return $this->hasOne('App\Colaboradorexame')->orderBy('datavencimento', 'desc')->first();
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }
    public function setors()
    {
        return $this->belongsToMany('App\Setor', 'setorcolaboradores');
    }

    public function uf()
    {
        return $this->belongsTo('App\Estado', 'uf');
    }

    public function tipoPessoa()
    {
        return $this->belongsTo('App\Tipopessoa');
    }

    public function rua()
    {
        return $this->belongsTo('App\Rua', 'rua_id');
    }

    public function colaboradorComissao()
    {
        return $this->hasMany('App\Colaboradorcomissao');
    }
    
    public function cargo()
    {
        return $this->belongsTo('App\Cargo');
    }

    public function selectColaborador($empresa)
    {
        return $this->where(['empresa_id'=>$empresa,'ativo'=>1])->pluck('nome','id');
    }
}
