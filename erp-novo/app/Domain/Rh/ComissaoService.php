<?php

namespace App\Domain\Rh;

use App\Models\Rh\ColaboradorComissao;

/**
 * ComissaoService (C5) — PORTA a fórmula de comissão do legado
 * (ReportcomissoesController). É regra de DINHEIRO: entra com baseline (caso de
 * ouro) replicando, centavo a centavo, o cálculo legado.
 *
 * Por item de venda, a comissão tem dois "baldes":
 *   - percentual: ganho percentual sobre o valor (tipo 1 / exceção 1);
 *   - repasse:    valor que sobra após a empresa reter um fixo por unidade (tipo 2 / exceção 2).
 *
 * O **tipo 3 (misto)** enche os dois: repasse por unidade + percentual sobre a
 * venda, somados. É o modelo que a rede usa com franqueados (F5), e foi
 * acrescentado como tipo próprio para não alterar o cálculo de quem já está
 * cadastrado nos tipos 1 e 2.
 *
 * Sequência (idêntica ao legado):
 *   valor = preco_unitario * quantidade
 *   se há desconto de convênio:  valor -= valor * comissao_convenio/100
 *   proporcional ao desconto do pedido:
 *       proporcional = valor - (valor / valorbruto) * valordesconto
 *   então, conforme tipo/exceção, calcula percentual OU repasse.
 */
class ComissaoService
{
    /**
     * Calcula a comissão de UM item.
     *
     * @param  array{
     *   preco_unitario: float, quantidade: float, valor_venda: float,
     *   valor_desconto?: float, comissao_convenio?: float, do_app?: bool
     * }  $item
     * @return array{base: float, percentual: float, repasse: float, percentual_aplicado: float}
     */
    public function calcularItem(array $item, ColaboradorComissao $regra): array
    {
        $qtd = (float) $item['quantidade'];
        $precoUnit = (float) $item['preco_unitario'];
        $valorVenda = (float) $item['valor_venda'];
        $valorDesconto = (float) ($item['valor_desconto'] ?? 0);
        $convenio = (float) ($item['comissao_convenio'] ?? 0);
        $doApp = (bool) ($item['do_app'] ?? false);

        // valorbruto do pedido (venda + desconto) — base do rateio proporcional.
        $valorBruto = $valorVenda + $valorDesconto;

        $valor = round($precoUnit * $qtd, 2);
        if ($convenio > 0) {
            $valor = $valor - ($valor * $convenio / 100);
        }

        // Rateio proporcional do desconto do pedido sobre este item.
        $proporcional = $valorBruto > 0
            ? $valor - (($valor / $valorBruto) * $valorDesconto)
            : $valor;
        $valor = $proporcional;

        // Percentual/valor efetivo conforme origem (app ou balcão).
        $percentualRegra = $doApp && $regra->percentual_app !== null
            ? (float) $regra->percentual_app
            : (float) $regra->percentual;
        $empresaValor = $doApp && $regra->empresa_valor_app !== null
            ? (float) $regra->empresa_valor_app
            : (float) $regra->empresa_valor;

        // Exceção por segmento, se houver (sobrescreve a regra base).
        $excecao = $regra->relationLoaded('excecoes') ? $regra->excecoes->first() : $regra->excecoes()->first();

        $percentual = 0.0;
        $repasse = 0.0;
        $aplicado = 0.0;

        if ($excecao && (int) $excecao->tipo_excecao === 1) {
            $valExc = $doApp && $excecao->valor_excecao_app !== null ? (float) $excecao->valor_excecao_app : (float) $excecao->valor_excecao;
            $percentual = $valor * $valExc / 100;
            $aplicado = $valExc;
        } elseif ($excecao && (int) $excecao->tipo_excecao === 2) {
            $valExc = $doApp && $excecao->valor_excecao_app !== null ? (float) $excecao->valor_excecao_app : (float) $excecao->valor_excecao;
            $repasse = $proporcional - ($valExc * $qtd);
            $aplicado = $valExc;
        } elseif ((int) $regra->tipo_comissao === 1) {
            $percentual = $valor * $percentualRegra / 100;
            $aplicado = $percentualRegra;
        } elseif ((int) $regra->tipo_comissao === 2) {
            $repasse = $proporcional - ($empresaValor * $qtd);
            $aplicado = $empresaValor;
        } elseif ((int) $regra->tipo_comissao === 3) {
            // MISTO (F5) — o modelo que o cliente usa com os franqueados: recebe o
            // repasse por unidade E MAIS um percentual sobre a venda; as duas
            // parcelas somam.
            //
            // Tipo novo em vez de deixar 1 e 2 coexistirem: os `elseif` acima
            // replicam o legado "centavo a centavo" (ver cabeçalho), e transformá-los
            // em `if` independentes mudaria silenciosamente o valor pago a todo
            // colaborador já cadastrado. O tipo 3 é aditivo — quem não usa não sente.
            $repasse = $proporcional - ($empresaValor * $qtd);
            $percentual = $valor * $percentualRegra / 100;
            $aplicado = $percentualRegra;
        }

        return [
            'base' => round($valor, 2),
            'percentual' => round($percentual, 2),
            'repasse' => round($repasse, 2),
            'percentual_aplicado' => round($aplicado, 4),
        ];
    }

    /**
     * Total da comissão de um colaborador sobre uma lista de itens já casados com
     * suas regras: soma percentual + repasse (= total a receber).
     *
     * @param  list<array{0: array<string,mixed>, 1: ColaboradorComissao}>  $itensComRegra
     * @return array{percentual: float, repasse: float, total: float}
     */
    public function totalColaborador(array $itensComRegra): array
    {
        $percentual = 0.0;
        $repasse = 0.0;
        foreach ($itensComRegra as [$item, $regra]) {
            $r = $this->calcularItem($item, $regra);
            $percentual += $r['percentual'];
            $repasse += $r['repasse'];
        }

        return [
            'percentual' => round($percentual, 2),
            'repasse' => round($repasse, 2),
            'total' => round($percentual + $repasse, 2),
        ];
    }
}
