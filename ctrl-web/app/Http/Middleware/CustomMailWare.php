<?php

namespace App\Http\Middleware;

use Config;
use Session;
use Closure;

class CustomMailWare
{
    /**
     * @param $request
     * @param Closure $next
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\RedirectResponse|mixed|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function handle($request, Closure $next)
    {
        $config = $this->setMailConfig($request);

        if (is_bool($config)) {
            $msg = 'Por favor, faça o cadastro das informações do e-mail nas Configurações da Empresa.';
            if ($request->ajax() || $request->wantsJson()) {
                return response('<br /><br />' . $msg);
            } else {
                return redirect()->back()->withInput()->withErrors($msg);
            }
        } else if (is_string($config)) {
            $msg = 'Porta incorreta, as portas suportadas são: 465 e 587';
            if ($request->ajax() || $request->wantsJson()) {
                return response('<br /><br />' . $msg);
            } else {
                return redirect()->back()->withInput()->withErrors($msg);
            }
        }

        Config::set('mail', $config);

        return $next($request);
    }

    /**
     * @param $request
     * @param bool $test
     * @return array|bool|object|string
     * @throws \Exception
     */
    protected function setMailConfig($request, $test = true)
    {
        return setMailConfig($request, $test);
    }
}
// 995 465