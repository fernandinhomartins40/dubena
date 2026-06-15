<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Appnotification;
use App\Enums\AppNotificationStatus;
use Illuminate\Http\Request;
use App\Http\Resources\ApiResources;
use App\Http\Resources\Classes\AppConfig;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class AppnotificationController extends Controller
{

    private $rules = [
        "fcmtitle"  => "required",
        "fcmbody"   => "required",
        "file"      => "nullable|image|max:1024"
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Appnotification $app)
    {
        $this->authorize("view", $app);
        $tipo = request()->get("tipo", -1);

        $empresa_id = Session::get("empresa_padrao")->id;

        $notificacoes = Appnotification::where("empresa_id", $empresa_id)
            ->select(
                DB::raw("'Notificação: ' || to_char(created_at, 'DD/MM/YYYY HH24:MI:SS') as descricao"),
                "id",
                "fcmtitle",
                "fcmbody",
                "status",
                "islayout"
            );

        if ($tipo == 1) {
            $notificacoes = $notificacoes->where("islayout", 1);
        } else if ($tipo == 2) {
            $notificacoes = $notificacoes->where("islayout", 0);
        }

        $notificacoes = $notificacoes->get();

        $tipos = [
            -1 => "Todos",
            1 => "Somente Layout",
            2 => "Somente Notificação",
        ];

        return view("notificacao.index", compact("notificacoes", "tipos"));
    }

    /**
     * Show the current resource.
     *
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($id)
    {
        $notificacao = Appnotification::find($id);

        $this->authorize("view", $notificacao);

        $show = true;

        return view("notificacao.form", compact("notificacao", "show"));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Appnotification $noti)
    {
        $this->authorize("create", $noti);

        return view("notificacao.form");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Appnotification $app)
    {
        $this->authorize("create", $app);

        $this->validate($request, $this->rules);

        DB::beginTransaction();
        try {
            $this->verifyPendentes();

            $data = $request->all();

            $data = $this->dadosExtras($data);

            $notificacao = Appnotification::create($data);

            if ($request->hasFile("file")) {
                $this->saveImage($request, $notificacao);
            }

            if ($notificacao->instant && !$notificacao->islayout) {
                $payload = [
                    "notification_id"   => $notificacao->id,
                    "title"             => $notificacao->fcmtitle,
                    "body"              => $notificacao->fcmbody,
                    "imagem"            => is_null($notificacao->imagem) ? null : asset($notificacao->imagem),
                ];

                $this->enviarNotificacao($payload);

                $notificacao->update(["status" => AppNotificationStatus::Enviando]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::back()
                ->withErrors($e->getMessage())
                ->withInput();
        }

        return Redirect::route("appnotification.index")->withMessageSuccess("Notificação salva com sucesso!");
    }

    /**
     * Show the form for editing a resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $notificacao = Appnotification::find($id);

        $this->authorize("update", $notificacao);

        return view("notificacao.form", compact("notificacao"));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Appnotification $noti)
    {
        $this->authorize('update', $noti);

        $this->validate($request, $this->rules);

        $notificacao = Appnotification::find($id);

        DB::beginTransaction();
        try {
            // formulário/validação usam fcmtitle/fcmbody (ver $this->rules e store);
            // "title"/"body" não chegam no request → update gravava sem título/corpo.
            $data = $request->only(["fcmtitle", "fcmbody", "instant", "islayout", "img-remove"]);

            $data = $this->dadosExtras($data, true);

            $hasImg = $request->hasFile("file");

            $storage = Storage::disk("public");

            if ($notificacao->status != "pendente" && $notificacao->status != "layout") {
                throw new \Exception("Somente notificações pendentes não podem ser modificadas.");
            }

            if (($hasImg || $data["img-remove"]) && !is_null($notificacao->imagem)) {
                $storage->delete(str_replace("/storage", "", $notificacao->imagem));
                $data["imagem"] = null;
            }

            $notificacao->update($data);

            if ($hasImg) {
                $this->saveImage($request, $notificacao);
            }

            if ($notificacao->instant && !$notificacao->islayout) {
                $payload = [
                    "notification_id"   => $notificacao->id,
                    "title"             => $notificacao->fcmtitle,
                    "body"              => $notificacao->fcmbody,
                    "imagem"            => is_null($notificacao->imagem) ? null : asset($notificacao->imagem),
                ];

                $this->enviarNotificacao($payload);

                $notificacao->update(["status" => AppNotificationStatus::Enviando]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::back()
                ->withErrors($e->getMessage())
                ->withInput();
        }

        return Redirect::route("appnotification.index")->withMessageSuccess("Notificação atualizada com sucesso!");
    }

    public function massReturn(Request $request, $notification_id)
    {
        $notificacao = Appnotification::find($notification_id);
        DB::beginTransaction();
        try {
            $data = $request->all();
            $status = "";

            if (isset($data["error"]) && $data["error"]) {
                $status = $data["error_msg"];
            } else {
                $return = (object) $data["returnObj"];
                $status = $this->mapStatus((object) $return->fcm_response);
            }

            $notificacao->update(["status" => str_limit($status, 45)]);

            DB::commit();
        } catch (\Exception $e) {
            $notificacao->update(["status" => "error"]);

            info("Error ao processar retorno: " . $e->getMessage());

            DB::commit();
        }

        return responseSuccess();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $notificacao = Appnotification::find($id);

        $this->authorize('delete', $notificacao);

        DB::beginTransaction();
        try {
            $storage = Storage::disk("public");
            if (!is_null($notificacao->imagem)) {
                $storage->deleteDirectory("/img/notificacoes/{$notificacao->id}/");
            }

            $notificacao->delete();

            DB::commit();

            return 'OK|';
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
    }

    public function sendNotification($id)
    {
        $notificacao = Appnotification::find($id);

        try {
            $payload = [
                "notification_id"   => $notificacao->id,
                "title"             => $notificacao->fcmtitle,
                "body"              => $notificacao->fcmbody,
                "imagem"            => is_null($notificacao->imagem) ? null : asset($notificacao->imagem),
            ];

            $this->enviarNotificacao($payload);

            $notificacao->update(["status" => AppNotificationStatus::Enviando]);
        } catch (\Exception $ex) {
            return response("Erro ao enviar: {$ex->getMessage()}", 400);
        }
        return response("Sucesso");
    }

    private function dadosExtras($data, $isUpdate = false)
    {
        $data["islayout"] = isset($data["islayout"]) && $data["islayout"];

        if (!$isUpdate) {
            $data["grupo_id"] = Session::get("empresa_padrao")->grupo_id;
            $data["empresa_id"] = Session::get("empresa_padrao")->id;
            $data["status"] = $data["islayout"] ? "layout" : "pendente";
        }

        $data["instant"] = isset($data["instant"]) && $data["instant"];

        return $data;
    }

    public function enviarNotificacao($data)
    {
        $config = new AppConfig();

        $config->setConfig();

        $data["user_id"] = $config->empresa_id;

        $url = $config->api_url;

        $url = str_finish($url, '/') . "api/sendNotification";

        $api = new ApiResources($url);

        $api->setAuthorizationCode($config->api_authorization);

        $response = $api->post($data, $url);

        return $response;
    }

    public function enviarNotificacaoCupom($data)
    {
        $config = new AppConfig();

        $config->setConfig();

        $data["user_id"] = $config->empresa_id;

        $url = $config->api_url;

        $url = str_finish($url, '/') . "api/sendNotificationCoupon";

        $api = new ApiResources($url);

        $api->setAuthorizationCode($config->api_authorization);

        $response = $api->post($data, $url);

        return $response;
    }

    public function mapStatus($fcm)
    {
        $status = "Sucesso: $fcm->success";

        if ($fcm->failure > 0) $status .= " Falha: $fcm->failure";

        return $status;
    }

    private function verifyPendentes()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $exists = Appnotification::where("empresa_id", $empresa_id)
            ->where(function ($qry) {
                $qry->where("status", AppNotificationStatus::Pendente);
                $qry->orWhere("status", AppNotificationStatus::Enviando);
            })
            ->exists();

        if ($exists) {
            throw new \Exception("Já existe uma notificação com status de pendente ou enviando.");
        }
    }

    private function saveImage($request, $notificacao)
    {
        $path = "img/notificacoes/{$notificacao->id}";
        $storage = Storage::disk("public");
        $foto = $request->file("file");
        $fileName = "notification-img." . $foto->getClientOriginalExtension();

        $encoded = Image::make($foto)->encode("png");

        // dd(asset(Storage::url("app/img/$path/$fileName")), $storage->path(""));
        $storage->put("$path/$fileName", $encoded);

        $notificacao->imagem = Storage::url("$path/$fileName");

        $notificacao->save();
    }
}
