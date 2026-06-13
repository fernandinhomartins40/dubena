<?php

namespace App\Helpers\Utils;

use \Exception;
use App\Empresa;
/**
 * Contém funções úteis usadas para Boleto
 *
 * @author Flávio
 */
final class BoletoUtil
{

    public static function savePDF($empresa_id, $pdf, $filename)
    {
        $cnpj = onlyNumbers(Empresa::findOrFail($empresa_id)->cnpj);
        if (strlen($filename) === 0) {
            throw new Exception("Nome de arquivo não encontrado para armazenar o PDF");
        }

        $file = static::getPDFPath($cnpj, $filename);
        \Storage::disk('boletospdf')->put($file, $pdf);
    }

    public static function getPDFPath($cnpj, $filename)
    {
        $base = DIRECTORY_SEPARATOR . "pdf" . DIRECTORY_SEPARATOR . onlyNumbers($cnpj) . DIRECTORY_SEPARATOR;
        if (!is_dir(storage_path('boletos') . DIRECTORY_SEPARATOR . $base)) {
            mkdir(storage_path('boletos') . DIRECTORY_SEPARATOR . $base, 0777, true);
        }
        return $base . $filename . ".pdf";
    }

    public static function getPDFFile($empresa_id, $filename)
    {
        $cnpj = onlyNumbers(Empresa::findOrFail($empresa_id)->cnpj);
        $base = DIRECTORY_SEPARATOR . "pdf" . DIRECTORY_SEPARATOR . onlyNumbers($cnpj) . DIRECTORY_SEPARATOR;
        if (!is_dir(storage_path('boletos') . DIRECTORY_SEPARATOR . $base)) {
            mkdir(storage_path('boletos') . DIRECTORY_SEPARATOR . $base, 0777, true);
        }
        $file = storage_path('boletos') . DIRECTORY_SEPARATOR . $base . $filename . ".pdf";
        if(file_exists($file)){
            return $file;    
        } else {
            return false;
        }
        
    }


}
