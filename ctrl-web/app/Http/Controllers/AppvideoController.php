<?php

namespace App\Http\Controllers;

use App\Appvideo;
use Illuminate\Http\Request;
use App\Enums\AppVideoStatus;
use App\Http\Resources\ApiResources;
use App\Http\Resources\Classes\AppConfig;
use App\Jobs\ProcessAppVideo;
use App\Services\AppVideoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;

class AppvideoController extends Controller
{

    public function index()
    {
        $empresa = Session::get("empresa_padrao");

        $appvideo = Appvideo::where("empresa_id", $empresa->id)->first();

        if (!is_null($appvideo)) {
            $appvideo->status_desc = AppVideoStatus::getDesc($appvideo->status);
        }

        return view("appvideo.index", compact("appvideo"));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            "file" => ["mimetypes:video/mp4", "required", "max:30720"]
        ], [
            "file.max" => "O arquivo não pode ser maior que 30mb."
        ]);

        DB::beginTransaction();
        try {
            $empresa = Session::get("empresa_padrao");

            $appvideo = Appvideo::create([
                "grupo_id" => $empresa->grupo_id,
                "empresa_id" => $empresa->id,
                "ativo" => true,
            ]);

            $this->processFile($request, $appvideo);

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollback();

            return Redirect::back()
                ->withErrors($ex->getMessage())
                ->withInput();
        }

        return Redirect::to('appvideo.index')->withMessageSuccess("Arquivo adicionado a fila de processamento com sucesso!");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $appvideo = Appvideo::find($id);

        $this->validate($request, [
            "file" => ["mimetypes:video/mp4", "nullable", "max:30720"]
        ], [
            "file.max" => "O arquivo não pode ser maior que 30mb."
        ]);

        DB::beginTransaction();
        try {
            $data = $request->only(["ativo"]);
            $data["ativo"] = isset($data["ativo"]) && $data["ativo"];
            $hasFile = $request->hasFile("file");

            if ($hasFile) {
                $this->processFile($request, $appvideo);
            }

            if ($data["ativo"] != $appvideo->ativo && !$hasFile) {
                $appvideo->update($data);

                $this->syncApi([
                    "ativo" => $data["ativo"]
                ]);
            }

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();

            return Redirect::back()
                ->withErrors($ex->getMessage())
                ->withInput();
        }

        return Redirect::to('appvideo.index')->withMessageSuccess("Arquivo atualizado com sucesso!");
    }

    /**
     * Method to send a request to the API to sync the video
     *
     * @param array $data - Form data
     * @return json
     */
    public function syncApi($data)
    {
        $config = new AppConfig();

        $config->setConfig();

        $data["user_id"] = $config->empresa_id;

        $url = $config->api_url;

        $url = str_finish($url, '/') . "api/video/sync";

        $api = new ApiResources($url);

        $api->setAuthorizationCode($config->api_authorization);

        $response = $api->post($data, $url);

        return $response;
    }

    private function processFile($request, $appvideo)
    {
        $file = $request->file("file");

        $fileRealPath = $file->getRealPath();

        $realPath = strtolower(realpath($fileRealPath));

        $tmp = strpos($realPath, "/temp") !== 0;
        $temp = strpos($realPath, "/tmp") !== 0;

        if (!$tmp && !$temp) {
            throw new \Exception("Arquivo incorreto.");
        }

        $videoService = new AppVideoService(120);

        $duration = $videoService->getDuration($fileRealPath);

        if ($duration >= 31) {
            throw new \Exception("Duração do video não pode ser maior que 30 segundos.");
        }

        $appvideo->update([
            "titulo" => $file->getClientOriginalName(),
            "status" => AppVideoStatus::Enviado,
            "mensagem" => null,
            "ativo" => true,
        ]);

        $appStorage = Storage::disk("local");

        if (!$appStorage->exists("tmp_videos")) {
            $appStorage->makeDirectory("tmp_videos", 0755, true);
        }

        $tmpFile = $file->storeAs("tmp_videos", "tmp_startup.mp4");
        $tmpFilePath = storage_path("app/$tmpFile");

        $path = "video";

        $storage = Storage::disk("public");

        if (!$storage->exists($path)) {
            $storage->makeDirectory($path, 0755, true);
        }

        $outputPath = $storage->path("$path/startup_video.mp4");

        ProcessAppVideo::dispatch($tmpFilePath, $outputPath, $appvideo);
    }
}
