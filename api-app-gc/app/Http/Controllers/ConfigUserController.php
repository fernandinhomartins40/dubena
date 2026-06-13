<?php

namespace App\Http\Controllers;

use App\ConfigUser;
use Auth;
use Illuminate\Http\Request;
use PHPUnit\Runner\Exception;

class ConfigUserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $config = ConfigUser::all();

            return response()->json(['configs' => $config], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 403);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $data = $request->all();

            $config = ConfigUser::create([
                'empresa_id' => $data['empresa_id'],
                'enderecoip' => $data['enderecoip'],
                'user_id' => 1,
            ]);

            return response()->json([
                'config'  => $config,
                'message' => 'Success'
            ], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 403);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $data = $request->all();
            $config = ConfigUser::find($id);
            $config->update([
                $data['empresa_id'],
                $data['enderecoip']
            ]);

            return response()->json([
                'message' => 'Success'
            ], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 403);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $config = ConfigUser::find($id);
        $config->delete();
        return response()->json([
            'message' => 'Task deleted successfully!'
        ], 403);
    }
}
