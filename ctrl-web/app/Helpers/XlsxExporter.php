<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Geração de planilhas .xlsx via PhpSpreadsheet.
 *
 * Substitui o Maatwebsite/Excel 2.1 (`Excel::create()->sheet()->download()`) e o
 * PHPExcel direto (`\PHPExcel_*`), ambos EOL e incompatíveis com Laravel 6 / a
 * lib 3.x. Centraliza o que era repetido nos exports (logo da empresa via
 * MemoryDrawing e o streaming do arquivo), mantendo o resultado equivalente.
 */
class XlsxExporter
{
    /**
     * Insere o logo da empresa (binário PNG) numa célula da planilha, como o
     * código legado fazia com PHPExcel_Worksheet_MemoryDrawing.
     *
     * @param Worksheet $sheet
     * @param string|null $logoBinario  conteúdo binário da imagem (empresa->logo)
     * @param string $coordenada        ex.: 'A1'
     */
    public static function inserirLogo(Worksheet $sheet, $logoBinario, $coordenada = 'A1')
    {
        if (empty($logoBinario)) {
            return;
        }
        $image = imagecreatefromstring($logoBinario);
        imagesavealpha($image, true);

        $drawing = new MemoryDrawing();
        $drawing->setName('logo');
        $drawing->setImageResource($image);
        $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
        $drawing->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT);
        $drawing->setWidthAndHeight(148, 70);
        $drawing->setResizeProportional(true);
        $drawing->setCoordinates($coordenada);
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(5);
        $drawing->setWorksheet($sheet);
    }

    /**
     * Faz o download da planilha como .xlsx (substitui ->download('xlsx')).
     *
     * @param Spreadsheet $spreadsheet
     * @param string $nomeArquivo  sem extensão
     * @return StreamedResponse
     */
    public static function download(Spreadsheet $spreadsheet, $nomeArquivo)
    {
        $writer = new Xlsx($spreadsheet);
        $filename = $nomeArquivo . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
