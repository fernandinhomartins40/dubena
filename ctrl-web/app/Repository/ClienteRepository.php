<?php

/**
 * Created by PhpStorm.
 * User: jeff
 * Date: 04/06/2018
 * Time: 14:59
 */

namespace App\Repository;


use App\Bairro;
use App\Cidade;
use App\Cliente;
use App\Clientecontatosituacao;
use App\Clientecontatotipo;
use App\Colaborador;
use App\Condicaopagamento;
use App\Estado;
use App\Estadocivil;
use App\Parentesco;
use App\Produto;
use App\Promocao;
use App\Rua;
use App\Segmento;
use App\Setor;
use App\Telefonetipo;

class ClienteRepository
{

    /**
     * @param $grupo_id
     * @param $uf
     * @return \Illuminate\Support\Collection
     */
    public static function getCidades($grupo_id, $uf)
    {
        return Cidade::where([['grupo_id', $grupo_id], ['uf', $uf]])
            ->orWhere([['grupo_id', null], ['uf', $uf]])
            ->orderby('descricao')->pluck('descricao', 'id');
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public static function getEstados()
    {
        return Estado::all()->pluck('descricao', 'uf');
    }

    /**
     * @param $grupo_id
     * @return \Illuminate\Support\Collection
     */
    public static function getTelefoneTipos($grupo_id)
    {
        return Telefonetipo::where('ativo', 1)
            ->where('grupo_id', $grupo_id)->select('descricao', 'id')
            ->orderBy('descricao')->pluck('descricao', 'id');
    }

    /**
     * @param $grupo_id
     * @return \Illuminate\Support\Collection
     */
    public static function getContatoSituacoes($grupo_id)
    {
        return Clientecontatosituacao::where('ativo', 1)
            ->where('grupo_id', $grupo_id)->select('descricao', 'id')
            ->orderBy('descricao')->pluck('descricao', 'id');
    }

    /**
     * @param $grupo_id
     * @return \Illuminate\Support\Collection
     */
    public static function getContatoTipos($grupo_id)
    {
        return Clientecontatotipo::where('ativo', 1)
            ->where('grupo_id', $grupo_id)->select('descricao', 'id')
            ->orderBy('descricao')->pluck('descricao', 'id');
    }

    /**
     * @param $grupo_id
     * @return \Illuminate\Support\Collection
     */
    public static function getEstadoCivil($grupo_id)
    {
        return Estadocivil::where('ativo', 1)
            ->where('grupo_id', $grupo_id)->select('descricao', 'id')->orderBy('descricao')->pluck('descricao', 'id');
    }

    /**
     * @param $grupo_id
     * @return \Illuminate\Support\Collection
     */
    public static function getParentesco($grupo_id)
    {
        return Parentesco::where([
            ['ativo', 1],
            ['grupo_id', $grupo_id]
        ])->select('descricao', 'id')->pluck('descricao', 'id');
    }

    /**
     * @param $grupo_id
     * @return $this
     */
    public static function getSegmentos($grupo_id)
    {
        return Segmento::where('ativo', 1)
            ->where('grupo_id', $grupo_id)
            ->select('descricao', 'id')
            ->orderBy('descricao')->pluck('descricao', 'id')
            ->prepend('Selecione', '');
    }

    /**
     * @param $empresa_id
     * @return \Illuminate\Support\Collection
     */
    public static function getEmpresaConveniada($empresa_id)
    {
        return Cliente::where('ativo', 1)
            ->where('convenioativo', '1')
            ->where('empresa_id', $empresa_id)->select('nome', 'id')->orderby('nome')
            ->pluck('nome', 'id')->prepend('Selecione', '');
    }

    /**
     * @param $empresa_id
     * @return \Illuminate\Support\Collection
     */
    public static function  getSetores($empresa_id)
    {
        return Setor::where('ativo', 1)
            ->where('empresa_id', $empresa_id)->select('descricao', 'id')->orderBy('descricao')
            ->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    /**
     * @param $empresa_id
     * @return \Illuminate\Support\Collection
     */
    public static function  getColaboradores($empresa_id)
    {
        return Colaborador::where('ativo', 1)
            ->where('empresa_id', $empresa_id)->select('nome', 'id')->orderBy('nome')
            ->pluck('nome', 'id')->prepend('Selecione', '');
    }

    /**
     * @param $grupo_id
     * @return \Illuminate\Support\Collection
     */
    public static function getCondicaoPagamento($grupo_id)
    {
        return Condicaopagamento::where('ativo', 1)
            ->where('grupo_id', $grupo_id)->select('descricao', 'id')->orderby('descricao')
            ->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    /**
     * @param $empresa_id
     * @return \Illuminate\Support\Collection
     */
    public static function getPromocoes($empresa_id)
    {
        return Promocao::where('ativo', 1)
            ->where('empresa_id', $empresa_id)
            ->select('descricao', 'id')->pluck('descricao', 'id')
            ->prepend('Selecione', '');
    }

    /**
     * @param $empresa_id
     * @return \Illuminate\Support\Collection
     */
    public static function getProdutos($empresa_id)
    {
        return Produto::where('ativo', 1)
            ->where('empresa_id', $empresa_id)->select('descricao', 'id')
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    /**
     * @param $cidade_id
     * @param $grupo_id
     * @return \Illuminate\Support\Collection
     */
    public static function getRuas($cidade_id, $grupo_id)
    {
        return Rua::where('cidade_id', $cidade_id)
            ->where('grupo_id', $grupo_id)
            ->where('ativo', 1)
            ->orderby('descricao')
            ->pluck('descricao', 'id');
    }

    /**
     * @param $cidade_id
     * @param $grupo_id
     * @return \Illuminate\Support\Collection
     */
    public static function getBairros($cidade_id, $grupo_id)
    {
        return Bairro::where('cidade_id', $cidade_id)
            ->where('grupo_id', $grupo_id)
            ->orderby('descricao')->pluck('descricao', 'id');
    }
}
