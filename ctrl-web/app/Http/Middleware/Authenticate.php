<?php

namespace App\Http\Middleware;

use Closure;
use Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // dd($request->route()->getName());
        $validado = $this->validarEmpresa($request);
        if (Auth::guard($guard)->guest()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response('Unauthorized.', 401);
            } else {                
                return redirect()->guest('login');
            }
        }
        if($validado){
            return $next($request);
        }else{
            if($request->ajax())
                return response('<br /><br />Empresa na qual a requisição foi feita é diferente da atual, por favor atualize a página. <br />');
            else
                return redirect('home')->withMessageInfo('Empresa na qual a requisição foi feita é diferente da atual, lembre-se de atualizar as páginas quando mudar de empresa.');
        }
    }

    private function validarEmpresa($request)
    {
        $empresa_sessao = Session::get('empresa_padrao');
        $empresa_submit = $request->input('empresa_documento');
        // dd($empresa_submit);
        if($this->excecoes()->has($request->route()->getName()))
            return true;

        if($empresa_sessao != null){
            if($empresa_submit != null && $empresa_sessao->id != $empresa_submit)
                return false;
        }
        
        return true;
    }

    private function excecoes()
    {
        return collect([
            0 => 'cliente.createFromPedidos/{id}'
        ]);
    }
}
