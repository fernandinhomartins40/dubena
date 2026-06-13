<?php

namespace App\Http\Middleware;

use Closure;

class DebugMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    /**
     * Campos que NUNCA devem ser logados (LGPD / segredos).
     * @var array
     */
    protected $sensitive = [
        'password', 'password_confirmation', 'app_key', 'token', 'access_token',
        'authorization', 'card_number', 'card_cvv', 'cvv', 'cpf', 'telefone',
        'phone', 'email', 'client_secret', 'pushregistration_id',
    ];

    public function handle($request, Closure $next)
    {
        // FASE 1 (segurança/LGPD — S4): só loga em modo debug, e NUNCA loga
        // request/response completos (continham dados pessoais, tokens e dados
        // de cartão). Em produção (APP_DEBUG=false) este middleware é no-op.
        if (! config('app.debug')) {
            return $next($request);
        }

        logger("Debug middleware -> before", [
            "method" => $request->method(),
            "path"   => $request->path(),
            "input"  => $this->scrub($request->all()),
        ]);

        return $next($request);
    }

    /**
     * Remove/oculta campos sensíveis de um array de input antes de logar.
     */
    protected function scrub(array $data)
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $this->sensitive, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }
        return $data;
    }
}
