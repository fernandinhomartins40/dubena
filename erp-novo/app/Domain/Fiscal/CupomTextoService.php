<?php

namespace App\Domain\Fiscal;

use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;

/**
 * Cupom em TEXTO para impressora térmica (F8, parte independente de hardware).
 *
 * **O que é e o que não é.** Isto produz o *conteúdo* do cupom — linhas de
 * largura fixa, prontas para qualquer impressora de coluna. Não fala com
 * hardware: a camada Bluetooth/ESC-POS vive no app, e depende de decisão sobre o
 * parque de impressoras (ver §8.1 do documento). Separar assim é o que permite
 * entregar metade do trabalho agora e testá-la sem impressora nenhuma.
 *
 * **Por que 55 colunas por padrão.** É a largura que o MovelApp usa
 * (`NotaFiscalImpressao.java:131` — `row_width=55`), e o parque atual imprime
 * nela. Impressoras de 32 e 48 colunas existem; por isso a largura é parâmetro,
 * não constante.
 *
 * **O alinhamento reproduz `getLinha`** (mesmo arquivo, linha 1512): 'L' à
 * esquerda, 'R' à direita, qualquer outra coisa centraliza, e texto maior que a
 * coluna é cortado — não quebrado. Cortar é o comportamento que o legado tem e
 * que o operador já conhece; mudar para quebra deslocaria todo o layout que ele
 * lê há anos.
 */
class CupomTextoService
{
    /** Largura do MovelApp (NotaFiscalImpressao.java:131). */
    public const LARGURA_PADRAO = 55;

    /**
     * Cupom de entrega — o que o entregador deixa com o cliente.
     *
     * @return list<string> linhas prontas para envio à impressora
     */
    public function doPedido(Pedido $pedido, int $largura = self::LARGURA_PADRAO): array
    {
        $pedido->loadMissing(['cliente', 'itens.produto', 'condicao']);
        // Pedido não declara relação `empresa` — só a coluna. Busca direta.
        $empresa = Empresa::find($pedido->empresa_id);

        $l = [];
        $l[] = $this->linha(mb_strtoupper((string) ($empresa?->nome_fantasia ?? $empresa?->razao_social ?? '')), $largura, 'C');
        $l[] = $this->separador($largura);
        $l[] = $this->linha('COMPROVANTE DE ENTREGA', $largura, 'C');
        $l[] = $this->separador($largura);

        $l[] = $this->coluna('Pedido:', (string) $pedido->id, $largura);
        $l[] = $this->coluna('Data:', optional($pedido->datahora)->format('d/m/Y H:i') ?? '', $largura);
        $l[] = $this->linha('Cliente: '.($pedido->cliente?->nome ?? ''), $largura, 'L');

        $endereco = trim(($pedido->cliente?->endereco ?? '').', '.($pedido->cliente?->numero ?? ''), ', ');
        if ($endereco !== '') {
            $l[] = $this->linha($endereco, $largura, 'L');
        }

        $l[] = $this->separador($largura);
        $l[] = $this->linha('ITEM              QTD   UNIT      TOTAL', $largura, 'L');

        foreach ($pedido->itens as $item) {
            // Descrição na primeira linha, números na segunda: em 55 colunas o
            // nome do produto sozinho já consome quase metade, e espremer tudo
            // numa linha só cortaria justamente o que identifica o item.
            $l[] = $this->linha(mb_substr((string) ($item->descricao_snapshot ?? $item->produto?->descricao ?? ''), 0, $largura), $largura, 'L');
            $l[] = $this->linha(
                $this->pad('', 18)
                .$this->pad($this->num($item->quantidade, 0), 5)
                .$this->pad($this->num($item->preco_unitario), 9)
                .$this->pad($this->num($item->valor_total), 10, 'R'),
                $largura,
                'L'
            );
        }

        $l[] = $this->separador($largura);

        if ((float) $pedido->valor_desconto > 0) {
            $l[] = $this->coluna('Desconto:', $this->num($pedido->valor_desconto), $largura);
        }
        if ((float) $pedido->entrega_taxa > 0) {
            $l[] = $this->coluna('Taxa de entrega:', $this->num($pedido->entrega_taxa), $largura);
        }

        $l[] = $this->coluna('TOTAL:', 'R$ '.$this->num($pedido->valor_venda), $largura);
        $l[] = $this->coluna('Pagamento:', (string) ($pedido->condicao?->descricao ?? ''), $largura);

        $l[] = '';
        $l[] = $this->linha('_____________________________', $largura, 'C');
        $l[] = $this->linha('Assinatura do cliente', $largura, 'C');
        $l[] = '';

        return $l;
    }

    /**
     * DANFE simplificado de uma nota autorizada.
     *
     * **Só imprime nota AUTORIZADA**, como o legado: o MovelApp verifica
     * `nfsituacao_id == 100` antes de imprimir
     * (`NotaFiscalImpressaoActivity:120`). Imprimir rascunho entregaria ao
     * cliente um documento sem valor fiscal.
     *
     * @return list<string>
     *
     * @throws \DomainException se a nota não estiver autorizada
     */
    public function daNota(NotaFiscal $nota, int $largura = self::LARGURA_PADRAO): array
    {
        if (! $this->autorizada($nota)) {
            throw new \DomainException('Nota não autorizada — DANFE não pode ser impresso.');
        }

        $nota->loadMissing(['itens.produto', 'cliente']);
        $empresa = Empresa::find($nota->empresa_id);

        $l = [];
        $l[] = $this->linha('DANFE SIMPLIFICADO', $largura, 'C');
        $l[] = $this->linha('1 - SAIDA', $largura, 'C');
        $l[] = $this->separador($largura);
        $l[] = $this->linha(mb_strtoupper((string) ($empresa?->razao_social ?? '')), $largura, 'L');
        $l[] = $this->separador($largura);

        $l[] = $this->coluna('NF-e:', (string) $nota->numero, $largura);
        $l[] = $this->coluna('Serie:', (string) $nota->serie, $largura);
        $l[] = $this->coluna('Emissao:', optional($nota->emitida_em)->format('d/m/Y H:i') ?? '', $largura);

        if (! empty($nota->chave)) {
            $l[] = '';
            $l[] = $this->linha('CHAVE DE ACESSO', $largura, 'C');
            // Quebra em blocos de 4, como na DANFE oficial: ninguém digita 44
            // dígitos corridos sem errar.
            foreach (str_split(chunk_split((string) $nota->chave, 4, ' '), $largura) as $parte) {
                $l[] = $this->linha(trim($parte), $largura, 'C');
            }
        }

        $l[] = $this->separador($largura);
        $l[] = $this->linha('Destinatario: '.($nota->cliente?->nome ?? ''), $largura, 'L');
        $l[] = $this->separador($largura);

        foreach ($nota->itens as $item) {
            $l[] = $this->linha(mb_substr((string) ($item->descricao_snapshot ?? $item->produto?->descricao ?? ''), 0, $largura), $largura, 'L');
            $l[] = $this->linha(
                $this->pad('', 18)
                .$this->pad($this->num($item->quantidade, 0), 5)
                .$this->pad($this->num($item->valor_unitario), 9)
                .$this->pad($this->num($item->valor_total), 10, 'R'),
                $largura,
                'L'
            );
        }

        $l[] = $this->separador($largura);
        $l[] = $this->coluna('TOTAL:', 'R$ '.$this->num($nota->valor_total), $largura);
        $l[] = '';

        return $l;
    }

    /** Nota autorizada pela SEFAZ — o legado compara `nfsituacao_id == 100`. */
    private function autorizada(NotaFiscal $nota): bool
    {
        $situacao = $nota->situacao;

        if ($situacao instanceof SituacaoNota) {
            return $situacao === SituacaoNota::AUTORIZADA;
        }

        return in_array((string) $situacao, ['AUTORIZADA', 'autorizada', '100'], true);
    }

    /** Rótulo à esquerda, valor à direita, na mesma linha. */
    private function coluna(string $rotulo, string $valor, int $largura): string
    {
        $espaco = max($largura - mb_strlen($rotulo) - mb_strlen($valor), 1);

        return mb_substr($rotulo.str_repeat(' ', $espaco).$valor, 0, $largura);
    }

    /** Reproduz `getLinha` do MovelApp (NotaFiscalImpressao.java:1512). */
    private function linha(string $texto, int $largura, string $align = 'L'): string
    {
        if (mb_strlen($texto) > $largura) {
            return mb_substr($texto, 0, $largura);
        }

        $sobra = $largura - mb_strlen($texto);

        return match ($align) {
            'R' => str_repeat(' ', $sobra).$texto,
            'L' => $texto.str_repeat(' ', $sobra),
            default => str_repeat(' ', intdiv($sobra, 2)).$texto.str_repeat(' ', $sobra - intdiv($sobra, 2)),
        };
    }

    private function pad(string $texto, int $tamanho, string $align = 'L'): string
    {
        return $this->linha($texto, $tamanho, $align);
    }

    private function separador(int $largura): string
    {
        return str_repeat('-', $largura);
    }

    private function num(mixed $valor, int $casas = 2): string
    {
        return number_format((float) $valor, $casas, ',', '.');
    }
}
