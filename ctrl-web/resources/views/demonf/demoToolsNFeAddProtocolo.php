<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../../bootstrap.php';
use NFePHP\NFe\ToolsNFe;
$nfe = new ToolsNFe('../../config/config-dubena.json');
$aResposta = array();
$indSinc = '0'; //0=asíncrono, 1=síncrono
$chave = '41170604190715000105550010000040641000000109';
$recibo = '411110216336443';
// $pathNFefile = "D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/assinadas/{$chave}-nfe.xml";
$pathNFefile = "C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/assinadas/{$chave}-nfe.xml"; // Ambiente Windows
if (! $indSinc) {
    // $pathProtfile = "D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/temporarias/201605/{$recibo}-retConsReciNFe.xml";
    $pathProtfile = "C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/temporarias/201706/{$recibo}-retConsReciNFe.xml"; // Ambiente Windows
} else {
    // $pathProtfile = "D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/temporarias/201605/{$recibo}-retEnviNFe.xml";
    $pathProtfile = "C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/temporarias/201706/{$recibo}-retEnviNFe.xml"; // Ambiente Windows
}
$saveFile = true;
$retorno = $nfe->addProtocolo($pathNFefile, $pathProtfile, $saveFile);
echo '<br><br><pre>';
echo htmlspecialchars($retorno);
echo "</pre><br>";
