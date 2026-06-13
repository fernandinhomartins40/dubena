<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralConfigRequest;
use App\Repository\GeneralConfigRepository;
use Exception;

class GeneralConfigController extends Controller
{

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $config = GeneralConfigRepository::first();
        if (! $config) {
            $config = collect([]);
        }
        $pageTitle = "Configurações Gerais da API";
        return view('config.index', compact('config', 'pageTitle'));
    }

    /**
     * @param GeneralConfigRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(GeneralConfigRequest $request)
    {
        try {
            $data = $this->validateData($request);
            $configs = GeneralConfigRepository::updateOrCreate($data);
            return responseSuccess($configs, "Configurações atualizadas com sucesso!");
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param GeneralConfigRequest $request
     * @return array
     * @throws Exception
     */
    private function validateData(GeneralConfigRequest $request)
    {
        $data = $request->only('keygooglemaps');

        return strNullToNullValue($data);
    }

}
