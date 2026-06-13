<?php

namespace App\Api\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Api\Models\Video;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VideoController extends Controller
{

    public function sync(Request $request)
    {
        $data = $request->all();

        $video = Video::where("user_id", $data["user_id"])
            ->first();

        if (is_null($video)) {
            $video = Video::create($data);
        } else {
            $video->update($data);
        }

        return responseSuccess();
    }

    public function get()
    {
        $video = Video::where("ativo", true)
            ->select("url", "titulo", "updated_at")
            ->first();

        if (!is_null($video)) {
            $updated_at = Carbon::createFromFormat("Y-m-d H:i:s", $video->updated_at)->getTimestamp();
            $video->updated = $updated_at;
            unset($video->updated_at);
        }

        return responseSuccess($video);
    }
}

