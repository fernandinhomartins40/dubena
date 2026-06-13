<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../../bootstrap.php';
use NFePHP\NFe\ToolsNFe;
$nfe = new ToolsNFe('../../config/config-dubena.json');
$nfe->setModelo('55');
$aResposta = array();
$tpAmb = '2';
$recibo = '411110216336443';
$retorno = $nfe->sefazConsultaRecibo($recibo, $tpAmb, $aResposta);
echo '<br><br><pre>';
echo htmlspecialchars($nfe->soapDebug);
echo '</pre><br><br><pre>';
print_r($aResposta);
echo "</pre><br>";
