<?php

namespace App\Processors;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\EmpresasGrupo;
use App\Empresa;
use App\Android;
use App\Conta;
use App\Contatipo;
use App\Banco;
use App\User;
use App\Contauser;
use App\Contafechamento;
use App\Contamovimento;
use App\Contamovimentoestorno;
use App\Contatransferencia;
use App\Financeiroparcela;
use App\Http\Controllers\Controller;
use Session;
use Illuminate\Support\Facades\Input;
use DB;
use Redirect;
use App\Services\CarbonCustom as Carbon;

class androidProcessor
{

    protected $android;
    protected $androidnovo;
    protected $errors = Array();

    public function __construct($android_id)
    {
        $android = Android::where('androidid', $android_id)->get();
        if(count($android)>0){
            $this->android = $android->first();
        } else {
            $this->android = null;
        }
    }
    public function setAndroid($value)
    {
        $this->androidnovo = $value;
    }

    public function getAndroid()
    {
        return $this->androidnovo;
    }

    public function registrarAndroid($androidnovo)
    {
        $this->androidnovo = $androidnovo;
        if($this->android == null){
            return ($this->inserirAndroid());
        } else {
            return ($this->atualizarAndroid());
        }
        return false;
    }
    
    public function atualizarAndroid()
    {
        
        DB::beginTransaction();
        try {
            //$this->android->descricao = $this->androidnovo->descricao;
            if(!$this->android->ativo){
                DB::rollback();
                $this->addError('Este dispositivo está inativo no cadastro. Verifique com a revenda.');
                return false;
            }
            $this->android->serie = $this->androidnovo->serie;
            $this->android->androidid = $this->androidnovo->androidid;
            $this->android->urlservidor = $this->androidnovo->urlservidor;
            $this->android->registrationid = $this->androidnovo->registrationid;
            $this->android->user_id = $this->androidnovo->user_id;
            $this->android->colaborador_id = $this->androidnovo->colaborador_id;
            $this->android->setor_id = $this->androidnovo->setor_id;
            $this->android->ativo = true;
            $this->android->save();
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }
    public function inserirAndroid()
    {
        DB::beginTransaction();
        try {
            $this->androidnovo->save();
        } catch (ValidationException $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }
    public function addError($error)
    {
        array_push($this->errors, $error);
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
