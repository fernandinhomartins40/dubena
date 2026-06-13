<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * @param Exception $exception
     * @return mixed|void
     * @throws Exception
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        //treat validations errors from api request
        if (
            $exception instanceof ValidationException &&
            str_contains($request->route()->getPrefix(), "api")
        ) {
            return responseReject($this->treatValidationException($exception->errors()));
        }
        return parent::render($request, $exception);
    }

    private function treatValidationException($errors)
    {
        $strErrors = "";
        foreach ($errors as $error) {
            $strErrors .= implode(' ', $error);
        }
        return $strErrors;
    }
}
