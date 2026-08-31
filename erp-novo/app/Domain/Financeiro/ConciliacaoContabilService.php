<?php

namespace App\Domain\Financeiro;

use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * ConciliacaoContabilService (F08) — concilia o FINANCEIRO do ERP com o saldo
 * CONTÁBIL externo (CONSISA), espelhando o ConciliacaoController do legado.
 *
 * Para cada cliente, soma as parcelas em aberto/efetivadas (escopadas por tenant)
 * e compara com débito/crédito vindos da API contábil; devolve a diferença. É um
 * GATE: sem `services.consisa.url` configurado, opera em modo desabilitado (sem
 * chamar rede), de modo que a tela abre e o CI roda sem dependência externa.
 */
class ConciliacaoContabilService
{
    /** Conciliação por período. Retorna linhas por cliente com a diferença. */
    public function conciliar(int $empresaId, string $inicio, string $fim, string $tipo = 'R'): array
    {
        $financeiro = $this->somasFinanceiro($empresaId, $inicio, $fim, $tipo);

        if (! $this->habilitado()) {
            // Modo gate: devolve o lado do ERP com contábil nulo e diff = valor.
            return [
                'habilitado' => false,
                'mensagem' => 'Integração contábil (CONSISA) não configurada.',
                'linhas' => $financeiro->map(fn ($r) => [
                    'cliente_id' => $r->cliente_id,
                    'valor_financeiro' => round((float) $r->valor, 2),
                    'valor_contabil' => null,
                    'diferenca' => round((float) $r->valor, 2),
                ])->values()->all(),
            ];
        }

        $contabil = $this->saldosContabeis($empresaId, $inicio, $fim);

        $linhas = $financeiro->map(function ($r) use ($contabil, $tipo) {
            $cont = $contabil[$r->cliente_id] ?? null;
            $valorContabil = $cont === null ? null
                : (float) ($tipo === 'R' ? ($cont['debito'] ?? 0) : ($cont['credito'] ?? 0));
            $diff = round((float) $r->valor - (float) ($valorContabil ?? 0), 2);

            return [
                'cliente_id' => $r->cliente_id,
                'valor_financeiro' => round((float) $r->valor, 2),
                'valor_contabil' => $valorContabil,
                'diferenca' => $diff,
            ];
        })->values()->all();

        return ['habilitado' => true, 'mensagem' => null, 'linhas' => $linhas];
    }

    /** Soma das parcelas por cliente no período (tenant-scoped). */
    private function somasFinanceiro(int $empresaId, string $inicio, string $fim, string $tipo)
    {
        return FinanceiroParcela::query()
            ->selectRaw('financeiros.cliente_id as cliente_id, sum(financeiroparcelas.valor) as valor')
            ->join('financeiros', 'financeiros.id', '=', 'financeiroparcelas.financeiro_id')
            ->where('financeiros.empresa_id', $empresaId)
            ->where('financeiros.pagarreceber', $tipo)
            ->where('financeiros.cancelado', false)
            // whereDate, nao whereBetween: ver a nota em RelatorioService::financeiro.
            ->whereDate('financeiroparcelas.vencimento', '>=', $inicio)
            ->whereDate('financeiroparcelas.vencimento', '<=', $fim)
            ->when(true, fn (Builder $b) => $b->groupBy('financeiros.cliente_id'))
            ->get();
    }

    /** Busca saldos contábeis na CONSISA (cache curto p/ não martelar a API). */
    private function saldosContabeis(int $empresaId, string $inicio, string $fim): array
    {
        $url = rtrim((string) config('services.consisa.url'), '/');
        $chave = "consisa:{$empresaId}:{$inicio}:{$fim}";

        return Cache::remember($chave, now()->addSeconds(30), function () use ($url, $empresaId, $inicio, $fim) {
            $resp = Http::timeout(15)->acceptJson()->get("{$url}/get_contabil", [
                'empresa_id' => $empresaId,
                'inicio' => $inicio,
                'fim' => $fim,
            ]);

            if (! $resp->successful()) {
                return [];
            }

            // Espera-se [{cliente_id, debito, credito}, ...] → indexa por cliente_id.
            return collect($resp->json('data') ?? $resp->json() ?? [])
                ->keyBy('cliente_id')
                ->map(fn ($r) => ['debito' => (float) ($r['debito'] ?? 0), 'credito' => (float) ($r['credito'] ?? 0)])
                ->all();
        });
    }

    private function habilitado(): bool
    {
        return (bool) config('services.consisa.enabled') && config('services.consisa.url');
    }
}
