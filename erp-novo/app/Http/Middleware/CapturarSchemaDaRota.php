<?php

namespace App\Http\Middleware;

use App\Domain\Contrato\ColetorDeSchema;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F2-01 — registra a forma da RESPOSTA de cada rota, quando a captura está
 * ligada (só nos testes; ver `ColetorDeSchema`).
 *
 * Fica num middleware porque é o único ponto por onde toda resposta passa,
 * independentemente de o controller devolver Resource, array ou JsonResponse.
 * Instrumentar os controllers exigiria tocar em centenas de retornos e ainda
 * assim esqueceria os que forem escritos depois.
 *
 * Fora dos testes ele sai na primeira linha: o custo em produção é uma
 * comparação de booleano por requisição.
 */
class CapturarSchemaDaRota
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        if (! ColetorDeSchema::ligado() || ! $resposta instanceof JsonResponse) {
            return $resposta;
        }

        $corpo = $resposta->getData(true);

        if (is_array($corpo)) {
            ColetorDeSchema::registrarResponse($corpo, $resposta->getStatusCode());
        }

        return $resposta;
    }
}
