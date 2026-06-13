<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResources;
use App\Repository\UserRepository;
use App\User;
use Auth;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Input;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @var array
     */
    protected $paramsLink = ["linked", "user_id"];

    /**
     * @var Model
     */
    protected $user;

    /**
     * @var bool
     */
    protected $usingWs = false;

    /**
     * Controller constructor.
     * @param bool $ws
     * @throws Exception
     */
    public function __construct($ws = false)
    {
        $this->usingWs = $ws;
        if (! $ws) {
            $user_id = Input::get("user_id", null);
            if (! $user_id) {
                $user_id = Input::get("revenda_id", null);
            }

            if ($user_id) {
                $this->user = UserRepository::getLinked($user_id);
            } else {
                if (Auth::check()) {
                    $this->user = UserRepository::findOrFail(env("DEFAULT_USER_ID"), "Usuário de autenticação");
                }
            }
        }
    }

    /**
     * @param array $news
     */
    protected function addParamsLink(array $news)
    {
        $this->paramsLink = array_merge($this->paramsLink, $news);
    }

    /**
     * @param User $user
     * @param string $orig
     * @param string $information
     * @param array $extraParams
     * @return Collection|mixed
     * @throws GuzzleException
     * @throws Exception
     */
    protected function linkTo(?User $user, string $orig, string $information, array $extraParams = [])
    {
        if (! $user) {
            throw new Exception("Dados não vinculados a nenhum usuário!");
        }

        $baseUri = str_finish($user->erpurl, '/') . "api/";

        $api = new ApiResources($baseUri, $user);

        $formParams = array_merge([
            "user_id"   => $user->serviceuser_id,
            "results"   => $orig
        ], $extraParams);

        return $api->link($formParams, $information);
    }

    /**
     * @param $condition
     * @param $message
     * @param int $code
     * @throws Exception
     */
    protected function throwIf($condition, $message, $code = 0)
    {
        if ($condition) {
            throw new Exception($message, $code);
        }
    }
}
