<?php

namespace App\Domain\Financeiro;

use App\Models\Financeiro\ContaExtratoRegra;

/**
 * Classificação automática das linhas do extrato bancário (T4.2).
 *
 * **O problema que resolve.** A importação OFX existia, mas devolvia uma lista
 * crua: cada linha precisava ser classificada à mão (plano de contas, centro de
 * custo, tipo de movimento). Com o volume real da operação — `contamovimentos`
 * tem 410.417 linhas — isso não é inconveniência, é impedimento. É o que a
 * auditoria chama de "a importação existe mas não é produtiva".
 *
 * **Como casa.** Por conta bancária, comparando a descrição/memo da linha do
 * OFX com o padrão da regra. O casamento é por CONTINÊNCIA e normalizado (sem
 * acento, sem caixa, espaços colapsados), porque o texto que o banco manda varia
 * — "PIX RECEBIDO", "Pix recebido de FULANO", "PIX  RECEBIDO-123" precisam casar
 * com a mesma regra.
 *
 * **O que NÃO faz:** não grava lançamento. Devolve a sugestão; quem confirma é
 * o operador na tela de conciliação. Classificar automaticamente é economia de
 * digitação, não automação de decisão financeira.
 */
class RegraExtratoService
{
    /**
     * Anota cada transação do OFX com a regra que casou (se alguma).
     *
     * @param  list<array<string,mixed>>  $linhas  transações do OfxParser
     * @return list<array<string,mixed>>  as mesmas linhas, com `sugestao`
     */
    public function aplicar(int $contaId, array $linhas): array
    {
        if ($linhas === []) {
            return [];
        }

        $regras = $this->regrasDaConta($contaId);
        if ($regras === []) {
            // Sem regras cadastradas, devolve as linhas intactas: a tela segue
            // funcionando exatamente como antes.
            return $linhas;
        }

        return array_map(function (array $linha) use ($regras) {
            $regra = $this->casar((string) ($linha['descricao'] ?? ''), $regras);
            $linha['sugestao'] = $regra !== null ? $this->sugestao($regra, $linha) : null;

            return $linha;
        }, $linhas);
    }

    /**
     * Encontra a regra que casa com a descrição.
     *
     * Já vêm ordenadas por prioridade e, dentro dela, pelas descrições mais
     * LONGAS primeiro: "PIX RECEBIDO ALUGUEL" tem de vencer de "PIX RECEBIDO",
     * senão a regra genérica engoliria todas as específicas.
     *
     * @param  list<ContaExtratoRegra>  $regras
     */
    public function casar(string $descricao, array $regras): ?ContaExtratoRegra
    {
        $alvo = $this->normalizar($descricao);
        if ($alvo === '') {
            return null;
        }

        foreach ($regras as $regra) {
            $padrao = $this->normalizar((string) $regra->descricao);
            if ($padrao !== '' && str_contains($alvo, $padrao)) {
                return $regra;
            }
        }

        return null;
    }

    /**
     * Regras ativas de uma conta, na ordem de avaliação.
     *
     * @return list<ContaExtratoRegra>
     */
    public function regrasDaConta(int $contaId): array
    {
        return ContaExtratoRegra::query()
            ->where('conta_id', $contaId)
            ->where('ativo', true)
            ->get()
            ->sortBy([
                ['prioridade', 'desc'],
                // Desempate por comprimento: a regra mais específica primeiro.
                fn ($a, $b) => mb_strlen((string) $b->descricao) <=> mb_strlen((string) $a->descricao),
            ])
            ->values()
            ->all();
    }

    /**
     * Monta a sugestão de lançamento a partir da regra.
     *
     * O SINAL vem do extrato, não da regra: uma linha negativa é saída
     * (pagamento) e positiva é entrada (recebimento), independentemente do que
     * a regra diga. Inverter isso lançaria despesa como receita.
     *
     * @param  array<string,mixed>  $linha
     * @return array<string,mixed>
     */
    private function sugestao(ContaExtratoRegra $regra, array $linha): array
    {
        $valor = (float) ($linha['valor'] ?? 0);

        return [
            'regra_id' => $regra->id,
            'regra_descricao' => $regra->descricao,
            'acao' => $regra->acao->value,
            'acao_rotulo' => $regra->acao->rotulo(),
            'pagarreceber' => $valor < 0 ? 'P' : 'R',
            'valor' => abs($valor),
            'condicaopagamento_id' => $regra->condicaopagamento_id,
            'contamovimentotipo_id' => $regra->contamovimentotipo_id,
            'plano_conta_id' => $regra->plano_conta_id,
            'centro_custo_id' => $regra->centro_custo_id,
            'cliente_id' => $regra->cliente_id,
            'conta_origem_id' => $regra->conta_origem_id,
            // A baixa automática só faz sentido em LANCAR_BAIXAR: o dinheiro já
            // consta no extrato, então o título nasce quitado.
            'baixar' => $regra->acao === ContaExtratoAcao::LANCAR_BAIXAR,
        ];
    }

    /**
     * Normaliza para comparação: minúsculo, sem acento, sem pontuação, espaços
     * colapsados.
     *
     * O texto que cada banco manda no MEMO varia em pontuação e caixa; comparar
     * cru faria a mesma regra falhar em metade dos extratos.
     */
    private function normalizar(string $v): string
    {
        $v = mb_strtolower(trim($v));

        // Tabela explícita em vez de iconv//TRANSLIT: o resultado do TRANSLIT
        // depende do locale do sistema — no Windows ele devolve "?" para
        // acentuados, e "pix recebído" deixaria de casar com "pix recebido".
        $v = strtr($v, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        $v = preg_replace('/[^a-z0-9 ]/u', ' ', $v) ?? '';

        return trim(preg_replace('/\s+/', ' ', $v) ?? '');
    }
}
