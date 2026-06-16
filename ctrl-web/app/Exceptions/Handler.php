<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\MessageBag;
use League\OAuth2\Server\Exception\OAuthServerException;
use Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\Access\AuthorizationException as AuthException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException as NotFound;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        OAuthServerException::class,
    ];

    /**
     * @param Throwable $e
     * @throws Throwable
     */
    public function report(Throwable $e)
    {
        parent::report($e);
    }

    /**
     * @param Request $request
     * @param Exception $e
     * @return ResponseFactory|RedirectResponse|\Illuminate\Http\Response|Redirector|\Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        if($e instanceof TokenMismatchException){
            if ($request->ajax())
                return response("<br /><br />&nbsp;Seu Token expirou, isso pode ser causado por enviar o formulário mais de uma vez ou ficar ocioso por muito tempo!");
            $error = new MessageBag(["error"=>"Seu Token expirou, isso pode ser causado por enviar o formulário mais de uma vez ou ficar ocioso por muito tempo!"]);
            return redirect('/home')->withErrors($error);

        }else if ($e instanceof AuthException){
            $mensagem = $this->getMessages($request);
            if($request->ajax())
                return response("<br /><br />$mensagem");

            $error = new MessageBag(["error"=>$mensagem]);
            return redirect('/home')->withErrors($error);
        }else if($e instanceof NotFound){
            return Response::view('layouts.404', [], 404);
        } else if ($e instanceof OAuthServerException) {
            return response()->json([
                "data" => null,
                "status" => "NOK",
                "message" => $e->getMessage()
            ]);
        }

        if (starts_with(request()->path(), 'api')) {
            return responseError($e->getMessage(), 400);
        }

        return parent::render($request, $e);
    }

    private function getMessages(Request $request)
    {
        $rota = $request->route()->getName();
        $padrao = "Você não foi autorizado a realizar esta ação!";
        $messages = $this->messages();

        $mensagem = $messages->has($rota) ? $messages->get($rota) : $padrao;

        return $mensagem;
    }

    private function messages()
    {
        return collect([
            "empresaconfig.senhamestre" => "Você precisa ter permissão de visualizar, criar e editar para acessar está página.",
            "financeiro.createDespesa" => "Você precisa ter permissão de visualizar e criar para acessar está página.",
            "financeiro.createReceita" => "Você precisa ter permissão de visualizar e criar para acessar está página.",
        ]);
    }
}
