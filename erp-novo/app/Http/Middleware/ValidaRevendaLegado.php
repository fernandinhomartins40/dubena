<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F0 — aceita o `revenda_id` do app legado, mas NÃO confia nele.
 *
 * O legado faz `Empresa::find($data['revenda_id'])` sem verificar se o token
 * pertence àquela empresa (ApiController:34, :71, :796). Trocar o parâmetro
 * alcança dados de outra revenda — IDOR de tenant.
 *
 * Aqui o tenant continua saindo do token (ResolveTenant:31, com RLS por baixo).
 * O `revenda_id` é apenas CONFERIDO: se vier e divergir, a requisição para. Não
 * ignoramos em silêncio porque divergência é sintoma — ou o app está mal
 * configurado, ou alguém está sondando outra empresa; nos dois casos o operador
 * precisa saber, e o log registra.
 *
 * Aceitar o parâmetro (em vez de exigir que o app pare de mandar) é o que
 * permite apontar os APKs em campo para o erp-novo sem republicar em loja — o
 * MovelApp nem publica hoje, com targetSdk 28.
 */
class ValidaRevendaLegado
{
    public function handle(Request $request, Closure $next): Response
    {
        $informado = $request->input('revenda_id');

        if ($informado !== null && $informado !== '') {
            $doToken = (int) ($request->user()?->empresa_id ?? 0);

            if ((int) $informado !== $doToken) {
                \Illuminate\Support\Facades\Log::warning('legado: revenda_id divergente do token', [
                    'revenda_id_informado' => (int) $informado,
                    'empresa_do_token' => $doToken,
                    'user_id' => $request->user()?->id,
                    'ip' => $request->ip(),
                    'rota' => $request->path(),
                ]);

                // 403 aqui vira {status:"NOK"} com HTTP 200 pelo DialetoLegado —
                // o app mostra a mensagem em vez de quebrar.
                return response()->json([
                    'message' => 'Revenda informada não confere com o acesso. Registre o aplicativo novamente.',
                ], 403);
            }
        }

        return $next($request);
    }
}
