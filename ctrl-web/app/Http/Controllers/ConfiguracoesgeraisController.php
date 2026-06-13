<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Configuracoesgerais;

class ConfiguracoesgeraisController extends Controller
{

    private $rules = [
        "rtcnpj"        => "required",
        "rtcontato"     => "required",
        "rtemail"       => "required",
        "rttelefone"    => "required"
    ];

    public function index()
    {
        $configG = Configuracoesgerais::first();
        return view('general.configuracoesgerais.configuracoesgerais', compact('configG'));
    }

    public function store(Request $request)
    {
        $this->validate($request, $this->rules);
        try {
            $data = $request->all();
            $this->treatData($data);
            $config = Configuracoesgerais::first();
            if (is_null($config))
                Configuracoesgerais::create($data);
            else
                $config->update($data);
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        return 'OK|';
    }

    private function treatData(&$data)
    {
        $data["emailrequerconexaotls"] = isset($data["emailrequerconexaotls"]) && $data["emailrequerconexaotls"];
        $data["emailrequerautenticacao"] = isset($data["emailrequerautenticacao"]) && $data["emailrequerautenticacao"];
        $data["emailsenha"] = isset($data["emailsenha"]) ? customCrypt($data["emailsenha"], 8) : null;
        $data["satemitcnpjhomolog"] = onlyNumbers($data["satemitcnpjhomolog"]);
        $data["satcnpjprod"] = onlyNumbers($data["satcnpjprod"]);
        $data["satcnpjhomolog"] = onlyNumbers($data["satcnpjhomolog"]);
    }

    public function acessoMonitoramento()
    {
        $config = Configuracoesgerais::first();
        if (!is_null($config) && !is_null($config->linkmonitoramento)) {
            $linkmonitoramento = $config->linkmonitoramento;
            return view('extras.acessomonitoramento', compact('linkmonitoramento'));
        } else {
            return redirect('home')->withMessageDanger('As configurações gerais do sistema não foram cadastradas para redirecionar ao monitoramento.');
        }
    }
}
