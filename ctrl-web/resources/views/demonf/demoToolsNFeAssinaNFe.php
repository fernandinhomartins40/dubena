<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../../bootstrap.php';
use NFePHP\NFe\ToolsNFe;
$nfe = new ToolsNFe('../../config/config-dubena.json');
$chave = '41170604190715000105550010000040641000000109';
// $filename = "/var/www/nfe/homologacao/entradas/{$chave}-nfe.xml"; // Ambiente Linux
// $filename = "D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/entradas/{$chave}-nfe.xml"; // Ambiente Windows
$filename = "C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/entradas/{$chave}-nfe.xml"; // Ambiente Windows
$xml = file_get_contents($filename);
$xml = $nfe->assina($xml);
// $filename = "/var/www/nfe/homologacao/assinadas/{$chave}-nfe.xml"; // Ambiente Linux
// $filename = "D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/assinadas/{$chave}-nfe.xml"; // Ambiente Windows
$filename = "C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/assinadas/{$chave}-nfe.xml"; // Ambiente Windows
file_put_contents($filename, $xml);
chmod($filename, 0777);
echo $chave;
