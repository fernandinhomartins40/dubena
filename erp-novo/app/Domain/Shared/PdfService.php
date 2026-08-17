<?php

namespace App\Domain\Shared;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Infraestrutura única de PDF operacional (T4.6 do PLANO_PRODUCAO).
 *
 * **Por que isto existe.** A auditoria: *"nenhum gerador de PDF operacional
 * existe no novo fora de relatórios"*. No modelo disk-gás o papel é o produto —
 * sem o boleto impresso o título não chega ao cliente, sem o recibo o caixa não
 * fecha, sem o vale impresso não há o que entregar.
 *
 * Havia um `RelatorioService::pdf()`, mas ele monta tabelas de relatório: layout
 * paisagem, cabeçalho de colunas, listagem. Documento operacional é outra coisa
 * — retrato, cabeçalho da empresa, blocos de dados, assinatura. Este serviço é
 * essa segunda forma, e não substitui aquele.
 *
 * Todos os documentos herdam o mesmo cabeçalho/rodapé para que a papelada saia
 * reconhecível como sendo da mesma empresa.
 */
class PdfService
{
    /**
     * Renderiza um documento operacional em PDF (retrato A4).
     *
     * @param  string  $titulo    aparece no topo do documento
     * @param  string  $corpoHtml conteúdo já escapado pelo chamador
     * @param  array{empresa?:string,cnpj?:string,endereco?:string,rodape?:string}  $contexto
     * @return string bytes do PDF
     */
    public function documento(string $titulo, string $corpoHtml, array $contexto = []): string
    {
        return Pdf::loadHTML($this->moldura($titulo, $corpoHtml, $contexto))
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * Documento em meia página (recibo, comanda) — dois por folha A4.
     *
     * Papel custa e a impressora do balcão é térmica ou jato: um recibo de caixa
     * ocupando uma folha inteira é desperdício que o operador nota todo dia.
     */
    public function meiaPagina(string $titulo, string $corpoHtml, array $contexto = []): string
    {
        return Pdf::loadHTML($this->moldura($titulo, $corpoHtml, $contexto, compacto: true))
            ->setPaper([0, 0, 595.28, 421.0])   // A5 paisagem (metade de um A4)
            ->output();
    }

    /**
     * Envelope HTML comum a todos os documentos.
     *
     * `DejaVu Sans` não é escolha estética: é a única fonte que o dompdf embarca
     * com cobertura de acentuação latina. Com a fonte padrão, "endereço" sai
     * "endere?o" no papel entregue ao cliente.
     *
     * @param  array<string,string>  $contexto
     */
    private function moldura(string $titulo, string $corpo, array $contexto, bool $compacto = false): string
    {
        $empresa = e($contexto['empresa'] ?? '');
        $cnpj = e($contexto['cnpj'] ?? '');
        $endereco = e($contexto['endereco'] ?? '');
        $rodape = e($contexto['rodape'] ?? '');
        $tituloEsc = e($titulo);

        $base = $compacto ? 10 : 11;
        $margem = $compacto ? '8mm' : '14mm';

        $cabecalhoEmpresa = $empresa !== ''
            ? "<div class='empresa'>{$empresa}"
                .($cnpj !== '' ? "<span class='doc'>CNPJ {$cnpj}</span>" : '')
                .($endereco !== '' ? "<div class='end'>{$endereco}</div>" : '')
                .'</div>'
            : '';

        $rodapeHtml = $rodape !== '' ? "<div class='rodape'>{$rodape}</div>" : '';
        $emissao = now()->format('d/m/Y H:i');

        return <<<HTML
        <html>
        <head><meta charset="utf-8"><style>
          @page { margin: {$margem}; }
          body { font-family: 'DejaVu Sans', sans-serif; font-size: {$base}px; color: #1e293b; }
          .empresa { font-size: 13px; font-weight: bold; border-bottom: 2px solid #1e293b;
                     padding-bottom: 6px; margin-bottom: 10px; }
          .empresa .doc { float: right; font-size: 10px; font-weight: normal; }
          .empresa .end { font-size: 9px; font-weight: normal; color: #475569; margin-top: 2px; }
          h1 { font-size: 14px; margin: 0 0 10px; text-transform: uppercase; letter-spacing: .5px; }
          table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
          td, th { padding: 4px 6px; vertical-align: top; }
          .campos td { border-bottom: 1px solid #e2e8f0; }
          .campos td.rot { color: #64748b; width: 32%; font-size: 10px; text-transform: uppercase; }
          .itens th { background: #1e293b; color: #fff; font-size: 9px;
                      text-transform: uppercase; text-align: left; }
          .itens td { border-bottom: 1px solid #e2e8f0; }
          .total { font-size: 13px; font-weight: bold; text-align: right; padding-top: 6px; }
          .assinatura { margin-top: 28px; border-top: 1px solid #1e293b; width: 60%;
                        padding-top: 4px; font-size: 9px; color: #475569; }
          .rodape { margin-top: 14px; font-size: 8px; color: #64748b;
                    border-top: 1px solid #e2e8f0; padding-top: 4px; }
          .emissao { float: right; font-size: 8px; color: #94a3b8; }
        </style></head>
        <body>
          {$cabecalhoEmpresa}
          <h1>{$tituloEsc}<span class="emissao">Emitido em {$emissao}</span></h1>
          {$corpo}
          {$rodapeHtml}
        </body>
        </html>
        HTML;
    }

    /**
     * Tabela rótulo→valor, o bloco mais comum destes documentos.
     *
     * @param  array<string,string|null>  $campos
     */
    public function campos(array $campos): string
    {
        $linhas = '';
        foreach ($campos as $rotulo => $valor) {
            if ($valor === null || $valor === '') {
                continue;   // campo vazio só ocupa espaço no papel
            }
            $linhas .= '<tr><td class="rot">'.e($rotulo).'</td><td>'.e((string) $valor).'</td></tr>';
        }

        return $linhas === '' ? '' : "<table class='campos'>{$linhas}</table>";
    }

    /**
     * Tabela de itens com cabeçalho.
     *
     * @param  list<string>  $cabecalhos
     * @param  list<list<string>>  $linhas
     */
    public function itens(array $cabecalhos, array $linhas): string
    {
        if ($linhas === []) {
            return '';
        }

        $th = implode('', array_map(fn ($c) => '<th>'.e($c).'</th>', $cabecalhos));
        $trs = '';
        foreach ($linhas as $linha) {
            $trs .= '<tr>'.implode('', array_map(fn ($c) => '<td>'.e((string) $c).'</td>', $linha)).'</tr>';
        }

        return "<table class='itens'><thead><tr>{$th}</tr></thead><tbody>{$trs}</tbody></table>";
    }

    /** Linha de assinatura. */
    public function assinatura(string $rotulo = 'Assinatura'): string
    {
        return "<div class='assinatura'>".e($rotulo).'</div>';
    }
}
