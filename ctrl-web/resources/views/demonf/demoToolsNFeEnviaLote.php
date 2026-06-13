<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../../bootstrap.php';
use NFePHP\NFe\ToolsNFe;
$nfe = new ToolsNFe('../../config/config-dubena.json');
$nfe->setModelo('55');
$aResposta = array();
$chave = '41170604190715000105550010000040641000000109';
$tpAmb = '2';
// $aXml = file_get_contents("/var/www/nfe/homologacao/assinadas/{$chave}-nfe.xml"); // Ambiente Linux
// $aXml = file_get_contents("D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/assinadas/{$chave}-nfe.xml"); // Ambiente Windows
$aXml = file_get_contents("C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/assinadas/{$chave}-nfe.xml"); // Ambiente Windows
$idLote = '';
$indSinc = '0';//A variável $indSinc '1' para SERVIÇO SÍNCRONO, '0' para SERVIÇO ASSÍNCRONO.
$flagZip = false;
$retorno = $nfe->sefazEnviaLote($aXml, $tpAmb, $idLote, $aResposta, $indSinc, $flagZip);
echo '<br><br><pre>';
echo htmlspecialchars($nfe->soapDebug);
echo '</pre><br><br><pre>';
print_r($aResposta);
echo "</pre><br>";
