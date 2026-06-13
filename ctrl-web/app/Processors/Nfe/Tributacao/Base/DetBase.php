<?php

namespace App\Processors\Nfe\Tributacao\Base;

use App\Helpers\Utils\NfUtil;
use App\Nfemitidaitem;
use App\Nfrecebidaitem;
use App\Processors\Nfe\Tributacao\ImpostoDB;
use App\Processors\Nfe\Tributacao\XmlTags\TagIcms;
use App\Processors\Nfe\Tributacao\XmlTags\TagProd;
use DB;
use Illuminate\Support\Collection;

/**
 * @author Jeferson
 */
class DetBase
{

    /**
     * @var
     */
    public $prod;

    /**
     * @var \Illuminate\Support\Collection
     */
    public $imposto;

    /**
     * @var
     */
    public $nItem;

    /**
     * @var Nfemitidaitem|Nfrecebidaitem
     */
    public $nfItem;

    /**
     * @var
     */
    public $cest;

    /**
     * DetBase constructor.
     * @param $nItem
     * @param $cest
     */
    public function __construct($nItem, $cest)
    {
        $this->nItem = $nItem;
        $this->cest = $cest;
        $imposto = [
            'icms'       => null,
            'pis'        => null,
            'cofins'     => null,
            'icmsUFDest' => null,
            'vTotTrib'   => null,
            'nfimposto'  => null,
            'ipi'        => null,
            'ibscbs'     => null
        ];
        $this->imposto = collect($imposto);
    }

    /**
     * seta o icms
     * @param TagIcms $icms
     */
    public function setIcms($icms)
    {
        $exception = $this->precisionExceptions();
        $this->imposto['icms'] = NfUtil::formatObjValues($icms, 2, $exception);
    }

    /**
     * @param PisBase $pis
     * @param CofinsBase $cofins
     */
    public function setPisCofins($pis, $cofins)
    {
        $this->imposto['cofins'] = NfUtil::formatObjValues($cofins);

        $this->imposto['pis'] = NfUtil::formatObjValues($pis);
    }

    /**
     * seta os valores da tag ICMSUFDest
     * @param \stdClass $icmsUfDest
     */
    public function setIcmsUfDest($icmsUfDest)
    {
        $this->imposto['icmsUFDest'] = $icmsUfDest;
    }

    /**
     * seta o valor todal dos tributos da Lei de Olho no Imposto
     * @param float $vTotTrib
     */
    public function setVTotTrib($vTotTrib)
    {
        $this->imposto['vTotTrib'] = $vTotTrib;
    }

    /**
     * seta o nfimposto vindo do banco
     * @param DB|\App\Nfimposto|ImpostoDB $nfimposto
     */
    public function setNfImposto($nfimposto)
    {
        $this->imposto['nfimposto'] = $nfimposto;
    }

    /**
     * seta os itens da nf vindos do banco
     * @param Collection|\App\Nfemitidaitem|\App\Nfrecebidaitem $nfitem
     */
    public function setNfItem($nfitem)
    {
        $this->nfItem = $nfitem;
    }

    /**
     * seta e formata os valores do objeto de produto
     * @param TagProd $prod
     */
    public function setProd($prod)
    {
        $this->prod = NfUtil::formatObjValues($prod, 2, ['vUnTrib' => ['precision' => 10]]);
    }

    /**
     * @param $ipi
     */
    public function setIPI($ipi)
    {
        $this->imposto['ipi'] = $ipi;
    }

    public function setIbsCbs($ibscbs)
    {
        $exception = $this->precisionExceptions();
        $this->imposto['ibscbs'] = NfUtil::formatObjValues($ibscbs, 2, $exception);
    }

    /**
     * retorna qualquer atributo da classe NfeImposto
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->imposto->get($key);
    }

    private function precisionExceptions()
    {
        return [
            'adRemICMSRet' => ['precision' => 4],
        ];
    }
}
