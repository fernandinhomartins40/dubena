<?php

namespace App\Http\controllers;

//use Request;
use DB;
use Image;
use App\Rua;
use Session;
use App\Regiao;
use App\Estado;
use App\Cidade;
use App\Empresa;
use App\Bairro;
use App\EmpresasGrupo;
use App\Veiculo;
use App\Veiculotipo;
use App\Setor;
use App\User;
use App\Http\Requests;
use App\Empresaconfig;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class CadastroController extends controller
{

    public function validateClientId()
    {
        $user = \Auth::user();
        try {
            if (!$user->access_token) {
                $token = buscarAccessToken(
                    env('DEFAULT_PASSWORD_SYSTEM'),
                    env('DEFAULT_USER_SYSTEM'),
                    '*',
                    'password',
                    $user->client_id,
                    env('DEFAULT_USER_SECRET')
                );
                $user->access_token = $token;
                $user->save();
            }
        } catch (\Exception $e) {
            $msg = "Atualize seu \"Cliente Id.\" e senha e tente novamente. Se o erro persistir, contate o suporte! " . $e->getMessage() . " " . $e->getLine() . " " . $e->getFile();
            Session::flash('message_danger', $msg);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->validateClientId();
        return view('cadastros.cadastros');
    }

    public function indexnew()
    {
        $this->validateClientId();
        return view('cadastros.cadastrosnew');
    }

    public function atualizarCadastro(Request $request)
    {
        try {
            $this->validateClientId();
            $tipocadastro = $request->only('tipocadastro')["tipocadastro"];
            if ($tipocadastro == 1) { //GRUPOS
                return response()->json($this->atualizarGrupo());
            } else if ($tipocadastro == 2) {
                return response()->json($this->atualizarEmpresas());
            } else if ($tipocadastro == 3) {
                return response()->json($this->atualizarVeiculos());
            } else if ($tipocadastro == 4) {
                return response()->json($this->atualizarSetores());
            } else if ($tipocadastro == 5) {
                return response()->json($this->atualizarUsers());
            } else if ($tipocadastro == 99) {
                $ret = $this->atualizarGrupo();
                if ($ret != 'OK|') {
                    return response()->json($ret);
                }
                $ret = $this->atualizarEmpresas();
                if ($ret != 'OK|') {
                    return response()->json($ret);
                }
                $ret = $this->atualizarVeiculos();
                if ($ret != 'OK|') {
                    return response()->json($ret);
                }
                $ret = $this->atualizarSetores();
                if ($ret != 'OK|') {
                    return response()->json($ret);
                }
                $ret = $this->atualizarUsers();
                return response()->json($ret);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage());
        }

        //return response()->json($tipocadastro);
        return response()->json('OK|');
    }

    public function criarCadastro(Request $request)
    {
        $this->validateClientId();
        $empresa_id = $request->only('empresa_id')["empresa_id"];

        if ($empresa_id == null || $empresa_id == '' || $empresa_id <= 0) {
            return response()->json('Informe um código de empresa válido.');
        }
        $empresa = Empresa::find($empresa_id);
        if ($empresa != null) {
            // return response()->json('Empresa já existente no banco de dados. Criação não realizada.');
        }
        $ret = $this->atualizarGrupo($empresa_id);
        if ($ret != 'OK|') {
            return response()->json($ret);
        }
        $ret = $this->atualizarEmpresas($empresa_id);
        if ($ret != 'OK|') {
            return response()->json($ret);
        }
        $ret = $this->atualizarVeiculos($empresa_id);
        if ($ret != 'OK|') {
            return response()->json($ret);
        }
        $ret = $this->atualizarSetores($empresa_id);
        if ($ret != 'OK|') {
            return response()->json($ret);
        }
        $ret = $this->atualizarUsers($empresa_id);
        return response()->json($ret);

        //return response()->json($tipocadastro);
        return response()->json('OK|');
    }

    public function atualizarGrupo($empresa_id = null)
    {
        $this->validateClientId();
        $response = buscarDadosRastreamento('getRastreamentoGrupos', $empresa_id);
        $registros = json_decode($response);
        foreach ($registros->dados as $registro) {
            $grupo = EmpresasGrupo::find($registro->id);
            if ($grupo != null) {
                $grupo->descricao = $registro->descricao;
                $grupo->ativo = true;
                $grupo->save();
            } else {
                $grupo = new EmpresasGrupo();
                $grupo->id = $registro->id;
                $grupo->descricao = $registro->descricao;
                $grupo->ativo = true;
                $grupo->save();
            }
        }
        return ('OK|');
    }

    public function atualizarEmpresas($empresa_id = null)
    {
        $this->validateClientId();
        $response = buscarDadosRastreamento('getRastreamentoEmpresas', $empresa_id);
        $registros = json_decode($response);
        foreach ($registros->dados as $registro) {
            $empresa = Empresa::find($registro->id);

            if ($empresa != null) {
                $empresa->grupo_id = $registro->grupo_id;
                $empresa->razao_social = $registro->razao_social;
                $empresa->nome_fantasia = $registro->nome_fantasia;
                $empresa->cnpj = $registro->cnpj;
                $empresa->inscricao_estadual = $registro->inscricao_estadual;
                $empresa->nome_informal = $registro->nome_informal;
                $empresa->latitude = $registro->latitude;
                $empresa->longitude = $registro->longitude;
                $empresa->ativo = true;
                $empresa->keygooglemaps = $registro->keygooglemaps;
                if ($empresa->tempoparado == null) {
                    $empresa->tempoparado = 300;
                }
                $empresa->save();
            } else {
                $empresa = new Empresa();
                $empresa->id = $registro->id;
                $empresa->grupo_id = $registro->grupo_id;
                $empresa->razao_social = $registro->razao_social;
                $empresa->nome_fantasia = $registro->nome_fantasia;
                $empresa->cnpj = $registro->cnpj;
                $empresa->inscricao_estadual = $registro->inscricao_estadual;
                $empresa->nome_informal = $registro->nome_informal;
                $empresa->latitude = $registro->latitude;
                $empresa->longitude = $registro->longitude;
                $empresa->ativo = true;
                $empresa->keygooglemaps = $registro->keygooglemaps;
                $empresa->tempoparado = 300;
                $empresa->save();
            }
        }
        return ('OK|');
    }

    public function atualizarVeiculos($empresa_id = null)
    {
        $this->validateClientId();
        $response = buscarDadosRastreamento('getRastreamentoVeiculos', $empresa_id);
        $registros = json_decode($response);
        foreach ($registros->dados as $registro) {
            $veiculo = Veiculo::find($registro->id);
            if ($veiculo != null) {
                $veiculo->grupo_id = $registro->grupo_id;
                $veiculo->empresa_id = $registro->empresa_id;
                $veiculo->veiculotipo_id = $registro->veiculotipo_id;
                $veiculo->descricao = $registro->descricao;
                $veiculo->placa = $registro->placa;
                $veiculo->ativo = $registro->ativo;
                $veiculo->motorista = $registro->motorista;
                $veiculo->save();
            } else {
                $veiculo = new Veiculo();
                $veiculo->id = $registro->id;
                $veiculo->grupo_id = $registro->grupo_id;
                $veiculo->empresa_id = $registro->empresa_id;
                $veiculo->veiculotipo_id = $registro->veiculotipo_id;
                $veiculo->descricao = $registro->descricao;
                $veiculo->placa = $registro->placa;
                $veiculo->ativo = $registro->ativo;
                $veiculo->motorista = $registro->motorista;
                $veiculo->save();
            }
        }
        return ('OK|');
    }

    public function atualizarSetores($empresa_id = null)
    {
        $this->validateClientId();
        $response = buscarDadosRastreamento('getRastreamentoSetors', $empresa_id);
        $registros = json_decode($response);
        foreach ($registros->dados as $registro) {
            $setor = Setor::find($registro->id);
            if ($setor != null) {
                $setor->grupo_id = $registro->grupo_id;
                $setor->empresa_id = $registro->empresa_id;
                $setor->descricao = $registro->descricao;
                $setor->latitude = $registro->latitude;
                $setor->longitude = $registro->longitude;
                $setor->rua = $registro->rua;
                $setor->numero = $registro->numero;
                $setor->cep = $registro->cep;
                $setor->bairro = $registro->bairro;
                $setor->cidade = $registro->cidade;
                $setor->uf = $registro->uf;
                $setor->ativo = $registro->ativo;
                $setor->save();
            } else {
                $setor = new Setor();
                $setor->id = $registro->id;
                $setor->grupo_id = $registro->grupo_id;
                $setor->empresa_id = $registro->empresa_id;
                $setor->descricao = $registro->descricao;
                $setor->latitude = $registro->latitude;
                $setor->longitude = $registro->longitude;
                $setor->rua = $registro->rua;
                $setor->numero = $registro->numero;
                $setor->cep = $registro->cep;
                $setor->bairro = $registro->bairro;
                $setor->cidade = $registro->cidade;
                $setor->uf = $registro->uf;
                $setor->ativo = $registro->ativo;
                $setor->save();
            }
        }
        return ('OK|');
    }

    public function atualizarUsers($empresa_id = null)
    {
        $this->validateClientId();
        $response = buscarDadosRastreamento('getRastreamentoUsers', $empresa_id);
        $registros = json_decode($response);
        $atualUserId = \Auth::user()->id;
        $allUsers = User::whereIn('id', collect($registros->dados)->pluck('id'))->get();
        foreach ($registros->dados as $registro) {
            $user = $allUsers->where('id', $registro->id)->first();
            if ($user != null) {
                //$user->grupo_id = $registro->grupo_id;
                $user->empresa_id = $registro->empresa_id;
                $user->email = $registro->email;
                $user->name = $registro->name;
                $user->password = $registro->password;
                $user->client_id = $registro->client_id;
                $user->ativo = $registro->ativo;
                if ($atualUserId !== $registro->id)
                    $user->access_token = null; //buscarAccessToken($registro->password, $registro->email, '', 'password', $registro->client_id);
                $user->save();
            } else {
                $user = new User();
                $user->id = $registro->id;
                //$user->grupo_id = $registro->grupo_id;
                $user->empresa_id = $registro->empresa_id;
                $user->email = $registro->email;
                $user->name = $registro->name;
                $user->password = $registro->password;
                $user->client_id = $registro->client_id;
                $user->ativo = $registro->ativo;
                $user->access_token = null; //buscarAccessToken('1234', $registro->email, '', 'password', $registro->client_id);
                $user->save();
            }
        }
        return ('OK|');
    }
}
