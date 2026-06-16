<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use App\Helpers\XlsxExporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Caracterização — FASE 2 (Salto 1, migração de exports).
 *
 * Garante que o XlsxExporter (substituto do Maatwebsite/Excel 2.1 + PHPExcel)
 * realmente gera arquivos no ambiente Laravel 6 / PhpSpreadsheet: xlsx e pdf
 * (via Pdf\Mpdf). Os exports de relatório/DRE/Balanço dependem disto.
 *
 * PHPUnit 8.5 / Laravel 6 / PHP 7.4.
 */
class XlsxExporterTest extends TestCase
{
    private function planilhaSimples()
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([
            ['Cabeçalho'],
            ['Col A', 'Col B'],
            ['valor 1', 123.45],
        ], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(30);
        return $ss;
    }

    public function testSalvarEmExportsGeraXlsx()
    {
        $nome = 'teste_export_' . uniqid();
        $path = XlsxExporter::salvarEmExports($this->planilhaSimples(), $nome, 'xlsx');

        $this->assertNotNull($path, 'não gerou o xlsx');
        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        @unlink($path);
    }

    public function testSalvarEmExportsGeraPdf()
    {
        $nome = 'teste_export_' . uniqid();
        $path = XlsxExporter::salvarEmExports($this->planilhaSimples(), $nome, 'pdf');

        $this->assertNotNull($path, 'não gerou o pdf (writer Mpdf)');
        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        @unlink($path);
    }

    public function testDownloadRetornaResponseXlsx()
    {
        $resp = XlsxExporter::download($this->planilhaSimples(), 'arquivo');
        $this->assertInstanceOf(
            \Symfony\Component\HttpFoundation\StreamedResponse::class, $resp
        );
        $cd = $resp->headers->get('Content-Disposition');
        $this->assertStringContainsString('arquivo.xlsx', $cd);
    }
}
