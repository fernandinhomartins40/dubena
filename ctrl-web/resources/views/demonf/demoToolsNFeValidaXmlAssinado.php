<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../../bootstrap.php';
use NFePHP\NFe\ToolsNFe;
$nfeTools = new ToolsNFe('../../config/config-dubena.json');
$nfeTools->setModelo('55');
$chave = '41170604190715000105550010000040641000000109';
$tpAmb = '2';
// $xml = "/var/www/nfe/homologacao/assinadas/{$chave}-nfe.xml"; // Ambiente Linux
// $xml = "D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/assinadas/{$chave}-nfe.xml"; // Ambiente Windows
$xml = "C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/assinadas/{$chave}-nfe.xml"; // Ambiente Windows
if (! $nfeTools->validarXml($xml) || sizeof($nfeTools->errors)) {
    echo "<h3>Eita !?! Tem bicho na linha .... </h3>";
    foreach ($nfeTools->errors as $erro) {
        if (is_array($erro)) {
            foreach ($erro as $err) {
                echo "$err <br>";
            }
        } else {
            echo "$erro <br>";
        }
    }
    exit;
}
echo "NFe Valida !";
