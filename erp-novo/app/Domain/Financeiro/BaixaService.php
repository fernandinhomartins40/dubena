<?php

namespace App\Domain\Financeiro;

use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Porta unica para a transicao de uma parcela entre aberta e baixada — **nos dois
 * sentidos** (F5-02).
 *
 * A baixa ja entrava so por aqui. O **estorno nao**: `CaixaService::estornar`
 * reabria a parcela escrevendo `baixado => false` direto no model, e nessa
 * escrita faltava a verificacao de empresa que a baixa faz. A unica protecao era
 * o global scope de tenant — que existe, mas nao vale em job, comando de console
 * nem contexto de suporte, justamente onde um estorno em lote roda.
 *
 * Ter duas portas para o mesmo estado tambem significa que qualquer regra nova
 * (registrar quem estornou, recusar estorno de parcela agrupada) precisaria ser
 * escrita duas vezes — e a segunda seria esquecida.
 */
final class BaixaService
{
    public function baixar(
        int $parcelaId,
        int $empresaId,
        float $valorEfetivado,
        string $origem,
        bool $reentregaIdempotente = false,
    ): bool {
        if ($empresaId <= 0 || $parcelaId <= 0 || ! is_finite($valorEfetivado) || $valorEfetivado < 0 || trim($origem) === '') {
            throw ValidationException::withMessages([
                'baixa' => 'Parcela, empresa, valor e origem validos sao obrigatorios.',
            ]);
        }

        $parcela = FinanceiroParcela::withoutTenant()
            ->whereKey($parcelaId)
            ->where('empresa_id', $empresaId)
            ->lockForUpdate()
            ->first();
        if (! $parcela) {
            throw ValidationException::withMessages([
                'parcela' => 'Parcela nao pertence a empresa da operacao.',
            ]);
        }

        if ($parcela->baixado) {
            if (! $reentregaIdempotente) {
                throw ValidationException::withMessages(['parcela' => 'Parcela ja baixada.']);
            }

            Log::warning('Baixa externa recebida para parcela ja baixada.', [
                'empresa_id' => $empresaId,
                'financeiroparcela_id' => $parcelaId,
                'origem' => $origem,
            ]);

            return false;
        }

        $parcela->update([
            'baixado' => true,
            'valor_efetivado' => round($valorEfetivado, 2),
            'datahora_baixa' => now(),
        ]);

        return true;
    }

    /**
     * Reabre uma parcela baixada — o caminho de volta.
     *
     * Mesma verificacao de empresa e mesmo `lockForUpdate` da baixa: reabrir e
     * uma decisao sobre dinheiro tanto quanto baixar, e um id de outra revenda
     * nao pode reabrir titulo alheio.
     *
     * Idempotente por natureza: reabrir o que ja esta aberto devolve `false` sem
     * erro. Um estorno reprocessado (a fila reentrega, o operador clica duas
     * vezes) nao deve explodir — a parcela ja esta no estado desejado.
     */
    public function reabrir(int $parcelaId, int $empresaId, string $origem): bool
    {
        if ($empresaId <= 0 || $parcelaId <= 0 || trim($origem) === '') {
            throw ValidationException::withMessages([
                'reabertura' => 'Parcela, empresa e origem validas sao obrigatorias.',
            ]);
        }

        $parcela = FinanceiroParcela::withoutTenant()
            ->whereKey($parcelaId)
            ->where('empresa_id', $empresaId)
            ->lockForUpdate()
            ->first();

        if (! $parcela) {
            throw ValidationException::withMessages([
                'parcela' => 'Parcela nao pertence a empresa da operacao.',
            ]);
        }

        if (! $parcela->baixado) {
            return false;
        }

        $parcela->update([
            'baixado' => false,
            'valor_efetivado' => 0,
            'datahora_baixa' => null,
        ]);

        Log::info('Parcela reaberta.', [
            'empresa_id' => $empresaId,
            'financeiroparcela_id' => $parcelaId,
            'origem' => $origem,
        ]);

        return true;
    }
}
