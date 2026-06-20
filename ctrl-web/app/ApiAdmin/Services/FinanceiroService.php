<?php

namespace App\ApiAdmin\Services;

use App\Processors\financeiroProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * F6 — adaptador entre a API admin (payload JSON limpo do React) e o
 * financeiroProcessor legado (que espera Request com datas d/m/Y, valor BR e
 * parcelas/rateios em JSON). PRESERVA a lógica do motor (parcelas/rateio/baixa).
 * Caracterizado por FinanceiroProcessorBaselineTest.
 */
class FinanceiroService
{
    /**
     * Grava um lançamento financeiro.
     *
     * @param array $data payload limpo: cliente_id, pagarreceber(P/R), descricao,
     *   documento, valor(float), datacompetencia/dataemissao/datavencimento (Y-m-d),
     *   planoconta_id, centrocusto_id, condicaopagamento_id, parcelas[], rateios[].
     * @return string|true 'OK|'/true em sucesso
     * @throws \RuntimeException com a mensagem do Processor em falha.
     */
    public function gravar(array $data)
    {
        $proc = new financeiroProcessor();

        // O Processor lê datas em d/m/Y; convertendo do ISO do React.
        $req = new Request([
            'cliente_id'       => $data['cliente_id'],
            'pagarreceber'     => $data['pagarreceber'] === 'P' ? 'P' : 'R',
            'descricao'        => $data['descricao'] ?? '',
            'documento'        => $data['documento'] ?? '',
            'valor'            => $this->br($data['valor'] ?? 0),
            'dataemissao'      => $this->dmy($data['dataemissao'] ?? null),
            'datacompetencia'  => $this->dmy($data['datacompetencia'] ?? null),
            'datavencimento'   => $this->dmy($data['datavencimento'] ?? null),
            'planoconta_id'    => $data['planoconta_id'] ?? '',
            'centrocusto_id'   => $data['centrocusto_id'] ?? '',
            'condicaopagamento_id' => $data['condicaopagamento_id'] ?? '',
            'contamovimentotipo_id' => $data['contamovimentotipo_id'] ?? null,
            'baixar'           => false, // baixa é feita pelo Caixa
            'datahorabaixa'    => '',
            'conta_id'         => null,
            'contafechamento_id' => -1,
            'cartaonsu'        => null,
            'cartaoautorizacao' => null,
            'origemAgrupar'    => null,
            'parcelasOrigem'   => null,
        ]);

        $proc->setFinanceiroRequest($req);

        // Rateios: [[centrocusto_id, '', planoconta_id, '', valorBR], ...]
        $rateios = ! empty($data['rateios'])
            ? array_map(fn ($r) => [$r['centrocusto_id'], '', $r['planoconta_id'], '', $this->br($r['valor'])], $data['rateios'])
            : [[$data['centrocusto_id'] ?? '', '', $data['planoconta_id'] ?? '', '', $this->br($data['valor'] ?? 0)]];
        $proc->setRateiosRequest(json_encode($rateios));

        // Parcelas: {data: [[dd/mm/yyyy, valor, fator], ...]}
        $parcelas = ! empty($data['parcelas'])
            ? array_map(fn ($p) => [$this->dmy($p['datavencimento']), (float) $p['valor'], 1], $data['parcelas'])
            : [[$this->dmy($data['datavencimento'] ?? null), (float) ($data['valor'] ?? 0), 1]];
        $proc->setParcelasRequest(json_encode(['data' => $parcelas]));

        if (! $proc->validar($req, true)) {
            throw new \RuntimeException(implode(' ', $proc->getErrors()));
        }
        $ret = $proc->gravar();
        if ($ret !== 'OK|' && $ret !== true) {
            throw new \RuntimeException(implode(' ', $proc->getErrors()) ?: 'Falha ao gravar lançamento.');
        }
        return $proc->getFinanceiro();
    }

    /** ISO (Y-m-d) → d/m/Y que o Processor espera. */
    private function dmy(?string $iso): string
    {
        if (! $iso) {
            return '';
        }
        try {
            return Carbon::parse($iso)->format('d/m/Y');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** float → string BR "1.234,56" (o Processor usa insertNumeroDecimalOracle). */
    private function br($valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }
}
