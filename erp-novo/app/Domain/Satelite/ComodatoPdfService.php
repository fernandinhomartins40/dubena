<?php

namespace App\Domain\Satelite;

use App\Domain\Shared\PdfService;
use App\Models\Empresa;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoContrato;
use App\Models\Satelite\ComodatoMovimento;

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
     * Passando uma `$versao`, os números impressos vêm CONGELADOS dela — é
     * assim que a via 1 continua dizendo "5 botijões" depois de o cliente
     * devolver 2. Sem versão, imprime o estado atual (comportamento antigo,
     * ainda usado por comodato do legado, que não tem versão nenhuma).
     *
     * @throws \DomainException se o comodato já estiver encerrado
     */
    public function contrato(Comodato $comodato, ?ComodatoContrato $versao = null): string
    {
        // Reimpressão de uma versão JÁ EMITIDA não passa pelo guarda: o
        // documento existiu, foi assinado, e negar a segunda via apagaria a
        // prova de uma posse que era real na data da emissão.
        if ($versao === null) {
            $this->exigirImprimivel($comodato);
        }

        $comodato->loadMissing(['cliente.telefones', 'produto']);
        $empresa = Empresa::query()->find($comodato->empresa_id);

        $cliente = $comodato->cliente;
        $doc = (string) ($cliente?->cnpj ?: $cliente?->cpf ?: '');

        $qtd = (float) ($versao->quantidade_contratada ?? $comodato->quantidade);
        $devolvida = (float) ($versao->quantidade_devolvida ?? $comodato->quantidade_devolvida);
        $pendente = round($qtd - $devolvida, 3);

        $identificacao = $this->pdf->campos([
            'Comodatário' => (string) ($cliente?->nome ?? ''),
            'CPF / CNPJ' => $this->documento($doc),
            // F3-01: ponto unico. A montagem manual lia a coluna `endereco`
            // (NULL em toda a base) e o contrato saia sem o logradouro — num
            // documento cujo proposito e localizar quem esta com o vasilhame.
            'Endereço' => (string) ($cliente?->endereco_completo ?? ''),
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

        // A linha deste comodato sempre vem dos números pedidos (congelados da
        // versão, quando há). As demais entram do estado atual — é a foto da
        // relação na hora da emissão, e é isso que o comodatário assina.
        $linhas = [[
            (string) ($comodato->produto->descricao ?? ''),
            $this->num($qtd),
            $this->num($devolvida),
            $this->num($pendente),
        ]];

        $totalEmPosse = $pendente;

        foreach ($this->outrosDoCliente($comodato) as $irmao) {
            $irmaoPendente = round(
                (float) $irmao->quantidade - (float) $irmao->quantidade_devolvida,
                3,
            );

            $linhas[] = [
                (string) ($irmao->produto->descricao ?? ''),
                $this->num((float) $irmao->quantidade),
                $this->num((float) $irmao->quantidade_devolvida),
                $this->num($irmaoPendente),
            ];

            $totalEmPosse = round($totalEmPosse + $irmaoPendente, 3);
        }

        // O total só aparece quando há mais de um item: numa linha só ele
        // repetiria o número logo acima e sujaria o documento.
        if (count($linhas) > 1) {
            $linhas[] = ['TOTAL', '', '', $this->num($totalEmPosse)];
        }

        $objeto = $this->pdf->itens(
            ['Produto', 'Quantidade emprestada', 'Já devolvida', 'Em poder do comodatário'],
            $linhas,
        );

        $datas = $this->pdf->campos([
            'Data do empréstimo' => $comodato->data_emprestimo?->format('d/m/Y') ?? '',
            'Situação' => (string) $comodato->situacao,
            'Contrato nº' => $versao !== null
                ? sprintf('%d — versão %d', $comodato->id, $versao->versao)
                : (string) $comodato->id,
            'Emitido em' => $versao?->created_at?->format('d/m/Y H:i'),
        ]);

        $corpo = '<h2 style="font-size:11px;margin:10px 0 4px">1. Partes</h2>'
            .$this->partes($empresa)
            .$identificacao
            .'<h2 style="font-size:11px;margin:10px 0 4px">2. Objeto do comodato</h2>'
            .$objeto
            .$datas
            .$this->notaDeVersao($versao)
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
     * Os demais vasilhames que o MESMO cliente tem em comodato vigente.
     *
     * O contrato descreve a relação, não o registro: um cliente com P13 e P20
     * assina um papel que lista os dois. Manter um comodato por produto é o que
     * permite à vigilância medir giro por capacidade; consolidar acontece só
     * aqui, na impressão.
     *
     * Filtra por `empresa_id` explicitamente — o cliente pode ser atendido por
     * mais de uma empresa do grupo, e o contrato de uma jamais pode listar o
     * vasilhame que está emprestado pela outra.
     */
    private function outrosDoCliente(Comodato $comodato)
    {
        if ($comodato->cliente_id === null) {
            return collect();
        }

        return Comodato::query()
            ->with('produto')
            ->where('empresa_id', $comodato->empresa_id)
            ->where('cliente_id', $comodato->cliente_id)
            ->where('id', '!=', $comodato->id)
            // Um contrato descreve uma direção de obrigação. Misturar o que
            // emprestamos com o que devemos ao mesmo parceiro produziria um
            // papel que afirma duas dívidas opostas na mesma tabela.
            ->where('sentido', $comodato->sentido)
            ->whereIn('situacao', ['ATIVO', 'PARCIAL'])
            // Sem saldo em poder do cliente não há posse a contratar, mesmo que
            // a situação ainda não tenha sido fechada.
            ->get()
            ->filter(fn (Comodato $c) => round(
                (float) $c->quantidade - (float) $c->quantidade_devolvida,
                3,
            ) > 0.0001)
            ->sortByDesc(fn (Comodato $c) => (float) $c->quantidade - (float) $c->quantidade_devolvida)
            ->values();
    }

    /**
     * Comodato já encerrado não vira contrato.
     *
     * Um contrato de comodato descreve uma obrigação vigente: o cliente está com
     * o vasilhame e deve devolvê-lo. Imprimir isso depois da devolução total
     * criaria documento afirmando uma posse que não existe — que o cliente
     * poderia ser cobrado com base nela.
     *
     * `ENCERRADO` está na lista porque o ETL trouxe 745 comodatos do legado com
     * essa situação, que o código não conhecia. Barrar só `DEVOLVIDO` deixava
     * todos os 745 imprimindo contrato de posse encerrada — exatamente o risco
     * que este guarda existe para evitar.
     */
    private function exigirImprimivel(Comodato $comodato): void
    {
        $encerradas = ['DEVOLVIDO', 'ENCERRADO', 'CANCELADO'];

        if (in_array((string) $comodato->situacao, $encerradas, true)) {
            throw new \DomainException(
                'Comodato encerrado não gera contrato: o documento afirmaria uma posse que não existe mais.'
            );
        }

        $pendente = (float) $comodato->quantidade - (float) $comodato->quantidade_devolvida;

        if ($pendente <= 0) {
            throw new \DomainException('Não há vasilhame em poder do comodatário — nada a contratar.');
        }
    }

    /**
     * Explica no papel por que existe uma versão 2.
     *
     * Sem isso o cliente recebe um contrato com número menor que o assinado
     * antes e não tem como saber que é o mesmo empréstimo com devolução
     * abatida — a leitura natural seria "emprestaram menos do que combinamos".
     */
    private function notaDeVersao(?ComodatoContrato $versao): string
    {
        if ($versao === null || $versao->motivo === ComodatoContrato::EMISSAO_INICIAL) {
            return '';
        }

        $texto = match ($versao->motivo) {
            ComodatoContrato::DEVOLUCAO_PARCIAL => sprintf(
                'Esta é a versão %d do contrato nº %d, emitida após devolução parcial de %s '
                .'unidade(s). As quantidades acima já refletem o abatimento; a versão anterior '
                .'fica sem efeito a partir desta data.',
                $versao->versao,
                $versao->comodato_id,
                $this->num((float) ($versao->movimento?->quantidade ?? 0)),
            ),
            ComodatoContrato::ACRESCIMO => sprintf(
                'Esta é a versão %d do contrato nº %d, emitida após acréscimo de %s unidade(s) '
                .'ao comodato. As quantidades acima já incluem o acréscimo; a versão anterior '
                .'fica sem efeito a partir desta data.',
                $versao->versao,
                $versao->comodato_id,
                $this->num((float) ($versao->movimento?->quantidade ?? 0)),
            ),
            ComodatoContrato::RENOVACAO => sprintf(
                'Esta é a versão %d do contrato nº %d, emitida na renovação do comodato. '
                .'A versão anterior fica sem efeito a partir desta data.',
                $versao->versao,
                $versao->comodato_id,
            ),
            default => sprintf(
                'Esta é a versão %d do contrato nº %d, reemitida em substituição à anterior, '
                .'que fica sem efeito a partir desta data.',
                $versao->versao,
                $versao->comodato_id,
            ),
        };

        return '<p style="margin:6px 0 0;padding:6px;background:#f1f5f9;'
            .'border-left:3px solid #64748b;font-size:9px;text-align:justify">'
            .e($texto).'</p>';
    }

    /**
     * Recibo de devolução — a prova, para o CLIENTE, de que ele entregou.
     *
     * O contrato protege a revenda; este documento protege quem devolveu. Sem
     * ele o cliente sai da entrega sem nada na mão, e uma cobrança futura pelo
     * vasilhame vira palavra contra palavra.
     */
    public function reciboDevolucao(ComodatoMovimento $movimento): string
    {
        if ($movimento->tipo !== ComodatoMovimento::DEVOLUCAO) {
            throw new \DomainException('Só devolução gera recibo.');
        }

        $comodato = $movimento->comodato;
        $comodato->loadMissing(['cliente', 'produto']);
        $empresa = Empresa::query()->find($comodato->empresa_id);
        $cliente = $comodato->cliente;

        $corpo = '<p style="margin:0 0 8px;text-align:justify">'
            .'A REVENDA declara ter <strong>RECEBIDO</strong> do comodatário abaixo '
            .'identificado o(s) vasilhame(s) descrito(s), objeto do contrato de comodato '
            .'nº '.e((string) $comodato->id).'.</p>'
            .$this->pdf->campos([
                'Comodatário' => (string) ($cliente?->nome ?? ''),
                'CPF / CNPJ' => $this->documento((string) ($cliente?->cnpj ?: $cliente?->cpf ?: '')),
                'Recibo nº' => (string) $movimento->id,
            ])
            .$this->pdf->itens(
                ['Produto', 'Devolvido agora', 'Ainda em poder do comodatário'],
                [[
                    (string) ($comodato->produto->descricao ?? ''),
                    $this->num((float) $movimento->quantidade),
                    $this->num((float) $movimento->saldo_apos),
                ]],
            )
            .$this->pdf->campos([
                'Data da devolução' => $movimento->data?->format('d/m/Y') ?? '',
                'Recebido por' => (string) ($movimento->usuario?->name ?? ''),
                'Observação' => $movimento->observacao,
            ])
            // O saldo remanescente é a informação que evita a briga seguinte: o
            // cliente precisa sair sabendo quantos ainda estão com ele.
            .($movimento->saldo_apos > 0
                ? '<p style="margin:8px 0 0;padding:6px;background:#fef3c7;border-left:3px solid #d97706;'
                    .'font-size:9px">Permanecem <strong>'.e($this->num((float) $movimento->saldo_apos))
                    .'</strong> unidade(s) em poder do comodatário, regidas pela versão vigente do contrato.</p>'
                : '<p style="margin:8px 0 0;padding:6px;background:#dcfce7;border-left:3px solid #16a34a;'
                    .'font-size:9px">Devolução <strong>integral</strong>: o comodato está encerrado e '
                    .'nada mais é devido quanto ao vasilhame.</p>')
            .$this->duasAssinaturas();

        return $this->pdf->documento('Recibo de Devolução de Vasilhame', $corpo, [
            'empresa' => (string) ($empresa->razao_social ?? ''),
            'cnpj' => (string) ($empresa->cnpj ?? ''),
            'endereco' => $this->enderecoDa($empresa),
            'rodape' => 'Via do comodatário. Este documento comprova a devolução descrita acima '
                .'e não é recibo de pagamento nem nota fiscal.',
        ]);
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
