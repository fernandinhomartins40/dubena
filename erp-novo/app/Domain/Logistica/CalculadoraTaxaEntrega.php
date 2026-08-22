<?php

namespace App\Domain\Logistica;

use App\Models\Cliente\Cliente;
use App\Models\Logistica\TaxaEntrega;
use Illuminate\Support\Collection;

/**
 * Quanto cobrar pela entrega — e quanto ela custa.
 *
 * Antes disto o valor era digitado à mão em cada venda: sem tabela por bairro,
 * sem isenção por valor mínimo e sem CUSTO. Dava para saber quanto se cobrou,
 * mas não se a entrega deu lucro.
 *
 * A ordem de avaliação é o que faz as quatro bases conviverem:
 *
 *   1. ISENÇÃO por valor do pedido  — "acima de R$ 150 é grátis" vence tudo,
 *      porque é promessa comercial feita ao cliente;
 *   2. BAIRRO    — a regra mais específica de lugar;
 *   3. DISTÂNCIA — quando o endereço está geocodificado;
 *   4. CIDADE;
 *   5. PADRÃO    — o valor único da empresa.
 *
 * REGIÃO ficou de fora: `clientes` não tem `regiao_id` (a coluna existe em
 * `empresas`, para outra finalidade). Entra quando houver o vínculo.
 *
 * A primeira que casar decide. Sem nenhuma, a entrega é gratuita — silêncio na
 * configuração não pode virar cobrança surpresa para o cliente final.
 */
class CalculadoraTaxaEntrega
{
    public function __construct(private DistanciaEntrega $distancia) {}

    /**
     * Calcula a taxa para um cliente e um valor de pedido.
     *
     * @param  float  $valorPedido  subtotal das mercadorias (sem a taxa)
     */
    public function calcular(int $empresaId, ?Cliente $cliente, float $valorPedido = 0.0): ResultadoTaxaEntrega
    {
        $regras = $this->regrasAtivas($empresaId);

        if ($regras->isEmpty()) {
            return ResultadoTaxaEntrega::semRegra();
        }

        foreach ($this->ordemDeAvaliacao() as $criterio) {
            $regra = $this->primeiraQueCasa($regras, $criterio, $cliente, $valorPedido);

            if ($regra !== null) {
                return ResultadoTaxaEntrega::de($regra);
            }
        }

        return ResultadoTaxaEntrega::semRegra();
    }

    /**
     * Ordem de precedência dos critérios.
     *
     * `valor_pedido` primeiro de propósito: a isenção por valor mínimo é uma
     * promessa comercial ("frete grátis acima de X") e não pode ser anulada
     * por uma taxa de bairro.
     *
     * @return list<string>
     */
    private function ordemDeAvaliacao(): array
    {
        return ['valor_pedido', 'bairro', 'distancia', 'cidade', 'padrao'];
    }

    /**
     * @param  Collection<int, TaxaEntrega>  $regras
     */
    private function primeiraQueCasa(Collection $regras, string $criterio, ?Cliente $cliente, float $valorPedido): ?TaxaEntrega
    {
        return $regras
            ->where('criterio', $criterio)
            ->first(fn (TaxaEntrega $r) => $this->casa($r, $cliente, $valorPedido));
    }

    private function casa(TaxaEntrega $regra, ?Cliente $cliente, float $valorPedido): bool
    {
        return match ($regra->criterio) {
            'valor_pedido' => $this->naFaixa($regra, $valorPedido),
            'bairro' => $cliente?->bairro_id !== null && (int) $regra->bairro_id === (int) $cliente->bairro_id,
            'cidade' => $cliente?->cidade_id !== null && (int) $regra->cidade_id === (int) $cliente->cidade_id,
            'distancia' => $this->casaDistancia($regra, $cliente),
            'padrao' => true,
            default => false,
        };
    }

    /**
     * Distância só decide quando HÁ coordenada.
     *
     * Sem geocodificação a regra é ignorada em vez de assumir zero — assumir
     * distância zero daria a taxa mais barata a quem mora longe.
     */
    private function casaDistancia(TaxaEntrega $regra, ?Cliente $cliente): bool
    {
        $km = $this->distancia->emKm($cliente);

        return $km !== null && $this->naFaixa($regra, $km);
    }

    /** Faixa aberta nas duas pontas: sem `de` é "até", sem `ate` é "a partir de". */
    private function naFaixa(TaxaEntrega $regra, float $valor): bool
    {
        $de = $regra->faixa_de !== null ? (float) $regra->faixa_de : null;
        $ate = $regra->faixa_ate !== null ? (float) $regra->faixa_ate : null;

        if ($de !== null && $valor < $de) {
            return false;
        }

        return ! ($ate !== null && $valor > $ate);
    }

    /**
     * @return Collection<int, TaxaEntrega>
     */
    private function regrasAtivas(int $empresaId): Collection
    {
        return TaxaEntrega::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            // Prioridade decide entre regras do MESMO critério (dois bairros,
            // duas faixas que se sobrepõem); o id desempata para o resultado
            // ser estável entre execuções.
            ->orderByDesc('prioridade')
            ->orderBy('id')
            ->get();
    }
}
