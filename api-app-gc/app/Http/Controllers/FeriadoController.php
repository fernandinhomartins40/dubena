<?php

namespace App\Http\Controllers;

use App\{Http\Requests\FeriadoRequest,
    Repository\FeriadoRepository,
    Repository\UserRepository as User,
    Repository\FeriadoRepository as Feriado};
use Auth;
use DB;
use Exception;
use Input;

class FeriadoController extends Controller
{

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function getToLink()
    {
        try {
            return responseSuccess(FeriadoRepository::byUser($this->user->id));
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param FeriadoRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function store(FeriadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $this->validateData($request);
            if ($request->get("_method_") === "POST") {
                $holiday = FeriadoRepository::create($data);
            } else {
                $id = $request->get('id');

                if (! $id) {
                    throw new Exception("Código não encontrado na base de dados.");
                }

                $holiday = FeriadoRepository::findOrFail($id);

                $holiday->update($data);
            }

            DB::commit();

            return responseSuccess($holiday, 'Feriado criado com sucesso!');
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @param FeriadoRequest $request
     * @return array
     */
    private function validateData(FeriadoRequest $request)
    {
        $data = $request->only($this->getFieldsStoreUp());

        $data["user_id"] = Auth::user()->id;

        return strNullToNullValue($data);
    }

    /**
     * @return array
     */
    private function getFieldsStoreUp()
    {
        return array_flatten(FeriadoRepository::getFillable());
    }
}
