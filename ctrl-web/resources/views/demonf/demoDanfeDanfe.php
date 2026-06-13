<?php
/**
 * ATENÇÃO : Esse exemplo usa classe PROVISÓRIA que será removida assim que
 * a nova classe DANFE estiver refatorada e a pasta EXTRAS será removida.
 */
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../../bootstrap.php';
use NFePHP\NFe\ToolsNFe;
use NFePHP\Extras\Danfe;
use NFePHP\Common\Files\FilesFolders;
$nfe = new ToolsNFe('../../config/config-dubena.json');
$chave = '41170604190715000105550010000040641000000109';
// $xmlProt = "D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/enviadas/aprovadas/201605/{$chave}-protNFe.xml";
$xmlProt = "C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/enviadas/aprovadas/201706/{$chave}-protNFe.xml"; // Ambiente Windows
// Uso da nomeclatura '-danfe.pdf' para facilitar a diferenciação entre PDFs DANFE e DANFCE salvos na mesma pasta...
// $pdfDanfe = "D:/xampp/htdocs/GIT-nfephp-org/nfephp/xmls/NF-e/homologacao/pdf/201605/{$chave}-danfe.pdf";
$diretorio = "C:/apache24/htdocs/ctrl2/vendor/nfephp-org/nfephp/xmls/nfes/homologacao/pdf/201706/";
$pdfDanfe = $diretorio . "{$chave}-danfe.pdf"; // Ambiente Windows
if(!file_exists($diretorio))
  mkdir($diretorio);
$docxml = FilesFolders::readFile($xmlProt);
$danfe = new Danfe($docxml, 'P', 'A4', $nfe->aConfig['aDocFormat']->pathLogoFile, 'I', '');
$id = $danfe->montaDANFE();
$salva = $danfe->printDANFE($pdfDanfe, 'F'); //Salva o PDF na pasta
$abre = $danfe->printDANFE("{$id}-danfe.pdf", 'I'); //Abre o PDF no Navegador
