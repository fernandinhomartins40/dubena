<?php

namespace App\Processors\Nfe\Tools;

use App\Empresa;
use App\Nfemitida;
use Illuminate\Support\Collection;
use NFePHP\NFe\Tools as ToolsNFE;
use NFePHP\Common\Certificate;
use App\Helpers\Utils\NfUtil as Util;
use \Exception;

class Tools
{

    /**
     * @var ToolsNFE
     */
    private $tools;

    /**
     * @var bool
     */
    private $inContingency = false;

    /**
     * @param null|Nfemitida $nf
     * @param null|string $configJson
     * @param null $cnpj
     * @param null|Collection|Empresa $empresa
     * @return ToolsNFE
     * @throws Exception
     */
    public function mount($nf = null, $configJson = null, $cnpj = null, $empresa = null)
    {
        if ($nf !== null) {
            $cnpj = $nf->emitcnpj;
            $empresa = $nf->empresa;
        } elseif ($cnpj === null || $empresa === null) {
            throw new Exception("Impossível gerar as ferramentas para comunicação com o Sefaz.");
        }
        $tools = new ToolsNFE($configJson, $this->getCertificate($cnpj, $empresa));
        if ($empresa->contingenciaemissao) {
            if ($nf === null) {
                throw new Exception("Impossível entrar em modo de contingência na inutilização");
            }
            $this->inContingency = true;
            $tools->contingency->load(Util::contingency($nf));
        }
        $this->tools = $tools;
        return $tools;
    }

    /**
     * @param $cnpj
     * @param $empresa
     * @return Certificate
     * @throws Exception
     */
    private function getCertificate($cnpj, $empresa)
    {
        try {
            $pfxContent = file_get_contents(getPathCertificateNFe() . $cnpj . '.pfx');
            return Certificate::readPfx($pfxContent, customDecrypt($empresa->nfesenhapfx, 6));
        } catch (\Exception $ex) {
            $msg = "Ocorreu um erro ao tentar buscar o Certificado Digital da empresa, "
                . "contate o suporte: " . $ex->getMessage();
            throw new \Exception($msg);
        }
    }
}
