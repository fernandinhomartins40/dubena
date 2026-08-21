<?php

namespace App\Http\Middleware;

use App\Models\RequisicaoIdempotente;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F7 — a mesma requisição, reenviada, não acontece duas vezes.
 *
 * O app em rota guarda a ação numa fila local quando não há sinal e reenvia
 * depois. Se a primeira tentativa chegou ao servidor mas a resposta se perdeu, o
 * reenvio criaria um segundo pedido ou baixaria o estoque em dobro. Com
 * `Idempotency-Key` (uuid gerado no dispositivo, estável entre tentativas), a
 * segunda vez devolve o que a primeira respondeu, sem reexecutar.
 *
 * **Sem a chave, passa direto.** O cabeçalho é opt-in: rotas do app antigo e da
 * SPA seguem funcionando sem mudança. Quem quer garantia, manda a chave.
 *
 * **Recusas deliberadas:**
 *  - mesma chave com payload diferente → 409. É bug do cliente, e devolver a
 *    resposta antiga esconderia a divergência.
 *  - chave ainda em execução → 409. Duas tentativas simultâneas (a rede voltou
 *    no meio do retry) não podem rodar as duas.
 *
 * **Só respostas de sucesso são memorizadas.** Um 500 por indisponibilidade
 * momentânea deve poder ser tentado de novo; congelar o erro deixaria o app
 * preso nele para sempre.
 */
class Idempotente
{
    /** Prazo de guarda da resposta. O app desiste muito antes disso. */
    private const HORAS_VALIDADE = 48;

    public function handle(Request $request, Closure $next): Response
    {
        $chave = trim((string) $request->header('Idempotency-Key', ''));

        if ($chave === '' || ! $request->isMethod('POST')) {
            return $next($request);
        }

        $empresaId = (int) ($request->user()?->empresa_id ?? 0);
        if ($empresaId === 0) {
            return $next($request);
        }

        $hash = hash('sha256', $request->getPathInfo().'|'.json_encode($request->except(['_token'])));

        // `withoutTenant()`: o middleware roda antes de o tenant estar resolvido
        // em algumas rotas, e o escopo global esconderia o registro — o SELECT
        // não acharia nada e o INSERT bateria no unique (empresa_id, chave),
        // devolvendo 409 para uma chave que era de OUTRA empresa. O filtro por
        // empresa_id continua explícito logo abaixo, então nada vaza.
        $registro = RequisicaoIdempotente::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->where('chave', $chave)
            ->first();

        if ($registro !== null) {
            if ($registro->payload_hash !== $hash) {
                return response()->json([
                    'message' => 'Esta chave de idempotência já foi usada com outro conteúdo.',
                ], 409);
            }

            if (! $registro->concluida) {
                return response()->json([
                    'message' => 'Requisição ainda em processamento. Tente novamente em instantes.',
                ], 409);
            }

            // Replay: devolve o que a primeira execução respondeu.
            return response()->json($registro->resposta, (int) ($registro->status_http ?? 200));
        }

        // Reserva a chave ANTES de executar. O unique (empresa_id, chave) é o que
        // resolve a corrida: se duas tentativas chegam juntas, uma insere e a
        // outra falha aqui e cai no caminho de "em processamento".
        try {
            $registro = RequisicaoIdempotente::create([
                'empresa_id' => $empresaId,
                'user_id' => $request->user()?->id,
                'chave' => $chave,
                'rota' => substr($request->getPathInfo(), 0, 190),
                'metodo' => $request->method(),
                'payload_hash' => $hash,
                'concluida' => false,
                'expira_em' => now()->addHours(self::HORAS_VALIDADE),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Requisição ainda em processamento. Tente novamente em instantes.',
            ], 409);
        }

        $response = $next($request);

        $status = $response->getStatusCode();

        if ($response instanceof JsonResponse && $status >= 200 && $status < 300) {
            $registro->forceFill([
                'status_http' => $status,
                'resposta' => $response->getData(true),
                'concluida' => true,
            ])->save();
        } else {
            // Falhou: solta a chave para que o app possa tentar de novo.
            $registro->delete();
        }

        return $response;
    }
}
