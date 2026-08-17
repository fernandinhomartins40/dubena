<?php

namespace App\Domain\Fiscal;

use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * DANFE — Documento Auxiliar da Nota Fiscal Eletrônica.
 *
 * **Por que é PRÉ-GO-LIVE.** Sem DANFE a mercadoria não circula legalmente: é o
 * papel que acompanha a carga e que a fiscalização de trânsito exige. O XML
 * autorizado sozinho não resolve — quem para o caminhão pede o impresso.
 *
 * **O que este serviço NÃO faz.** Não calcula, não recolhe, não decide nada
 * fiscal. Ele IMPRIME o que a SEFAZ autorizou. Todo número aqui vem da nota
 * gravada; recalcular qualquer coisa na impressão criaria a possibilidade de o
 * papel divergir do XML — que é exatamente o problema que o DANFE existe para
 * não ter.
 *
 * **Por que só de nota autorizada.** Um DANFE de rascunho ou de nota rejeitada
 * é um documento que aparenta validade e não tem. Circular com ele é pior do
 * que circular sem nada, porque induz o motorista a acreditar que está coberto.
 * Por isso a emissão é bloqueada fora de AUTORIZADA (e de CANCELADA, que sai
 * marcada como tal).
 *
 * **Layout.** O DANFE tem forma definida pelo Manual de Integração (quadros,
 * ordem dos campos, barcode da chave). Ele não usa a moldura genérica do
 * `PdfService` justamente por isso: o formato não é escolha nossa.
 */
class DanfePdfService
{
    public function __construct(private CodigoBarras128C $barras)
    {
    }

    /**
     * Gera os bytes do PDF do DANFE de uma nota.
     *
     * @throws \DomainException se a nota não estiver em situação que permita imprimir
     */
    public function gerar(NotaFiscal $nota): string
    {
        $this->exigirImprimivel($nota);

        $nota->loadMissing(['cliente', 'itens.produto']);
        $empresa = Empresa::query()->find($nota->empresa_id);

        $chave = preg_replace('/\D/', '', (string) $nota->chave) ?? '';

        return Pdf::loadHTML($this->html($nota, $empresa, $chave))
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * Só nota autorizada (ou cancelada, marcada como tal) vira DANFE.
     *
     * A checagem é do serviço, não do controller: qualquer caminho que chegue
     * aqui — tela, job, integração — tem de esbarrar na mesma regra.
     */
    private function exigirImprimivel(NotaFiscal $nota): void
    {
        if ($nota->situacao === SituacaoNota::CANCELADA) {
            return; // sai com a tarja; ver §cancelada no HTML
        }

        if ($nota->situacao !== SituacaoNota::AUTORIZADA) {
            throw new \DomainException(
                'O DANFE só pode ser impresso de nota autorizada pela SEFAZ. '
                ."Situação atual: {$nota->situacao?->value}."
            );
        }

        if (trim((string) $nota->chave) === '') {
            throw new \DomainException('Nota autorizada sem chave de acesso gravada — DANFE não pode ser impresso.');
        }
    }

    private function html(NotaFiscal $nota, ?Empresa $empresa, string $chave): string
    {
        $cancelada = $nota->situacao === SituacaoNota::CANCELADA;

        // Uma nota cancelada pode ter de ser reimpressa para arquivo, mas o
        // papel precisa dizer isso na cara: sem a tarja, viraria um documento
        // de aparência válida para mercadoria que não pode circular.
        $tarja = $cancelada
            ? '<div class="tarja">NOTA FISCAL CANCELADA — SEM VALOR FISCAL</div>'
            : '';

        $barrasHtml = $chave !== '' && strlen($chave) === 44
            ? $this->barras->html($chave, altura: 44)
            : '<div class="semchave">Chave de acesso indisponível</div>';

        $chaveFmt = e($this->barras->chaveFormatada($chave));

        return <<<HTML
        <html><head><meta charset="utf-8"><style>
          @page { margin: 6mm; }
          body { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #000; }
          table { width: 100%; border-collapse: collapse; }
          .q { border: 1px solid #000; }
          .q td { padding: 2px 3px; vertical-align: top; }
          .rot { font-size: 5.5px; text-transform: uppercase; color: #333; display: block; }
          .val { font-size: 8px; }
          .b { font-weight: bold; }
          .c { text-align: center; }
          .r { text-align: right; }
          .titulo { font-size: 13px; font-weight: bold; }
          .sep { height: 4px; }
          .itens th { border: 1px solid #000; background: #eee; font-size: 5.5px;
                      text-transform: uppercase; padding: 2px; }
          .itens td { border: 1px solid #000; padding: 2px; font-size: 6.5px; }
          .tarja { border: 2px solid #000; text-align: center; font-size: 12px;
                   font-weight: bold; padding: 4px; margin-bottom: 4px; }
          .semchave { font-size: 8px; color: #900; }
          .canhoto { border: 1px solid #000; padding: 3px; margin-bottom: 4px; font-size: 6px; }
        </style></head><body>
          {$tarja}
          {$this->canhoto($nota)}
          {$this->cabecalho($nota, $empresa, $barrasHtml, $chaveFmt)}
          <div class="sep"></div>
          {$this->destinatario($nota)}
          <div class="sep"></div>
          {$this->totais($nota)}
          <div class="sep"></div>
          {$this->itens($nota)}
          <div class="sep"></div>
          {$this->rodape($nota)}
        </body></html>
        HTML;
    }

    /** Canhoto de recebimento — o destinatário assina e a revenda arquiva. */
    private function canhoto(NotaFiscal $nota): string
    {
        $numero = e($this->numeroFormatado($nota));
        $emissao = e($nota->emitida_em?->format('d/m/Y') ?? '');
        $destino = e((string) ($nota->cliente->nome ?? ''));

        return <<<HTML
        <div class="canhoto">
          RECEBEMOS DE <span class="b">{$destino}</span> OS PRODUTOS CONSTANTES DA NOTA FISCAL AO LADO
          &nbsp;&nbsp;|&nbsp;&nbsp; DATA DE RECEBIMENTO: ______/______/__________
          &nbsp;&nbsp;|&nbsp;&nbsp; ASSINATURA: ______________________________________
          &nbsp;&nbsp;|&nbsp;&nbsp; NF-e Nº <span class="b">{$numero}</span> — Emissão {$emissao}
        </div>
        HTML;
    }

    private function cabecalho(NotaFiscal $nota, ?Empresa $empresa, string $barrasHtml, string $chaveFmt): string
    {
        $razao = e((string) ($empresa->razao_social ?? ''));
        $cnpj = e($this->documento((string) ($empresa->cnpj ?? '')));
        $ie = e((string) ($empresa->inscricao_estadual ?? ''));
        $endereco = e(trim(sprintf(
            '%s, %s - %s - %s/%s - CEP %s',
            $empresa->endereco ?? '', $empresa->numero ?? '', $empresa->bairro ?? '',
            $empresa->cidade ?? '', $empresa->uf ?? '', $empresa->cep ?? '',
        ), ' -,/'));

        $numero = e($this->numeroFormatado($nota));
        $serie = e((string) $nota->serie);
        $tipo = $nota->tipo === 'ENTRADA' ? '0 - ENTRADA' : '1 - SAÍDA';
        $emissao = e($nota->emitida_em?->format('d/m/Y H:i') ?? '');
        $protocolo = e((string) ($nota->protocolo ?? ''));
        $modelo = e((string) ($nota->modelo?->value ?? ''));

        return <<<HTML
        <table class="q"><tr>
          <td style="width:42%">
            <span class="titulo">{$razao}</span>
            <div>{$endereco}</div>
            <div>CNPJ {$cnpj} &nbsp;&nbsp; IE {$ie}</div>
          </td>
          <td class="c" style="width:20%;border-left:1px solid #000">
            <span class="b" style="font-size:11px">DANFE</span>
            <div style="font-size:5.5px">Documento Auxiliar da<br>Nota Fiscal Eletrônica</div>
            <div style="margin-top:3px">{$tipo}</div>
            <div class="b" style="margin-top:3px">Nº {$numero}</div>
            <div>SÉRIE {$serie} &nbsp; MODELO {$modelo}</div>
          </td>
          <td class="c" style="width:38%;border-left:1px solid #000">
            {$barrasHtml}
            <div style="font-size:6px;margin-top:2px">CHAVE DE ACESSO</div>
            <div class="b" style="font-size:6.5px">{$chaveFmt}</div>
            <div style="font-size:5.5px;margin-top:2px">
              Consulta de autenticidade no portal nacional da NF-e<br>
              www.nfe.fazenda.gov.br/portal ou no site da SEFAZ autorizadora
            </div>
          </td>
        </tr>
        <tr><td colspan="3" style="border-top:1px solid #000">
          <span class="rot">Protocolo de autorização de uso</span>
          <span class="val">{$protocolo} &nbsp;&nbsp; {$emissao}</span>
        </td></tr>
        </table>
        HTML;
    }

    private function destinatario(NotaFiscal $nota): string
    {
        $c = $nota->cliente;

        $doc = $this->documento((string) ($c?->cnpj ?: $c?->cpf ?: ''));
        $endereco = trim(sprintf('%s, %s %s', $c?->endereco ?? '', $c?->numero ?? '', $c?->complemento ?? ''), ' ,');

        return $this->quadro('Destinatário / Remetente', [
            ['Nome / Razão social', (string) ($c?->nome ?? ''), '46%'],
            ['CNPJ / CPF', $doc, '27%'],
            ['Inscrição estadual', (string) ($c?->inscricao_estadual ?? ''), '27%'],
        ], [
            ['Endereço', $endereco, '46%'],
            ['CEP', (string) ($c?->cep ?? ''), '27%'],
            ['UF', (string) ($c?->uf ?? ''), '27%'],
        ]);
    }

    private function totais(NotaFiscal $nota): string
    {
        return $this->quadro('Cálculo do imposto', [
            ['Valor dos produtos', $this->brl($nota->valor_produtos), '20%'],
            ['Desconto', $this->brl($nota->valor_desconto), '20%'],
            ['Frete', $this->brl($nota->valor_frete), '20%'],
            ['Base de cálculo ICMS', $this->brl($nota->valor_icms), '20%'],
            ['ICMS ST', $this->brl($nota->valor_icms_st), '20%'],
        ], [
            ['IPI', $this->brl($nota->valor_ipi), '20%'],
            ['PIS', $this->brl($nota->valor_pis), '20%'],
            ['COFINS', $this->brl($nota->valor_cofins), '20%'],
            ['', '', '20%'],
            ['Valor total da nota', $this->brl($nota->valor_total), '20%', true],
        ]);
    }

    private function itens(NotaFiscal $nota): string
    {
        $linhas = '';

        foreach ($nota->itens as $item) {
            $desc = e((string) ($item->produto->descricao ?? ''));
            $cod = e((string) ($item->produto_id ?? ''));
            $cfop = e((string) ($item->cfop ?? ''));
            $cst = e((string) ($item->cst_icms ?? ''));
            $qtd = $this->num($item->quantidade, 3);
            $unit = $this->num($item->valor_unitario, 4);
            $tot = $this->brl($item->valor_total);
            $bc = $this->brl($item->bc_icms);
            $vicms = $this->brl($item->valor_icms);
            $aliq = $this->num($item->aliq_icms, 2);

            $linhas .= <<<HTML
            <tr>
              <td>{$cod}</td><td>{$desc}</td><td class="c">{$cst}</td><td class="c">{$cfop}</td>
              <td class="r">{$qtd}</td><td class="r">{$unit}</td><td class="r">{$tot}</td>
              <td class="r">{$bc}</td><td class="r">{$vicms}</td><td class="r">{$aliq}</td>
            </tr>
            HTML;
        }

        if ($linhas === '') {
            $linhas = '<tr><td colspan="10" class="c">Sem itens</td></tr>';
        }

        return <<<HTML
        <div style="font-size:6px;font-weight:bold;text-transform:uppercase;margin-bottom:2px">
          Dados do produto / serviço
        </div>
        <table class="itens">
          <tr>
            <th>Código</th><th>Descrição</th><th>CST</th><th>CFOP</th>
            <th>Qtd</th><th>Vl. unit.</th><th>Vl. total</th>
            <th>BC ICMS</th><th>Vl. ICMS</th><th>Alíq.</th>
          </tr>
          {$linhas}
        </table>
        HTML;
    }

    private function rodape(NotaFiscal $nota): string
    {
        $motivo = trim((string) ($nota->motivo_rejeicao ?? ''));
        $obs = $motivo !== '' ? e($motivo) : '';

        return <<<HTML
        <table class="q"><tr><td>
          <span class="rot">Dados adicionais</span>
          <span class="val">{$obs}</span>
          <div style="height:22px"></div>
        </td></tr></table>
        HTML;
    }

    /**
     * Um quadro do DANFE: título + uma ou mais linhas de células rotuladas.
     *
     * @param  list<array{0:string,1:string,2:string,3?:bool}>  ...$linhas
     */
    private function quadro(string $titulo, array ...$linhas): string
    {
        $html = '<div style="font-size:6px;font-weight:bold;text-transform:uppercase;margin-bottom:2px">'
            .e($titulo).'</div><table class="q">';

        foreach ($linhas as $linha) {
            $html .= '<tr>';
            foreach ($linha as $celula) {
                [$rot, $val, $largura] = [$celula[0], $celula[1], $celula[2]];
                $negrito = ($celula[3] ?? false) ? ' b' : '';
                $html .= '<td style="width:'.$largura.';border:1px solid #000">'
                    .'<span class="rot">'.e($rot).'</span>'
                    .'<span class="val'.$negrito.'">'.e($val).'</span></td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    /** Número da nota no formato 000.000.000 do DANFE. */
    private function numeroFormatado(NotaFiscal $nota): string
    {
        return trim(chunk_split(str_pad((string) $nota->numero, 9, '0', STR_PAD_LEFT), 3, '.'), '.');
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

    private function brl(mixed $v): string
    {
        return number_format((float) $v, 2, ',', '.');
    }

    private function num(mixed $v, int $casas): string
    {
        return number_format((float) $v, $casas, ',', '.');
    }
}
