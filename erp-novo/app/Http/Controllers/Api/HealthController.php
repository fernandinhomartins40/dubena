<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * HealthController (P9) — readiness para balanceador/observabilidade.
 *
 * Diferente do `/up` (liveness do Laravel, só "o processo respondeu"), aqui
 * verificamos a PRONTIDÃO de fato: conexão com o banco. Não expõe segredos nem
 * detalhes internos — só o suficiente para o LB/monitor decidir rotear tráfego.
 * 200 = pronto; 503 = degradado (LB tira de rotação).
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['db' => false];
        $ok = true;

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $checks['db'] = true;
        } catch (\Throwable $e) {
            $ok = false;
        }

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'queue' => config('queue.default'),
            'broadcast' => config('broadcasting.default'),
            'time' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
