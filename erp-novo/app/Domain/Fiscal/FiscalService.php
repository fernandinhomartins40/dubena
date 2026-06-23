<?php

namespace App\Domain\Fiscal;

use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Shared\NumeroSequencialService;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FiscalService (N9) — orquestra a emissão fiscal. PORTA o cálculo de imposto
 * (CalculoImpostoService) e isola a SEFAZ (SefazDriver). A numeração fiscal é
 * obtida com LOCK (NumeroSequencialService do N0) — anti-duplicidade (a regra
 * crítica trataNumNF do legado).
 */
class FiscalService
{
    public function __construct(
        private CalculoImpostoService $imposto,
        private SefazDriver $sefaz,
        private NumeroSequencialService $sequencia,
    ) {}

    /**
     * Monta uma nota (RASCUNHO) a partir de um pedido, calculando os impostos por
     * item. Não numera nem transmite — isso é no emitir().
     */
    public function montarDoPedido(Pedido $pedido, ModeloDocumento $modelo): NotaFiscal
    {
        $pedido->loadMissing('itens.produto');
        if ($pedido->itens->isEmpty()) {
            throw ValidationException::withMessages(['pedido' => 'Pedido sem itens para faturar.']);
        }

        return DB::transaction(function () use ($pedido, $modelo) {
            $nota = NotaFiscal::create([
                'empresa_id' => $pedido->empresa_id,
                'grupo_id' => $pedido->grupo_id,
                'cliente_id' => $pedido->cliente_id,
                'pedido_id' => $pedido->id,
                'modelo' => $modelo->value,
                'tipo' => 'S',
                'serie' => $this->serie($pedido->empresa_id, $modelo),
                'numero' => 0, // numerado só na emissão (sob lock)
                'situacao' => SituacaoNota::RASCUNHO->value,
            ]);

            $totais = ['prod' => 0.0, 'desc' => 0.0, 'icms' => 0.0, 'ipi' => 0.0, 'pis' => 0.0, 'cofins' => 0.0];

            foreach ($pedido->itens as $i => $item) {
                $produto = $item->produto;
                $imp = $this->imposto->calcular([
                    'quantidade' => (float) $item->quantidade,
                    'valor_unitario' => (float) $item->preco_unitario,
                    'desconto' => (float) $item->desconto,
                    'cst_icms' => '00',
                    'aliq_icms' => 18.0,                 // base; config por operação entra por extensão
                    'aliq_pis' => 1.65,
                    'aliq_cofins' => 7.6,
                    'aliq_ipi' => (float) ($produto->nfe_aliq_ipi ?? 0),
                ]);

                $valorTotal = round((float) $item->quantidade * (float) $item->preco_unitario - (float) $item->desconto, 2);
                $nota->itens()->create(array_merge([
                    'produto_id' => $produto->id,
                    'numero_item' => $i + 1,
                    'quantidade' => $item->quantidade,
                    'valor_unitario' => $item->preco_unitario,
                    'valor_total' => $valorTotal,
                    'desconto' => $item->desconto,
                    'cfop' => '5102',
                    'cst_icms' => '00',
                ], $imp->toArray()));

                $totais['prod'] += $valorTotal;
                $totais['desc'] += (float) $item->desconto;
                $totais['icms'] += $imp->valorIcms;
                $totais['ipi'] += $imp->valorIpi;
                $totais['pis'] += $imp->valorPis;
                $totais['cofins'] += $imp->valorCofins;
            }

            $nota->update([
                'valor_produtos' => round($totais['prod'], 2),
                'valor_desconto' => round($totais['desc'], 2),
                'valor_icms' => round($totais['icms'], 2),
                'valor_ipi' => round($totais['ipi'], 2),
                'valor_pis' => round($totais['pis'], 2),
                'valor_cofins' => round($totais['cofins'], 2),
                'valor_total' => round($totais['prod'] + $totais['ipi'], 2),
            ]);

            return $nota->refresh()->load('itens');
        });
    }

    /**
     * Emite a nota: NUMERA sob lock (anti-duplicidade) e transmite via SefazDriver.
     */
    public function emitir(NotaFiscal $nota): NotaFiscal
    {
        if ($nota->situacao !== SituacaoNota::RASCUNHO) {
            throw ValidationException::withMessages(['nota' => 'Só rascunho pode ser emitido.']);
        }

        return DB::transaction(function () use ($nota) {
            // Numeração sequencial COM LOCK por empresa+modelo+série.
            $chaveSeq = $nota->modelo->chaveSequencia($nota->empresa_id, $nota->serie);
            $numero = $this->sequencia->proximo($chaveSeq);

            $nota->update([
                'numero' => $numero,
                'situacao' => SituacaoNota::EMITIDA->value,
                'emitida_em' => now(),
            ]);

            $resultado = $this->sefaz->transmitir($nota->refresh());

            if ($resultado['autorizada']) {
                $nota->update([
                    'situacao' => SituacaoNota::AUTORIZADA->value,
                    'chave' => $resultado['chave'],
                    'protocolo' => $resultado['protocolo'],
                ]);
            } else {
                $nota->update([
                    'situacao' => SituacaoNota::REJEITADA->value,
                    'motivo_rejeicao' => $resultado['motivo'],
                ]);
            }

            return $nota->refresh();
        });
    }

    /** Emite direto a partir de um pedido (monta + emite). */
    public function emitirDoPedido(Pedido $pedido, ModeloDocumento $modelo): NotaFiscal
    {
        return $this->emitir($this->montarDoPedido($pedido, $modelo));
    }

    public function cancelar(NotaFiscal $nota, string $justificativa): NotaFiscal
    {
        if (! $nota->situacao->podeCancelar()) {
            throw ValidationException::withMessages(['nota' => 'Só nota autorizada pode ser cancelada.']);
        }
        if (mb_strlen($justificativa) < 15) {
            throw ValidationException::withMessages(['justificativa' => 'Justificativa deve ter ao menos 15 caracteres (regra SEFAZ).']);
        }

        $resultado = $this->sefaz->cancelar($nota, $justificativa);
        if ($resultado['cancelada']) {
            $nota->update(['situacao' => SituacaoNota::CANCELADA->value]);
        }

        return $nota->refresh();
    }

    private function serie(int $empresaId, ModeloDocumento $modelo): int
    {
        $config = ConfigFiscal::query()->where('empresa_id', $empresaId)->first();
        if (! $config) {
            return 1;
        }

        return $modelo === ModeloDocumento::NFCE ? $config->serie_nfce : $config->serie_nfe;
    }
}
