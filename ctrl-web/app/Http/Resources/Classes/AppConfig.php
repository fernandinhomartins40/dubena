<?php

namespace App\Http\Resources\Classes;

use App\Bairro;
use App\Cidade;
use App\Rua;

class AppConfig
{
    public $usesDefault;

    public $grupo_id;

    public $empresa_id;

    public $tipoPessoa_id;

    public $tipoTelefone_id;

    public $segmento_id;

    public $operacao_id;

    public $uf;

    public $cidade_id;

    public $cidadeDesc;

    public $bairro_id;

    public $bairroDesc;

    public $rua_id;

    public $ruaDesc;

    public $numero;

    public $cep;

    public $nomeEmpresaApp;

    public $telefone;

    public $tempoEntregaMin;

    public $tempoEntregaMax;

    public $horaUteis = [
        "open"  => "07:30:00",
        "close" => "21:59:00"
    ];

    public $horaSabado = [
        "open"  => "07:30:00",
        "close" => "21:59:00"
    ];

    public $horaDomingos = [
        "open"  => "08:00:00",
        "close" => "20:59:00"
    ];

    public $horaFeriado = [
        "open"  => "08:00:00",
        "close" => "20:59:00"
    ];

    public $api_url;

    public $api_authorization;

    public $keygooglemaps;

    public $apiuser_id;

    public $pedidooperacao_id;

    public $condicaoConvenio;

    public $telapermissao;

    public $setor_id;

    public $colaborador_id;

    public function __construct()
    {
        $is_prod = strtolower(env("APP_ENV")) == "production";

        $this->usesDefault = (bool) env("APP_USES_DEFAULT", true);

        if ($is_prod) {
            $this->api_url = "https://gasemcasa.com.br/api-app/public/";
        } else {
            $this->api_url = "http://qtidevel.ddns.net:8181/api-integration/public/";
            // $this->api_url = 'http://localhost/api-integration/public/';
        }

        //$this->api_url = "http://134.122.8.179/api-app/public/";
    }

    public function setConfig()
    {
        if ($this->usesDefault)
            $this->setDefaultConfig();
        else
            $this->setDBConfig();

        return $this;
    }

    private function setDefaultConfig()
    {
        $is_prod = strtolower(env("APP_ENV")) == "production";

        $this->grupo_id = 2;
        $this->empresa_id = 2;
        $this->numero = 1277;
        $this->cidade_id = 4109401;
        $this->cep = "85050-290";
        $this->bairro_id = 133;
        $this->uf = "PR";
        $this->rua_id = 406;
        $this->telefone = "(42) 36293-586";
        $this->nomeEmpresaApp = "Distribuidora Dubena";
        $this->tempoEntregaMin = 5;
        $this->tempoEntregaMax = 15;
        $this->tipoPessoa_id = 25;
        $this->segmento_id = 49;
        $this->tipoTelefone_id = 64;
        $this->apiuser_id = 2;
        $this->pedidooperacao_id = 21;
        $this->condicaoConvenio = 45;
        $this->telapermissao = "pedido";
        $this->setor_id = 103; // Produção -> 352
        $this->colaborador_id = 351; // Produção -> 351

        // ? Base testes 10.7 - DB: SGCM_API
        $tokenLocal = "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjRiZTllZDljNWUxOGUxNmZhOTQ1NGVmNzA0YmFhNWUxOGIxYTNhYzI1NmQzYzJjZjk3MjFkMDAxYTA4MTExYzRmM2YxNTE4MGNhNTUzNTA5In0.eyJhdWQiOiI1NyIsImp0aSI6IjRiZTllZDljNWUxOGUxNmZhOTQ1NGVmNzA0YmFhNWUxOGIxYTNhYzI1NmQzYzJjZjk3MjFkMDAxYTA4MTExYzRmM2YxNTE4MGNhNTUzNTA5IiwiaWF0IjoxNzY1OTcyNjA3LCJuYmYiOjE3NjU5NzI2MDcsImV4cCI6MTc5NzUwODYwNywic3ViIjoiMiIsInNjb3BlcyI6W119.H6MmQNvVnpC-lZQakLigloE0V-HMy7w611zG_4n9P6wGpnbKbd3WWhdAUu5hfe7rnsKW5MpPWwQrTsDrxOkGWq0ABiqjm0wJUaSzGj4fvaozn8B-fXwAOnC1CL694Xy8rWkOwGF3er1KB3rxtFY_eyXSNn2CvYJ6epah3LdnrgZYPV-FrTsy8S47D4uLjlt51KS5aTcQOYWDLSXms_nhDttjJg9uN6XC8XKHWRkFUNCYMcISDmhMRBP5xC9WvrxGev5P0cc9UqL38vS5rcLkMlEvKzZ5f9XboP3plxJPb_RykaUEG_YvDaUgQnWfjieg1yS8gJHrJPp2i5wctsdy-AzoGUn2Jk2Uke1465-wUP7OxdO8_WeS4RUR9U5O13o0hbYNXleEw1qabzPonOFJyNI9pbGggjaEnQu9XRm503PLUACDBTyW5EpnVKpmj8wFd070eVVBEfaA6nzA1eBg7gOOkyjTRFnOPDdR5S0z_9OeFx9m5X-GiUjatko35-SVqQ_tpxvCvNq-xKmre6Wb4Ceio5IUjD3_nn12Pa78BIf6avnQwk77BMKAu5YuJ2ue7SSmV0fUYfk-Vx9hRYHAVdqGnpSRlfBI3KtXiY_qV4DZBh1zwQ6Z4G0NWC-HTCbr2yqIX6vSByYtpP7fNwiD_nWSSlZniQ4Z56tNAhJO1AI";

        // ? Base local
        // $tokenLocal = "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjQ2ODViNTcyOWEyNTczYzE1NzA0ODcwZjdhZTY2MzI0ZmI0N2VmNDkxZWJmMjgxYzAzNWEyMmEyOTRhYjYxNzRhNzI1YWRiM2QzNmY5NTFlIn0.eyJhdWQiOiI1NSIsImp0aSI6IjQ2ODViNTcyOWEyNTczYzE1NzA0ODcwZjdhZTY2MzI0ZmI0N2VmNDkxZWJmMjgxYzAzNWEyMmEyOTRhYjYxNzRhNzI1YWRiM2QzNmY5NTFlIiwiaWF0IjoxNzM0NTE4MTIyLCJuYmYiOjE3MzQ1MTgxMjIsImV4cCI6MTc2NjA1NDEyMiwic3ViIjoiMiIsInNjb3BlcyI6W119.ia-7OXgFJL1WtXnKRAV_n2S3h_MHGzesq7111ZC661nntxS_3zAIJMoRauHLThSZdKj7UYY7QfK2b2Vgja8jRD5EB7v5aKWvKg-H68y6HnU6zUZMg_HQ4NYQe9_83YTGssZo68hP-ZG3N25-fgLp1GMxbQ-519aJsjFx84Ek8ZQ-kZWURWBimKKde7nay_QYWlWumpU9kPNzYupo-ZTQP9W_4FSlzCXlemQqbEqlAZzN467jRDBk44Bpm6yy4fk8GMCBYLnSw9qKKNsDEvoRYmI2CYl3ZwkMwH0hAo6dI7o2jQXBUoZWPuxdLjBP4vfG_Es_Jr8MFxsgCqg_kmwESmnxMk5u0zOxsCKRYuvg5me8vGWEo3AjWg6tOvqYFtMkyqDOPKhZFgy2U6la1tt22Olk2yWoonj9XpTgbRx2aQR6E_-EBMfOnurYUKNsD7EGcMCjQPjezu5sWsFrLnW0gmB1wIpQmuMSjl1n9isf6bmOwWLmS7NFkzk7ynWzSPywI23neaTAQEdg2605IeuHMeNHhxJQA_lZkCJ_BCGjwy2Rw1IoqLSrCPoftvk6L8GsaACmaawlVQzjaROwHZhObl3kP2siCJJ3dg4hHdmfI5yERqC0KW5uUXXGTtPAVApEpBzx2opNSIxIW6ZkAeRv0QJNf3j6ctEelD5mmar4FuQ";

        // Base testes 192.168.10.7 - DB: SGCM_API_BKP
        // $this->api_authorization = "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImMzYzAzNWU5NDA4Zjk0YjY1ODM2ZDkyMDVkOGFhYTc0YTZhMDk1Y2Y1OGU3NzU5ZGFjMzNhNDM4OGMyYTA1ODg3NDhlZDc3ZWQ3MGFiMTQwIn0.eyJhdWQiOiIxNjExIiwianRpIjoiYzNjMDM1ZTk0MDhmOTRiNjU4MzZkOTIwNWQ4YWFhNzRhNmEwOTVjZjU4ZTc3NTlkYWMzM2E0Mzg4YzJhMDU4ODc0OGVkNzdlZDcwYWIxNDAiLCJpYXQiOjE1ODMxNTIxODYsIm5iZiI6MTU4MzE1MjE4NiwiZXhwIjoxNjE0Njg4MTg2LCJzdWIiOiIyIiwic2NvcGVzIjpbXX0.AtRFJIcaxPiSP96s0O8mFYkF9khmx1LlLBSA6_tFz8sgtx4U5QkoORwjJKOMe3PnU7vb_q2jzvjxD5i-iO9JWAgWnCx7z8ez3lbLXSMfVaTGYm3UDjk2sdOdY3T7R3De2BEBxyuURijsWm_x7SECqRnsREWEqYYshmA3hrHD33tCJ7drKoFU4W3RfqzoRv8m-I8usDa5nDSwO8r1pXOkcNP1eenVR84Z8vKEatq7GeFQmPa6YGOPXwEVwq1i2Pb-AtkPjG4KdvVez2tQmFBaqpgkyb7O-7hbAiHhuAiqc7tVQHs-erbvwW0yP2oUWO4Xrdpv8nJrIicxysXTD-6Sn7CK-oZD4V6j6-RiL4M4sD1mxHTKtf6C_VlgaiUnSwEogOCp3dHy0mZ9H7fEZ0NywK7rF0F1NWmsc3gg2LyN1xnRUqnAoBGJnwgOFVr06HEzd0jCeul-LxU7ub5x4elZB4lSsP6f8ciAhxefA8iGM3fLdgUjGatFrD3b8ijrCySlJQZBdO5CB_l5HQNPjpdgDZKOJv_utm0s7R0sLB0Q9XB6-01S-cqcfzXDBmbB7rDTXV26RX8Um9rrENoPRR6SctjfdN-gQf7gtNnXqyEACZbMgv8RwwIG2a1NiVJiZrF6Py1XdxSs5KMuruhwEfAF4FFvJpQEibqzjYXa2rPrA10";

        // Base Produção
        // $this->api_authorization = "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImM3MTllYzc5OTQ3OTFlMjZjYWM1NDBjN2Y4ZjRhNjQ4NGExZjZiNjQ1OGE3MzM1NjJmODAxOTk0ZTRjY2FmMjk1MzY4NTk3MWJmMWRhYWE3In0.eyJhdWQiOiI5IiwianRpIjoiYzcxOWVjNzk5NDc5MWUyNmNhYzU0MGM3ZjhmNGE2NDg0YTFmNmI2NDU4YTczMzU2MmY4MDE5OTRlNGNjYWYyOTUzNjg1OTcxYmYxZGFhYTciLCJpYXQiOjE2Mzc2MTI1MTUsIm5iZiI6MTYzNzYxMjUxNSwiZXhwIjoxNjY5MTQ4NTE1LCJzdWIiOiIxIiwic2NvcGVzIjpbXX0.KC6Wv6-vrsuw48-eR7g0ydE2AtnxBqgON8psQGaDxOJYuBSuc9XWcmmItaF86kYQZfgXu29kd_AzD35pgooDqcwUVfZ9SqCn4XE7B72IQhmVQwmMpcSBjy3cW2QYU977IzAtSO3mKE7gGrV6Bbh5l2XwkLGo7UtUM-kJfSwRXdeXWF1Bp7_qOAtX8sM5norbycS4pBpo_CUOexWbMQizOPlDfMIh-WQsbyJCRVOXCzPuYeY8evUKvDPIYaAFveDDlp-JWlHsAEIL2oVFSMGWQy2RgBWpS7G6DAIYsGsbuoksS7K24wBciz8m4q6QK1hPKEk7IykyzxiJgcyDpGYqFTJ0_-zCgRgdeEHSDVnqULiCd1gzJNMLhWriN5HGpXzozdqVRDyl6oaw7tFwQ3roSEvBMHGX_9Uhfckv8ewrV-j1QnEJc5C5jLfWWi09gEV0hd02vwwu8EmvRNBCo2HQ9AY3RbL_ipopKpC2b1MZp0vo1oLam-TM2cMe93wKgBxr0nzAjAy2Z8y6cYjk1vM5jbMKdGDhl1EHqzAL4lY5sqjG-A7awPrdt-2WMnXGzDRgNR0nJAIKZ0iJy0rtCWoAHlyWDvQUBrc5D_RMTKI3pgMqRmxMFGCyKu4D5gZducfoBiGGKwgqLJsbFALL2P1yvGthC6yLt873SRnKTUw3yRM";

        // base local
        // $tokenLocal = "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImY1YTcxNjFkMDg2NGQ3ZDhjYTkwYjNhMzZhOGQwZWNiZjJiMGZmMzU2NjdjMzIwNGRlYmUzOTNhZTJkNDliNjJhNDg5YjM3ODI1MDk5MTVkIn0.eyJhdWQiOiIxNyIsImp0aSI6ImY1YTcxNjFkMDg2NGQ3ZDhjYTkwYjNhMzZhOGQwZWNiZjJiMGZmMzU2NjdjMzIwNGRlYmUzOTNhZTJkNDliNjJhNDg5YjM3ODI1MDk5MTVkIiwiaWF0IjoxNjM3NjEyOTAyLCJuYmYiOjE2Mzc2MTI5MDIsImV4cCI6MTY2OTE0ODkwMiwic3ViIjoiMiIsInNjb3BlcyI6W119.VHxkXo2tH8YsuD3GC2G5KeB8wK1K6KWHpYDeOj_GhVdrIqNHli6EF-9GfHzxntkJjdIlmW7nEUra6OX7f0JUVaU_Gn4e3oSjLerzwdikmg2WqdSFcZBHzIgj7civYZVLU-rDEpFhA33i1HqyeR5yRieIfqCCNfcE8bFlimHu_1ekg7TNlkoj7uu-sDFcwzK-4gxdZ7yeM4QifjZONjz6q7KnZBBuUpc_ZBE4vyiWL9tc2Zz645_4QdbcNNauJezK8VPVbNoXviELjAmtTEbeFO0JktdWSziG5HtZExZsi3eEvZnjYk8MsXDvpxsrR05KbAtj8YczWZoBqAoQpaL48tgglefzAr0qmxgrVLp6rU6pOQnCAJ0sOumKm8vzdauNJEDOKUfiumNSwjYNrMCU6DMPQQM6RcpWgAyShunxjHax9mkxLP5_O7ejrEXYjBrrSkuWq74rZeXydEsCiUCUpazEPx2ewP2pyunE8ZDrQCZJhxMO49tkvhQY-EHMxWKjBci3o93gkHiGoSDhZh2yb72P17xO-ruuKt1HRPW-zCwBYdJ7vD2KAQZGKI24flHdEZk6YjNJearz1vz6tqfHTYVaJKKiqEF8xKmrsC3nTUCLTRA0KzAjg8ECvZnxIw2LYNwoTAFYLfBoUfhDMhmhp3PWnbdKalGOmmgLxV8eXzU";

        //Base Digital Ocean
        $tokenProd = "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjUzYmU4YTM1ZWNlNWRlYWVmMjIxMTE3ZmZiNjYyYjU2MjQ1ZDAwZTlkN2FiYmM0MGU5NjAyMmRmMTA2ZWM1MjAzNDAzOTM3ZWFmNmEwNGFlIn0.eyJhdWQiOiIxNjEwIiwianRpIjoiNTNiZThhMzVlY2U1ZGVhZWYyMjExMTdmZmI2NjJiNTYyNDVkMDBlOWQ3YWJiYzQwZTk2MDIyZGYxMDZlYzUyMDM0MDM5MzdlYWY2YTA0YWUiLCJpYXQiOjE3NTMyMDk5OTIsIm5iZiI6MTc1MzIwOTk5MiwiZXhwIjoxNzg0NzQ1OTkyLCJzdWIiOiIyIiwic2NvcGVzIjpbXX0.SuMXNuMh8iIHjyIdHifNrtQlDinNo-ZMWNvC5oHN_QLxFbk8kwwoxqChhIkWbq_IO64LemOOIsC5XFEYmykX9fwANrm6onUyYN9G24uS5t6WJKRqyohXPVA2dNKonyDrsAh3M6YkK3SmQdxX_t6HXkll1IVbd6WBy0dFFcCmqCJCl2jZ2I4MDCoENy_WuMFbMonCgHqz68Zq6-CR5CFybntD-wpQ3S366wpapkq1urg9RbyT7X7v-RQvFMYXfMmXevK895cHZWkiJtgdMMi2gwnhYGBixgwWoFgb5wJ_vgkv261CK4URiKeY5JMspbajzWS52iz4H-v5cK-3UZGJtb5kLyPovU-vCQqLohF8fXWzzqmNkJh6RnCAC5awAZDuohqu9aRqe_oTg2Qi8ebMpOHTYgbJ-wmfWgYGcdQL_0Xt6-NrZYkhZLsZF8TswhhFLJJNfw5toHc5fdkUXaaoU2t-YNO1o8OTErrF7K891Mz810X9PasapE1u8wBiWKUGOEBt0eevgaczbmCUBLmCIi-PHGgCoQy8FVp4XuV2AYQts9suXJXoxMFu-FC5oueDLS9Fy1dIinlOACkKj0nHNdiIdvAfzOc3z7DOO2ZbOmeHruAUo1pFQHz0aIVtj94vF-BHbJ2VYT43s5x0j888kqe7Gz39u9J9BXX5S47Y6UI";

        if ($is_prod) {
            $this->api_authorization = $tokenProd;
        } else {
            $this->api_authorization = $tokenLocal;
        }

        $this->keygooglemaps = "AIzaSyBlaYqOGBuXKdrRrB8KkyqbpvOG2AlRXxs";
    }

    public function setDescriptions()
    {
        $this->cidadeDesc = Cidade::find($this->cidade_id)->descricao;
        $this->bairroDesc = Bairro::find($this->bairro_id)->descricao;
        $this->ruaDesc = Rua::find($this->rua_id)->descricao;
    }

    private function setDBConfig()
    {
        throw new \Exception("Not Implemented");
    }
}
