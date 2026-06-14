<?php

namespace App\Monitora\Repository;
use DB;
use App\Monitora\Models\User;
use App\Monitora\Models\Setor;
use App\Nfipi;
use App\NFcest;
use App\Monitora\Models\Empresa;
use App\Produto;
use App\Cliente;
use App\Monitora\Models\Veiculo;
use App\Promocao;
use App\Segmento;
use App\Tipopessoa;
use App\Monitora\Models\Veiculotipo;
use App\Colaborador;
use App\Spedtipoitem;
use App\Produtoclasse;
use App\Unidademedida;
use App\Nfgrupofiscal;
use App\Pedidooperacao;
use App\Valegassituacao;
use App\Condicaopagamento;
use App\Setorcolaboradores;
use App\Vendaativaocorrenciatipo;

class SelectRepository {

    private $empresa_id;
    private $grupo_id;

    public function setEmpresa($empresa){
        $this->empresa_id = $empresa;
    }

    public function setGrupo($grupo){
        $this->grupo_id = $grupo;
    }

    public function getProdutoClasse(){
        return Produtoclasse::where(['grupo_id'=>$this->grupo_id,'ativo'=>1])->orderby('descricao')->pluck('descricao','id');
    }

    public function getUnidadeMedida(){
        return Unidademedida::where(['grupo_id'=>$this->grupo_id,'ativo'=>1])->orderby('descricao')->pluck('descricao','id');
    }

    public function getNfGrupoFiscal(){
        return Nfgrupofiscal::where(['ativo'=>1,'empresa_id'=>$this->empresa_id])->pluck('descricao', 'id');
    }

    public function getSpedTipoItem(){
        return Spedtipoitem::pluck('descricao', 'id');
    }

    public function getIpi(){
        return Nfipi::all();
    }

    public function getNfcest($cest,$ncm){
        return NFcest::where(['ncm'=>$ncm,'cest'=>$cest])->get();
    }

    public function getClienteJuridico(){
        $tipopessoa = Tipopessoa::where(['grupo_id'=>$this->grupo_id,'ativo'=>1,'tipopessoacadastro'=>'J'])->pluck('id')->toArray();
        $cliente = Cliente::where(['empresa_id'=>$this->empresa_id,'ativo'=>1,'cliente'=>1])->whereIn('tipopessoa_id',$tipopessoa)->pluck('nome', 'id');
        return $cliente;
    }

    public function getProdutos($classe = false){
        if($classe){
            $class = Produtoclasse::where([["tipo","G"],['grupo_id',$this->grupo_id]])->pluck('id')->toArray();
            return Produto::where(['ativo'=>1,'empresa_id' => $this->empresa_id])->whereIn('produtoclasse_id',$class)->orderby('descricao')->pluck('descricao', 'id');    
        }
        return Produto::where(['ativo'=>1,'empresa_id' => $this->empresa_id])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getCondicaoPagamento(){
        return Condicaopagamento::where(['ativo'=> 1,'grupo_id'=>$this->grupo_id])->where([['tipo','!=',4],['tipo','!=',5]])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getSituacao($tipo){
        return Valegassituacao::where(DB::raw('upper(descricao)'),mb_strtoupper($tipo))->get()->first();
    }

    public function getColaborador(){
        return Colaborador::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->orderby('nome')->pluck('nome','id');
    }

    public function getSetores(){
        return Setor::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->orderby('descricao')->pluck('descricao','id');
    }

    public function getPedidoOperacoes(){
        return Pedidooperacao::where(['grupo_id'=>$this->grupo_id,'ativo'=>1])->orderby('descricao')->pluck('descricao','id');
    }

    public function getSegmentos(){
        return Segmento::where(['grupo_id'=>$this->grupo_id,'ativo'=>1])->orderby('descricao')->pluck('descricao','id');
    }

    public function getTipoOcorrencia(){
        return Vendaativaocorrenciatipo::where(['grupo_id'=>$this->grupo_id,'ativo'=>1])->pluck('descricao','id');
    }

    public function getVeiculos(){
        return Veiculo::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->orderBy('placa')->pluck('placa','id');
    }

    public function getTipoVeiculos(){
        return Veiculotipo::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->orderBy('descricao')->pluck('descricao','id');
    }
    
    public function getSetorColaborador(){
        $setorscolaborador = Setorcolaboradores::pluck('colaborador_id')->toArray();
        $colaborador = Colaborador::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->whereIn('id',$setorscolaborador)->orderBy('nome')->pluck('nome','id');
        return $colaborador;
    }

    public function getEmpresasUser(){
        $empresas_id = User::find(\Auth::guard('monitora')->user()->id)->empresas()->pluck('id')->toArray();
		$empresas = Empresa::whereIn('id', $empresas_id)->orderBy('nome_fantasia')->orderBy('nome_fantasia')->get()->pluck('nome_fantasia', 'id');
        return $empresas;
    }

    public function getPromocoes(){
        return Promocao::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->orderBy('descricao')->get()->pluck('descricao','id');
    }

    public function getConvenios(){
        return Cliente::where(['empresa_id'=>$this->empresa_id,'ativo'=>1,'convenioativo'=>1])->pluck('nome','id');
    }

    public function getTipoPessoa(){
        return Tipopessoa::where(['grupo_id'=>$this->grupo_id,'ativo'=>1])->orderBy('descricao')->pluck('descricao','id');
    }
}