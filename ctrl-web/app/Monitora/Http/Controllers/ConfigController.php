<?php

namespace App\Monitora\Http\Controllers;
use DB;
use Session;
use Redirect;
use Exception;
use App\Monitora\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Monitora\Http\Requests\ConfigRequest;

class ConfigController extends Controller {

    protected $config;

    public function index() {
        $this->definition();
        $config = $this->config !== null ? $this->config : null;
        return view('monitora.configs.config')->with(compact('config'));
    }

    public function store(ConfigRequest $request) {
        $this->definition();
        $data = $request->all();
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);
        DB::beginTransaction();
        try {
            $config = Config::create($data);
        } catch (\Exception $ex) {
            DB::rollback();
            return Redirect::to('/config')
                            ->withErrors($ex->getMessage())
                            ->withInput();
        }
        DB::commit();
        Session::put('config',$config);
        return Redirect::to('/home')->withMessageSuccess('Sucesso! As configurações foram salvas.');
    }

    public function update(ConfigRequest $request, $id) {
        $this->definition();
        $data = $request->all();
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);
        if($data["passwordtraccar"]==''){
            unset($data["passwordtraccar"]);
        }
        DB::beginTransaction();
        try {
            $config = Config::findOrFail($id);
            $config->update($data);
        } catch (Exception $ex) {
            DB::rollback();
            return Redirect::to('/config')
                            ->withErrors($ex->getMessage())
                            ->withInput();
        }
        DB::commit();
        Session::put('config',$config);
        
        return Redirect::to('/home')->withMessageSuccess('Sucesso! Mudanças nas configurações salvas.');
    }

    private function dadosExtras($data) {
        $this->definition();
        $data["urlsistemaweb"] = isset($data["urlsistemaweb"]) ? $data["urlsistemaweb"] : '';
        $data["urltraccar"] = isset($data["urltraccar"]) ? $data["urltraccar"] : '';
        $data["usertraccar"] = isset($data["usertraccar"]) ? $data["usertraccar"] : '';
        $data["passwordtraccar"] = isset($data["passwordtraccar"]) ? $data["passwordtraccar"] : '';
        $data["keygooglemaps"] = isset($data["keygooglemaps"]) ? $data["keygooglemaps"] : '';
        $data["temporefresh"] = isset($data["temporefresh"]) ? $data["temporefresh"] : '';

        return $data;
    }

    private function definition(){
        $this->config = Config::all()->first();
    }

}
