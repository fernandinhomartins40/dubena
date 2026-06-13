<?php

namespace App\Api\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Api\Resources\ApiResources;
use App\Api\Models\OauthClient;
use Exception;
use Illuminate\Http\Request;

class OauthClientController extends Controller
{
    /**
     * @param $user
     * @param $password
     * @return OauthClient
     * @throws Exception
     */
    public function store(&$user, $password)
    {
        try {
            $client = new Oauthclient();
            $client->user_id = $user->id;
            $client->name = $user->email;
            $client->secret = hashClientSecret($password);
            $client->password_client = 1;
            $client->personal_access_client = 0;
            $client->redirect = 'null';
            $client->revoked = 0;
            $client->save();

            $user->load("oauth");

            return $client;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $user
     * @param $oauthClient
     * @param $password
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAuthorizationToken($user, $oauthClient, $password)
    {
        //TODO tem que alterar pq com url externa dá B.O.
        $url = url("/") . "/oauth/";
        $api = new ApiResources($url);
        $api->setMethod("POST");
        $api->setHeader([
            'content_type' => 'application/x-www-form-urlencoded'
        ]);

        return $api->request([
            'username'      => $user->email,
            'password'      => $password,
            'client_secret' => $oauthClient->secret,
            'scope'         => '',
            'grant_type'    => 'password',
            'client_id'     => $oauthClient->id
        ], "token");
    }

    /**
     * @param $user_id
     * @return bool|mixed|null
     * @throws Exception
     */
    public function exclude($user_id)
    {
        try {
            return Oauthclient::whereUserId($user_id)->delete();
        } catch (Exception $e) {
            throw $e;
        }
    }
}

