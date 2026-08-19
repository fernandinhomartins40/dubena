<?php

namespace App\Domain\Satelite;

use App\Domain\Shared\PdfService;
use App\Models\Empresa;
use App\Models\Satelite\Comodato;

/**
 * Contrato de comodato de vasilhame (item 20 da triagem).
 *
 * **Por que é PRÉ-GO-LIVE.** O botijão é patrimônio da revenda que fica na casa
 * do cliente. O contrato assinado é o que separa "empréstimo com prazo e
 * responsável identificado" de "sumiço sem documento" — sem ele a revenda não
 * tem como reaver o vasilhame nem cobrar por ele. A triagem classifica a
 * **gestão analítica** (saldos, vencidos, giro) como PÓS; o **contrato** é PRÉ,
 * porque é o instrumento que protege o patrimônio.
 *
 * **O que este serviço faz.** Materializa em papel um comodato que já existe no
 * banco, com as cláusulas do empréstimo e as duas assinaturas. Não cria, não
 * altera situação, não mexe em estoque — quem faz isso é o `ComodatoService`.
 *
 * **Sobre o texto das cláusulas.** É o texto operacional padrão do comodato de
 * vasilhame, com os dados do caso preenchidos. Não substitui revisão jurídica:
 * quem responde pelo contrato é a revenda, e o texto está isolado num método
 * (`clausulas()`) exatamente para poder ser trocado sem tocar no resto.
 */
class ComodatoPdfService
{
    public function __construct(private PdfService $pdf) {}

    /**
     * Gera o contrato de um comodato.
     *
     * @throws \DomainException se o comodato já estiver encerrado
     */
    public function contrato(Comodato $comodato): string
    {
        $this->exigirImprimivel($comodato);

        $comodato->loadMissing(['cliente.telefones', 'produto']);
        $empresa = Empresa::query()->find($comodato->empresa_id);

        $cliente = $comodato->cliente;
        $doc = (string) ($cliente?->cnpj ?: $cliente?->cpf ?: '');

        $qtd = (float) $comodato->quantidade;
        $devolvida = (float) $comodato->quantidade_devolvida;
        $pendente = round($qtd - $devolvida, 3);

        $identificacao = $this->pdf->campos([
            'Comodatário' => (string) ($cliente?->nome ?? ''),
            'CPF / CNPJ' => $this->documento($doc),
            'Endereço' => trim(sprintf('%s, %s %s', $cliente?->endereco ?? '', $cliente?->numero ?? '', $cliente?->complemento ?? ''), ' ,'),
            'CEP / UF' => trim(((string) ($cliente?->cep ?? '')).' / '.((string) ($cliente?->uf ?? '')), ' /'),
            // Telefone vive em `clientetelefones`, nao numa coluna do cliente:
            // sem ele o contrato nao tem como localizar quem esta com o vasilhame.
            'Telefone' => (string) ($cliente?->telefones->first()?->telefone ?? ''),
            // Quem assina pelo comodatário. Em contrato de pessoa jurídica é o
            // que dá validade à assinatura — o legado guarda em 784 dos 975
            // comodatos e o campo não vinha para cá.
            'Representante' => $comodato->nome_representante !== null
                ? trim(sprintf(
                    '%s%s',
                    $comodato->nome_representante,
                    $comodato->cpf_representante !== null
                        ? ' — CPF '.$this->documento($comodato->cpf_representante)
                        : '',
                ))
                : null,
            'Vencimento' => $comodato->data_vencimento?->format('d/m/Y'),
        ]);

        $objeto = $this->pdf->itens(
            ['Produto', 'Quantidade emprestada', 'Já devolvida', 'Em poder do comodatário'],
            [[
                (string) ($comodato->produto->descricao ?? ''),
                $this->num($qtd),
                $this->num($devolvida),
                $this->num($pendente),
            ]],
        );

        $datas = $this->pdf->campos([
            'Data do empréstimo' => $comodato->data_emprestimo?->format('d/m/Y') ?? '',
            'Situação' => (string) $comodato->situacao,
            'Contrato nº' => (string) $comodato->id,
        ]);

        $corpo = '<h2 style="font-size:11px;margin:10px 0 4px">1. Partes</h2>'
            .$this->partes($empresa)
            .$identificacao
            .'<h2 style="font-size:11px;margin:10px 0 4px">2. Objeto do comodato</h2>'
            .$objeto
            .$datas
            .'<h2 style="font-size:11px;margin:10px 0 4px">3. Cláusulas</h2>'
            .$this->clausulas()
            .$this->duasAssinaturas();

        return $this->pdf->documento('Contrato de Comodato de Vasilhame', $corpo, [
            'empresa' => (string) ($empresa->razao_social ?? ''),
            'cnpj' => (string) ($empresa->cnpj ?? ''),
            'endereco' => $this->enderecoDa($empresa),
            'rodape' => 'Via da revenda. O comodatário recebe cópia idêntica assinada. '
                .'Este documento não é recibo de pagamento nem nota fiscal.',
        ]);
    }

    /**
     * Comodato já encerrado não vira contrato.
     *
     * Um contrato de comodato descreve uma obrigação vigente: o cliente está com
     * o vasilhame e deve devolvê-lo. Imprimir isso depois da devolução total
     * criaria documento afirmando uma posse que não existe — que o cliente
     * poderia ser cobrado com base nela.
     */
    private function exigirImprimivel(Comodato $comodato): void
    {
        if ($comodato->situacao === 'DEVOLVIDO') {
            throw new \DomainException(
                'Comodato já devolvido não gera contrato: o documento afirmaria uma posse que não existe mais.'
            );
        }

        $pendente = (float) $comodato->quantidade - (float) $comodato->quantidade_devolvida;

        if ($pendente <= 0) {
            throw new \DomainException('Não há vasilhame em poder do comodatário — nada a contratar.');
        }
    }

    private function partes(?Empresa $empresa): string
    {
        $razao = e((string) ($empresa->razao_social ?? ''));
        $cnpj = e($this->documento((string) ($empresa->cnpj ?? '')));

        return '<p style="margin:0 0 6px;text-align:justify">'
            ."<strong>COMODANTE:</strong> {$razao}, inscrita no CNPJ sob o nº {$cnpj}, "
            .'doravante denominada REVENDA, e <strong>COMODATÁRIO</strong>, qualificado abaixo, '
            .'celebram o presente contrato de comodato de vasilhame, nas condições a seguir.</p>';
    }

    /**
     * Texto operacional das cláusulas.
     *
     * Isolado num método porque é a parte que a revenda mais provavelmente vai
     * querer ajustar (prazo, multa, foro) — e ajustar aqui não toca em mais nada.
     */
    private function clausulas(): string
    {
        $itens = [
            'O COMODATÁRIO recebe, a título de empréstimo gratuito, o(s) vasilhame(s) '
                .'descrito(s) na cláusula 2, de propriedade exclusiva da REVENDA.',
            'O vasilhame não é vendido em nenhuma hipótese: a compra de gás refere-se '
                .'exclusivamente ao conteúdo. A propriedade do recipiente permanece com a REVENDA.',
            'O COMODATÁRIO obriga-se a conservar o vasilhame, respondendo por perda, '
                .'extravio, dano ou uso indevido, inclusive por terceiros a quem o entregar.',
            'É vedado ao COMODATÁRIO ceder, emprestar, alugar, dar em garantia ou usar o '
                .'vasilhame para envase por terceiros não autorizados, prática vedada pela '
                .'regulamentação do setor.',
            'O vasilhame deve ser devolvido à REVENDA quando cessar o fornecimento ou a '
                .'pedido desta, no estado em que foi recebido, ressalvado o desgaste natural.',
            'Não devolvido ou devolvido danificado, o COMODATÁRIO indenizará a REVENDA pelo '
                .'valor de reposição vigente na data da apuração.',
            'Este contrato vigora por prazo indeterminado, podendo ser denunciado por '
                .'qualquer das partes, encerrando-se com a devolução integral do vasilhame.',
        ];

        $html = '<ol style="margin:0 0 8px 14px;padding:0;text-align:justify">';
        foreach ($itens as $item) {
            $html .= '<li style="margin-bottom:3px">'.e($item).'</li>';
        }

        return $html.'</ol>';
    }

    /** Duas assinaturas lado a lado — comodato é bilateral. */
    private function duasAssinaturas(): string
    {
        $local = '<p style="margin:14px 0 0;font-size:9px">Local e data: ____________________________________, ______/______/__________</p>';

        return $local.'<table style="width:100%;margin-top:26px"><tr>'
            .'<td style="width:48%;border-top:1px solid #1e293b;padding-top:4px;font-size:9px;color:#475569">'
            .'REVENDA (comodante)</td>'
            .'<td style="width:4%"></td>'
            .'<td style="width:48%;border-top:1px solid #1e293b;padding-top:4px;font-size:9px;color:#475569">'
            .'COMODATÁRIO</td>'
            .'</tr></table>';
    }

    private function enderecoDa(?Empresa $empresa): string
    {
        if ($empresa === null) {
            return '';
        }

        return trim(sprintf(
            '%s, %s - %s - %s/%s',
            $empresa->endereco ?? '', $empresa->numero ?? '',
            $empresa->bairro ?? '', $empresa->cidade ?? '', $empresa->uf ?? '',
        ), ' -,/');
    }

    /** CNPJ/CPF com máscara; devolve cru se o tamanho não for o esperado. */
    private function documento(string $doc): string
    {
        $d = preg_replace('/\D/', '', $doc) ?? '';

        if (strlen($d) === 14) {
            return vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($d));
        }
        if (strlen($d) === 11) {
            return vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($d));
        }

        return $doc;
    }

    /** Quantidade sem casas decimais inúteis: vasilhame é contado em unidades. */
    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    }
}
