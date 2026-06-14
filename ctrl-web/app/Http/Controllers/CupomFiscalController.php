<?php

namespace App\Http\Controllers;

use App\Centrocusto;
use App\Cliente;
use App\Configuracoesgerais;
use App\CupomFiscal;
use App\CupomFiscalItem;
use App\CupomFiscalParcela;
use App\Empresa;
use App\Exceptions\SatException;
use App\Financeiro;
use App\Financeiroparcela;
use App\Financeirorateio;
use App\Helpers\Utils\NfUtil;
use App\Http\Requests\CupomFiscalRequest;
use App\Nfimposto;
use App\Nfoperacao;
use App\Planoconta;
use App\Processors\financeiroProcessor;
use App\Processors\Nfe\Tributacao\ImpostoDB;
use App\Produto;
use App\Produtoleiimposto;
use App\Repository\SelectRepository;
use App\User;
use Auth;
use Carbon\Carbon;
use DB;
use Exception;
use Form;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Input;
use PHPCFe\CFe\Make;
use PHPCFe\Tags\{COFINS, Dest, Det, Emit, Entrega, ICMS, Ide, Imposto, InfAdic, MP, PIS, Prod};
use Ratchet\Client;
use Ratchet\MessageInterface;
use Ratchet\RFC6455\Messaging\Message;
use React\Promise\Promise;
use Session;
use PHPCFe;
use stdClass;

class CupomFiscalController extends Controller
{

    /**
     * versão do layout do sat
     */
    public const VERSAO_LAYOUT = "0.07";

    /**
     * @var Configuracoesgerais
     */
    public $config;

    /**
     * @var int
     */
    public $resultsPerPage = 100;

    /**
     * @var int
     */
    public $maxResults = 20000;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var User
     */
    private $user;

    public function __construct()
    {
        $this->config = Configuracoesgerais::first();
    }

    /**
     * @param CupomFiscal $cfe
     * @return Factory|View
     * @throws AuthorizationException
     */
    public function index(CupomFiscal $cfe)
    {
        $this->user = Auth::user();
        $this->authorize('view', $cfe);

        $cfes = $this->filter();
        $currentPage = (int) Input::get('page', 1);
        $totalPages = ceil($cfes->total / $this->resultsPerPage);
        $data = $this->fillDataIndex($cfes);

        $fullUrl = route('satcfe.create') . "?" . (request()->getQueryString() ? request()->getQueryString() : "");

        return view('satcfe.cfe_index')->with(compact('data', 'fullUrl', 'currentPage', 'totalPages'));
    }

    /**
     * @param $cfes
     * @return Collection
     */
    private function fillDataIndex($cfes)
    {
        $redirectUrl = \Request::getQueryString() ? "?" . \Request::getQueryString() : "";
        $data = collect([]);
        foreach ($cfes->cf as $cfe) {
            $stdNf = new stdClass();
            $stdNf->id = $cfe->id;
            $stdNf->ncfe = $cfe->ncfe;
            $stdNf->datahoraemissao = requestDataOracleSemHora($cfe->demi)  . " " . $cfe->hemi;
            $stdNf->cliente = $cfe->destxnome;

            $stdNf->status = $cfe->status != null ? $cfe->status : '';
            $stdNf->status_descricao = $cfe->status != null ? $cfe->status_descricao : '';

            $stdNf->vcfe = requestNumeroDecimalOracle($cfe->vcfe);

            $stdNf->buttons = $this->getButtonActionIndex(
                "btnVisualizar",
                "Visualizar",
                "<span class='fa fa-eye fa-lg'></span>",
                "btn btn-nw-buscas btn-xs",
                route("satcfe.show", $cfe->id) . $redirectUrl
            );

            if ($this->user->can('update', $cfe))
                $stdNf->buttons .= $this->getButtonActionIndex(
                    "btnEditar",
                    "Editar",
                    "<span class='fa fa-pencil-square-o fa-lg'></span>",
                    "btn btn-nw-geral btn-xs",
                    route("satcfe.edit", $cfe->id) . $redirectUrl
                );

            $data->push($stdNf);
        }
        return $data;
    }

    /**
     * @param $id
     * @param $title
     * @param $content
     * @param $class
     * @param string $href
     * @param string $onclick
     * @return string
     */
    private function getButtonActionIndex($id, $title, $content, $class, $href = '', $onclick = '')
    {
        $htmlButton = [
            'id'             => $id,
            'class'          => $class,
            'onclick'        => $onclick,
            'data-toggle'    => 'tooltip',
            'data-trigger'   => 'hover',
            'data-placement' => 'bottom',
            'title'          => $title,
        ];
        if ($href) {
            $attr = "";
            foreach ($htmlButton as $key => $v) {
                $attr .= $key . '="' . $v . '" ';
            }
            $htmlElement = "<a href='" . $href . "' " . $attr . ">" . $content . "</a>";
        } else {
            $htmlElement = Form::button($content, $htmlButton)->toHtml();
        }
        return $htmlElement . "&nbsp;";
    }

    /**
     * @return object
     */
    public function filter()
    {
        $cfe = CupomFiscal::whereEmpresaId(Session::get("empresa_padrao")->id);
        $count = (clone $cfe)->selectRaw('count(id) as count, sum(CASE WHEN status = 6000 THEN icmsvcfe ELSE 0 END) AS vcfe')->get()->first();
        $countTotal = ! is_null($count) ? $count->count : 0;
        $vCFe = ! is_null($count) ? $count->vcfe : 0;

        if ($countTotal > $this->maxResults) {
            $msg = "O número de registros é muito grande e pode causar uma sobrecarga. "
                . "Por favor, utilize os filtros para trazer um número menor resultados.";
            Session::flash('message_danger', $msg);
            return (object) [
                'cf'     => collect([]),
                'vcfe'   => 0,
                'total'  => 0
            ];
        }

        $sort = Input::get('sort', 'ncfe');
        $order = Input::get('order', 'desc');

        if ($sort == 'cliente')
            $sort = 'destxnome';

        return (object) [
            'cf'     => $cfe->get()->sortBy($sort, SORT_NATURAL, strtoupper($order) !== 'ASC'),
            'vcfe'   => $vCFe,
            'total'  => $countTotal
        ];
    }

    /**
     *
     * @param CupomFiscalRequest $request
     * @param CupomFiscal $cfe
     * @return string
     * @throws AuthorizationException
     * @throws Exception
     */
    public function store(CupomFiscalRequest $request, CupomFiscal $cfe)
    {
        $this->authorize('create', $cfe);
        return $this->updateOrCreate($request);
    }

    /**
     * @param CupomFiscalRequest $request
     * @param $id
     * @param CupomFiscal $cfe
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function update(CupomFiscalRequest $request, $id, CupomFiscal $cfe)
    {
        $this->authorize('update', $cfe);
        return $this->updateOrCreate($request, $id);
    }

    /**
     * @param CupomFiscalRequest $request
     * @param null $id
     * @return RedirectResponse
     */
    private function updateOrCreate($request, $id = null) {

        try {
            DB::beginTransaction();
            if (! Session::get("empresa_padrao")->usasat) {
                throw new Exception("A empresa atual não está configurada para usar SAT.");
            }

            $data = $request->only((new CupomFiscal())->getFillable());
            $data["produtosjson"] = $request->only("produtosJson")["produtosJson"];
            if (! $this->validateCustom($data))
                throw new Exception("");

            if ($id === null) {
                $cfe = CupomFiscal::create($data);
            } else {
                $cfe = CupomFiscal::find($id);
                $cfe->update($data);
            }

            $dataFin = $request->only([
                "descricaofinanceiro", "condicaopagamento_id", "nfoperacao_id", "vmp", "cliente_id"
            ]);
            $this->gerarFinanceiro($dataFin, $cfe);

            $cfe->update([
                "xml" => $this->mountXml($cfe, $this->createItems($cfe))
            ]);

            DB::commit();

            /** @noinspection PhpUndefinedMethodInspection */
            return redirect()->route('satcfe.show', $cfe->id)->withMessageSuccess("Salvo com Sucesso!");
        } catch (Exception $e) {
            DB::rollBack();
            if (empty($this->errors)) {
                $this->errors[] = $e->getMessage();
                satLog($e->getTraceAsString());
            } else {
                satLog(implode(PHP_EOL, $this->errors));
            }
            return redirect()
                ->route(! is_null($id) ? 'satcfe.edit' : 'satcfe.create', $id)
                ->withErrors($this->errors)
                ->withInput();
        }
    }

    /**
     * @param CupomFiscal $cfe
     * @return Collection
     * @throws Exception
     */
    private function createItems(CupomFiscal $cfe)
    {
        $empresa = Session::get("empresa_padrao");
        $dest = $cfe->cliente;
        $items = $this->itemsJsonToStd($cfe->produtosjson);

        $operacoes = Nfoperacao::whereIn("id", $items->pluck("nfoperacao_id"))->get();
        $produtosDb = Produto::whereIn("id", $items->pluck("produto_id"))->get();
        $impostos = Nfimposto::whereIn('nfoperacao_id', $items->pluck("nfoperacao_id"))
            ->whereIn('nfgrupofiscal_id', $items->pluck("nfgrupofiscal_id"))
            ->where('empresa_id', $empresa->id)
            ->where('grupo_id', $empresa->grupo_id)
            ->with('nfoperacao', 'nfcofins', 'pfnfcofins', 'nficms', 'pfnficms', 'nfpis', 'pfnfpis', 'nfimpostoestado.pfnficms', 'nfimpostoestado.nficms')
            ->get();
        $allProdLei = DB::table('produtoleiimpostos')->where("uf", $empresa->uf)
            ->whereIn('ncm', $items->pluck("ncm"))->get();

        $totItems = count($items);
        $allItems = collect([]);
        $restVDesc = $cfe->icmsvdesc;
        $restVOutro = $cfe->icmsvoutro;
        foreach ($items as $item) {
            try {

                $produtoDb = $this->getProdDb($produtosDb, $item);
                $operacao = $this->getNfOperacao($operacoes, $item);
                $imposto = $this->getNfImposto($impostos, $item, $empresa->uf, $dest->uf);

                if ($operacao->deolhonoimposto) {
                    $lei = $this->getProdLeiImpostoByNcm($allProdLei, $produtoDb->ncm);
                } else {
                    $lei = null;
                }

                $cfeI = $this->setItem(
                    $item, $produtoDb, $imposto, $operacao, $cfe, $totItems, $lei, $restVDesc, $restVOutro
                );
                $allItems->push($cfeI);
                $restVDesc -= $cfeI->vdesc;
                $restVOutro -= $cfeI->voutro;

            } catch (Exception $e) {
                $this->errors[] = "Erro ao validar item " . $item->nItem . ": ". $e->getMessage();
                continue;
            }
        }
        if (! empty($this->errors)) {
            throw new Exception();
        }
        return $allItems;
    }

    /**
     * @param StdClass $item
     * @param Produto $produtoDb
     * @param ImpostoDB $imposto
     * @param Nfoperacao $operacao
     * @param CupomFiscal $cfe
     * @param float|int $totItems
     * @param Produtoleiimposto $lei
     * @param $restVDesc
     * @param $restVOutro
     * @return CupomFiscalItem
     */
    private function setItem($item, $produtoDb, $imposto, $operacao, $cfe, $totItems, $lei, $restVDesc, $restVOutro)
    {
        //Campos gravados pelo sat: [vprod, vitem, vratdesc, vratacr, vicms, vpis, vcofins]
        $cfeItem = new CupomFiscalItem();
        $cfeItem->nfimposto_id = $imposto->get("id");
        $cfeItem->cfop = $operacao->cfop;
        $cfeItem->nitem = $item->nItem;

        $cfeItem->indregra = NfUtil::isOpComb($cfeItem->cfop, $produtoDb->nfecprodanp) ? "T" : "A";
        $cfeItem->empresa_id = $cfe->empresa_id;
        $cfeItem->grupo_id = $cfe->grupo_id;
        $cfeItem->cupomfiscal_id = $cfe->id;
        $cfeItem->nfoperacao_id = $item->nfoperacao_id;
        $cfeItem->setor_id = $item->setor_id;
        $cfeItem->cprod = $item->produto_id;
        $cfeItem->xprod = $item->produto;

        $vTotI = $this->setItemValues(
            $cfeItem, $cfe, $item, $restVDesc, $restVOutro, $item->nItem === $totItems
        );

        $this->setIcmsItem($cfeItem, $imposto);
        $this->setPisItem($cfeItem, $imposto, $vTotI);
        $this->setCofinsItem($cfeItem, $imposto, $vTotI);

        $cfeItem->ucom = $item->unidademedidasigla;
        $cfeItem->cean = $produtoDb->cean;
        $cfeItem->ncm = $produtoDb->ncm;
        $cfeItem->cest = $produtoDb->nfcest;


        $inf = "NCM: " . $item->ncm;
        // Calculo de carga tributária similar ao IBPT - Lei 12.741/12
        if ($lei && $operacao->deolhonoimposto) {
            $cfeItem->vaproxtribest = sprintf('%0.2f', $vTotI * $lei->aliqestadual / 100);
            $cfeItem->vaproxtribmun = sprintf('%0.2f', $vTotI * $lei->aliqmunicipal / 100);
            $cfeItem->vaproxtribfed = sprintf('%0.2f', $vTotI * $lei->aliqnac / 100);
            $cfeItem->vitem12741 = $cfeItem->vaproxtribest + $cfeItem->vaproxtribmun + $cfeItem->vaproxtribfed;
        } else {
            $cfeItem->vaproxtribest = "0.00";
            $cfeItem->vaproxtribmun = "0.00";
            $cfeItem->vaproxtribfed = "0.00";
            $cfeItem->vitem12741 = 0;
        }

        $cfeItem->vitem12741 = sprintf('%0.2f', strval($cfeItem->vitem12741));
        if ($operacao->deolhonoimposto) {
            $chave = $lei ? $lei->chave : " ";
            $inf .= " - Valor Aprox. Tributos R$ {$cfeItem->vitem12741} - {$cfeItem->vaproxtribfed} Federal, "
                . "{$cfeItem->vaproxtribest1} Estadual e {$cfeItem->vaproxtribmun} Municipal. Fonte: IBPT {$chave}.";

        }
        $cfeItem->infadprod = $inf;

        $cfeItem->save();

        return $cfeItem;
    }

    /**
     * @param Collection $produtosDb
     * @param $item
     * @return mixed
     * @throws Exception
     */
    private function getProdDb($produtosDb, $item)
    {
        $produtoDb = $produtosDb->where("id", $item->produto_id)->first();
        if (! $produtoDb) {
            throw new Exception("Não foi possível encontrar o item " . $item->nItem . " no banco de dados");
        }
        return $produtoDb;
    }

    /**
     * @param Collection $impostos
     * @param stdClass $item
     * @param string $emituf
     * @param string $destuf
     * @return ImpostoDB
     * @throws Exception
     */
    private function getNfImposto($impostos, $item, $emituf, $destuf)
    {
        $imposto = $impostos->first(function ($imp) use ($item) {
            return $item->nfoperacao_id == $imp->nfoperacao_id && $item->nfgrupofiscal_id == $imp->nfgrupofiscal_id;
        });
        if (! $imposto) {
            throw new Exception("Não foi possível encontrar o imposto do  item " . $item->nItem . " no banco de dados");
        }
        return new ImpostoDB([
            'emituf'            => $emituf,
            'destuf'            => $destuf,
            'idDest'            => 1,//somente operaçao interna
            'isConsumidorFinal' => true,
            'produto'           => [
                1   => $item->nfoperacao,
                5   => $item->produto,
                16  => $item->nfgrupofiscal
            ],
            'nfimposto'         => $imposto
        ]);
    }

    /**
     * @param Collection $operacoes
     * @param $item
     * @return mixed
     * @throws Exception
     */
    private function getNfOperacao($operacoes, $item)
    {
        $operacao = $operacoes->where("id", $item->nfoperacao_id)->first();
        if (! $operacao) {
            throw new Exception("Operação não encontrada para o item " . $item->nItem);
        }
        return $operacao;
    }

    /**
     * @param $cfeItem
     * @param $cfe
     * @param $item
     * @param float|int $restVDesc
     * @param float|int $restVOutro
     * @param bool $isLastItem
     * @return float|int
     */
    private function setItemValues(&$cfeItem, $cfe, $item, $restVDesc, $restVOutro, $isLastItem)
    {
        if ($cfeItem->indregra == "T") {
            $cfeItem->vuncom = formatDecimalPlaces($item->valor, 3);
            $cfeItem->qcom = formatDecimalPlaces($item->qtde, 4);
            $vTotI = trunc($cfeItem->qcom * $cfeItem->vuncom);

            $percentVDesc = $cfe->icmsvprod / ($vTotI ? $vTotI : 1);
            $cfeItem->vdesc = formatDecimalPlaces(
                $isLastItem ? $cfe->icmsvdesc - $restVDesc : $cfe->icmsvdesc * $percentVDesc, 2
            );

            $percentVOutro = $cfe->icmsvcfe / ($vTotI ? $vTotI : 1);
            $cfeItem->voutro = formatDecimalPlaces(
                $isLastItem ? $cfe->icmsvoutro - $restVOutro : $cfe->icmsvoutro * $percentVOutro, 2
            );
        } else {
            $cfeItem->vuncom = formatDecimalPlaces($item->valor, 3);
            $cfeItem->qcom = formatDecimalPlaces($item->qtde, 4);

            $vTotI = round($cfeItem->qcom * $cfeItem->vuncom);

            $percentVDesc = $cfe->icmsvprod / ($vTotI ? $vTotI : 1);

            $cfeItem->vdesc = formatDecimalPlaces(
                $isLastItem ? $cfe->icmsvdesc - $restVDesc : $cfe->icmsvdesc * $percentVDesc
            );

            $percentVOutro = $cfe->icmsvcfe / ($vTotI ? $vTotI : 1);
            $cfeItem->voutro = formatDecimalPlaces(
                $isLastItem ? $cfe->icmsvoutro - $restVOutro : $cfe->icmsvoutro * $percentVOutro
            );
        }
        return $vTotI;
    }

    /**
     * @param $cfeItem
     * @param ImpostoDB $imposto
     */
    private function setIcmsItem(&$cfeItem, $imposto)
    {
        $cfeItem->csticms = $imposto->get("codigocst");
        $cfeItem->icmsorig = $imposto->get("origemicms");
        $cfeItem->picms = formatDecimalPlaces($imposto->get("nficmsaliq"));
    }

    /**
     * @param $cfeItem
     * @param ImpostoDB $imposto
     * @param $vTotI
     */
    private function setPisItem(&$cfeItem, $imposto, $vTotI)
    {
        $cfeItem->cstpis = $imposto->get("codigopis");
        $cfeItem->ppis = formatDecimalPlaces($imposto->get("nfpisaliq") / 100);
        $vBCPis = $vTotI - NfUtil::calcImpostoProp($vTotI, 100 - $imposto->get("nfpisbase"));
        $cfeItem->vbcpis = formatDecimalPlaces($vBCPis);
    }

    /**
     * @param $cfeItem
     * @param ImpostoDB $imposto
     * @param $vTotI
     */
    private function setCofinsItem(&$cfeItem, $imposto, $vTotI)
    {
        $cfeItem->cstcofins = $imposto->get("codigocofins");
        $cfeItem->pcofins = formatDecimalPlaces($imposto->get("nfcofinsaliq") / 100);
        $vBCCofins = $vTotI - NfUtil::calcImpostoProp($vTotI, 100 - $imposto->get("nfcofinsbase"));
        $cfeItem->vbccofins = formatDecimalPlaces($vBCCofins);
    }

    /**
     * @param $json
     * @return Collection
     * @throws Exception
     */
    private function itemsJsonToStd($json)
    {
        $produtos = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Não foi possível verificar os protudos do CF-e" . json_last_error_msg());
        }
        $items = collect([]);
        $nItem = 0;
        foreach ($produtos as $item) {
            $newItem = new stdClass();
            $nItem++;
            $newItem->nItem = $nItem;
            $newItem->nfoperacao_id = $item[0];
            $newItem->nfoperacao = $item[1];
            $newItem->setor_id = $item[2];
            $newItem->setor = $item[3];
            $newItem->produto_id = $item[4];
            $newItem->produto = $item[5];
            $newItem->valor = $item[6];
            $newItem->qtde = $item[7];
            $newItem->operacao = $item[8];
            $newItem->ncm = $item[9];
            $newItem->unidademedidasigla = $item[10];
            $newItem->ean = $item[11];
            $newItem->nfeextipi = $item[12];
            $newItem->nfcest = $item[13];
            $newItem->nfedescricaofiscal = $item[14];
            $newItem->nfgrupofiscal_id = $item[15];
            $newItem->nfgrupofiscal = "Grupo Fiscal código [" . $newItem->nfgrupofiscal_id . "]";
            $items->push($newItem);
        }
        return $items;
    }

    /**
     * @param Collection $allProdLei
     * @param $ncm
     * @return mixed
     */
    private function getProdLeiImpostoByNcm($allProdLei, $ncm)
    {
        $lei = $allProdLei->where('ncm', $ncm)->first();
        if (! $lei) {
            Session::flash(
                "message_info", "NCM " . $ncm . " não encontrado na tabela " .
                "ProdutoLeiImpostos em desacordo com a lei 12.741/2012. Entre em contato com o suporte imediatamente."
            );
        }
        return $lei;
    }

    /**
     * @param CupomFiscal $cfe
     * @param $cfeItems
     * @return string
     * @throws PHPCFe\Exceptions\InvalidTagException|Exception
     */
    private function mountXml(CupomFiscal $cfe, $cfeItems)
    {
        $make = new Make();
        $ide = new Ide();

        $ide->CNPJ = $cfe->qti_cnpj;
        $ide->signAC = $cfe->signac;
        $ide->numeroCaixa = $cfe->numerocaixa;

        $make->tagIde($ide);

        $emit = new Emit();
        $emit->CNPJ = $cfe->emitcnpj;
        $emit->IE = str_pad($cfe->emitie, 12, " ");
        $emit->IM = $cfe->emitim;
        $emit->cRegTribISSQN = $cfe->emitcregtribissqn;
        $emit->indRatISSQN = $cfe->emitindratissqn;

        $make->tagEmit($emit);

        $dest = new Dest();
        $dest->CNPJ = $cfe->destcnpj;
        $dest->xNome = $cfe->destxnome;

        $make->tagDest($dest);

        $entrega = new Entrega();
        $entrega->xLgr = $cfe->destxlgr;
        $entrega->nro = $cfe->destnro;
        $entrega->xBairro = $cfe->destxbairro;
        $entrega->xMun = $cfe->destxmun;
        $entrega->xCpl = $cfe->destxcpl;
        $entrega->UF = $cfe->destuf;

        $make->tagEntrega($entrega);

        $vAproxTribEst = 0;
        $vAproxTribMun = 0;
        $vAproxTribFed = 0;
        $vItems12741 = 0;
        /**@var CupomFiscalItem $item*/
        foreach ($cfeItems as $item) {
            $det = new Det();
            $det->nItem = $item->nitem;
            $det->infAdProd = $item->infadprod;
            $make->tagDet($det);
            $vAproxTribEst += $item->vaproxtribest ? $item->vaproxtribest : 0;
            $vAproxTribMun += $item->vaproxtribmun ? $item->vaproxtribmun : 0;
            $vAproxTribFed += $item->vaproxtribfed ? $item->vaproxtribfed : 0;
            $vItems12741 += $item->vitem12741;
            $imposto = new Imposto($item->nitem);
            $imposto->vItem12741 = formatDecimalPlaces($item->vitem12741, 2);
            $make->tagImposto($imposto);

            $prod = new Prod();
            $prod->cProd = $item->cprod;
            $prod->cEAN = $item->cean;
            $prod->xProd = $item->xprod;
            $prod->NCM = $item->ncm;
            if (self::VERSAO_LAYOUT === "0.08") {
                $prod->CEST = $item->cest;
            }
            $prod->CFOP = $item->cfop;
            $prod->uCom = $item->ucom;
            $prod->qCom = $item->qcom;
            $prod->vUnCom = $item->vuncom;
            $prod->indRegra = $item->indregra;
            $prod->vDesc = $item->vdesc;
            $prod->vOutro = $item->voutro;
            $prod->nItem = $item->nitem;

            $make->tagProd($prod);

            $icms = new ICMS($item->csticms);
            $icms->Orig = $item->icmsorig;
            if (in_array($item->csticms, ["00", "20", "90", "900"])) {
                $icms->pICMS = $item->picms;
            }
            $icms->nItem = $item->nitem;
            $make->tagICMS($icms);

            $pis = new PIS($item->cstpis);
            if (in_array($item->cstpis, ["01", "02", "05", "99"])) {
                $pis->pPIS = formatDecimalPlaces($item->ppis, 4);
                $pis->vBC = formatDecimalPlaces($item->vbcpis);
            }
            $pis->nItem = $item->nitem;
            $make->tagPIS($pis);

            $cofins = new COFINS($item->cstcofins);
            if (in_array($item->cstcofins, ["01", "02", "05", "99"])) {
                $cofins->pCOFINS = formatDecimalPlaces($item->pcofins, 4);
                $cofins->vBC = formatDecimalPlaces($item->vbccofins);
            }
            $cofins->nItem = $item->nitem;
            $make->tagCofins($cofins);
        }

        foreach ($cfe->parcelas as $parc) {
            $pgto = new MP();
            $pgto->cMP = $cfe->condicaoPagamento->nfc_tpag;
            $pgto->vMP = formatDecimalPlaces($parc->vmp);
            $make->tagMP($pgto);
        }

        $vItems12741 = sprintf('%0.2f', strval($vItems12741));
        $vAproxTribEst = sprintf('%0.2f', strval($vAproxTribEst));
        $vAproxTribFed = sprintf('%0.2f', strval($vAproxTribFed));
        $vAproxTribMun = sprintf('%0.2f', strval($vAproxTribMun));

        $operacao = $cfe->operacao;
        $infAdic = new InfAdic();

        if ($operacao->deolhonoimposto) {
            $lei = Produtoleiimposto::first();
            $chave = $lei ? $lei->chave : "";
            $infAdic->infCpl = " - Valor Aprox. Tributos R$ {$vItems12741} - {$vAproxTribFed} Federal, "
                . "{$vAproxTribEst} Estadual e {$vAproxTribMun} Municipal. Fonte: IBPT {$chave}.";
        }
        $make->tagInfAdic($infAdic);

        $xml = $make->getXml();
        if ($make->hasErrros()) {
            $this->errors = array_merge($this->errors, $make->getErrors());
            throw new Exception();
        }

        $cfe->update([
            "vcfelei12741"  => $vItems12741,
            "infCpl"        => $infAdic->infCpl
        ]);

        return $xml;
    }

    /**
     * @param $data
     * @param CupomFiscal $cfe
     * @return bool
     * @throws Exception
     */
    private function gerarFinanceiro($data, $cfe)
    {
        $data["condicaopagamento_id"] = isset($data["condicaopagamento_id"]) ? $data["condicaopagamento_id"] : null;
        $condicaopagamento = DB::table("condicaopagamentos")->where('id', $data["condicaopagamento_id"])->first();
        $nfoperacao = Nfoperacao::find($data['nfoperacao_id']);
        if ($nfoperacao->movimentafinanceiro != 0 && is_null($condicaopagamento))
            throw new Exception("Não foi possivel localizar a condição de pagamento " . $data["condicaopagamento_id"]);
        // tipo = 5-Vale Gás
        $isValeGas = ! is_null($condicaopagamento) && (int) $condicaopagamento->tipo === 5;
        // movimentafinanceiro = 0-Nada 1-Pagar 2-Receber
        if ($nfoperacao->movimentafinanceiro == 0 || $isValeGas) {
            return false;
        } else {
            $date = Carbon::parse($cfe->demi . ' ' . $cfe->hemi);

            $cliente_id = $data["cliente_id"];
            $descricao_lanc = "Cupom Fiscal N°:" . $cfe->ncfe . ", id:" . $cfe->id . ". " . $data["descricaofinanceiro"];
            // 0-Nada 1-Pagar 2-Receber
            $pagarreceber = $nfoperacao->movimentafinanceiro == 1 ? "P" : "R";

            $conveniovencimento = isset($data['conveniovencimento']) ? $data['conveniovencimento'] : null;
            $parcelas = calculoParcelas($data["condicaopagamento_id"], $date, $cfe->icmsvcfe, $condicaopagamento, $conveniovencimento);

            $processor = new financeiroProcessor();
            if ($cfe->planoconta_id !== null && $cfe->centrocusto_id !== null) {
                $financeiro = new Financeiro();
                $financeiro->empresa_id = $cfe->empresa_id;
                $financeiro->grupo_id = $cfe->grupo_id;
                $financeiro->cliente_id = $cliente_id;
                $financeiro->pagarreceber = $pagarreceber;
                $financeiro->documento = $cfe->ncfe;
                $financeiro->descricao = $descricao_lanc;
                $financeiro->cartaonsu = null;
                $financeiro->cartaoautorizacao = null;
                $financeiro->valor = $cfe->icmsvcfe;
                $financeiro->condicaopagamento_id = $data["condicaopagamento_id"];
                $financeiro->datacompetencia = Carbon::now();
                $financeiro->dataemissao = $date;
                $processor->setFinanceiro($financeiro);

                $countparcelas = (int) count($parcelas);
                $avista = NfUtil::isPgtoAvisa($parcelas, $financeiro, $countparcelas);

                $descontoparcelar = $cfe->icmsvdesc / ($avista ? 1 : $countparcelas);
                $tRateio = $cfe->icmsvcfe + $cfe->icmsvdesc;

                $this->gerarRateio($processor, $tRateio, $data, $cfe->planoconta_id, $cfe->centrocusto_id);
                $parc = NfUtil::gerarParcelas($countparcelas, $parcelas, $descontoparcelar, $processor, $date, $cfe->icmsvcfe, $cfe->empresa);
                $processor->setParcelas($parc);
                $this->setParcelasCFe($parc, $cfe, $data["vmp"]);

                if (! $processor->gravar()) {
                    $this->errors = $processor->getErrors();
                    throw new Exception();
                }

                $idfinanceiro = $processor->getFinanceiro()->id;

                $dataUp = ["financeiro_id" => $idfinanceiro];
                return $cfe->update($dataUp);
            } else {
                $msg = "Problemas ao criar Financeiro: Não foi encontrado o plano de contas ou centro de custos";
                throw new Exception($msg);
            }
        }
    }

    /**
     * @param $parcelas
     * @param CupomFiscal $cfe
     * @param $vmp
     * @throws Exception
     */
    private function setParcelasCFe($parcelas, CupomFiscal $cfe, $vmp)
    {
        CupomFiscalParcela::where("cupomfiscal_id", $cfe->id)->delete();
        formatDecimalPlaces($vmp, 2);
        foreach ($parcelas as $parc) {
            /** @var Financeiroparcela $parc */
            $p = new CupomFiscalParcela();
            $p->cupomfiscal_id = $cfe->id;
            $p->empresa_id = $parc->empresa_id;
            $p->grupo_id = $parc->grupo_id;
            $p->numeroparcela = $parc->numero;
            $p->referencia = str_pad($parc->numero, 3, "0", STR_PAD_LEFT);
            $p->datavencimento = $parc->datavencimento;
            $p->cmp = $cfe->condicaoPagamento->nfc_tpag;
            $p->vmp = $parc->valor;
            $p->save();
        }
    }

    /**
     * Gera o rateio se foi passado algum, senão gera o padrão
     *
     * @param FinanceiroProcessor|null $processor
     * @param $total
     * @param $data
     * @param $planoconta_id
     * @param $centrocusto_id
     * @return array|null
     * @throws Exception
     */
    private function gerarRateio(?FinanceiroProcessor $processor, $total, $data, $planoconta_id, $centrocusto_id)
    {
        $rato = [];
        $rateios = isset($data["rateios"]) && count(json_decode($data["rateios"])) > 0 ? json_decode($data["rateios"]) : null;

        // Substitui o rateio default pelos informados
        if (! is_null($rateios)) {
            $vlrtotalrateio = 0;

            foreach ($rateios as $rateio) {
                $rateiovalor = insertNumeroDecimalOracle($rateio[6]);
                $vlrtotalrateio += $rateiovalor;

                $raton = new Financeirorateio();
                $raton["centrocusto_id"] = $rateio[0];
                $raton["planoconta_id"] = $rateio[3];
                $raton["valor"] = $rateiovalor;
                $raton["financeiro_id"] = is_null($processor) ? : $processor->getFinanceiro()->id;
                $raton["percentual"] = $total > 0 ? $rateiovalor / $total : 1;
                array_push($rato, $raton);
            }
        } else {
            $raton = new Financeirorateio();
            $raton["centrocusto_id"] = $centrocusto_id;
            $raton["planoconta_id"] = $planoconta_id;
            $raton["valor"] = $total;
            $raton["financeiro_id"] = is_null($processor) ? : $processor->getFinanceiro()->id;
            $raton["percentual"] = 1;
            $vlrtotalrateio = $total;
            array_push($rato, $raton);
        }

        if (strval($total) !== strval($vlrtotalrateio)) {
            throw new Exception("Total do rateio não confere com o total da NF.");
        }

        if (! is_null($processor)) {
            $processor->setRateios($rato);
            return null;
        } else {
            return $rato;
        }
    }

    /**
     * @param array $data
     * @return bool
     */
    private function validateCustom(array &$data)
    {
        $empresa = Session::get("empresa_padrao");
        $user = Auth::user();
        if (empty($data["destcnpj"]) && empty($data["destcpf"])) {
            $this->errors[] = "CNPJ ou CPF do destinatário precisa ser informado.";
        }
        $data["destcnpj"] = onlyNumbers($data["destcnpj"]);
        $data["destcpf"] = onlyNumbers($data["destcpf"]);
        $data["emitcnpj"] = onlyNumbers($data["emitcnpj"]);

        $data["numerocaixa"] = "001";

        if ($empresa->sattipoambiente == 2) {
            $data["qti_cnpj"] = $this->config->satcnpjhomolog;
            $data["signac"] = $this->config->satsignachomolog;
        } else {
            $data["qti_cnpj"] = $this->config->satcnpjprod;
            $data["signac"] = $this->config->satsignacprod;
        }

        //valor fixo devido ao sistema não oferecer prestação de serviços
        $data["emitindratissqn"] = "N";

        $data["grupo_id"] = $empresa->grupo_id;
        $data["user_id"] = $user->id;
        $data["status"] = 1;
        $data["status_descricao"] = "Salvo não transmitido";

        $data["icmsvicms"] = insertNumeroDecimalOracle(isset($data["icmsvicms"]) ? $data["icmsvicms"] : "R$ 0,00");
        $data["icmsvprod"] = insertNumeroDecimalOracle(isset($data["icmsvprod"]) ? $data["icmsvprod"] : "R$ 0,00");
        $data["icmsvdesc"] = insertNumeroDecimalOracle(isset($data["icmsvdesc"]) ? $data["icmsvdesc"] : "R$ 0,00");
        $data["icmsvpis"] = insertNumeroDecimalOracle(isset($data["icmsvpis"]) ? $data["icmsvpis"] : "R$ 0,00");
        $data["icmsvcofins"] = insertNumeroDecimalOracle(isset($data["icmsvcofins"]) ? $data["icmsvcofins"] : "R$ 0,00");
        $data["icmsvpisst"] = insertNumeroDecimalOracle(isset($data["icmsvpisst"]) ? $data["icmsvpisst"] : "R$ 0,00");
        $data["icmsvcofinsst"] = insertNumeroDecimalOracle(isset($data["icmsvcofinsst"]) ? $data["icmsvcofinsst"] : "R$ 0,00");
        $data["icmsvoutro"] = insertNumeroDecimalOracle(isset($data["icmsvoutro"]) ? $data["icmsvoutro"] : "R$ 0,00");
        $data["icmsvcfe"] = insertNumeroDecimalOracle(isset($data["icmsvcfe"]) ? $data["icmsvcfe"] : "R$ 0,00");
        $data["issqnvbc"] = insertNumeroDecimalOracle(isset($data["issqnvbc"]) ? $data["issqnvbc"] : "R$ 0,00");
        $data["issqnviss"] = insertNumeroDecimalOracle(isset($data["issqnviss"]) ? $data["issqnviss"] : "R$ 0,00");
        $data["issqnvpis"] = insertNumeroDecimalOracle(isset($data["issqnvpis"]) ? $data["issqnvpis"] : "R$ 0,00");
        $data["issqnvcofins"] = insertNumeroDecimalOracle(isset($data["issqnvcofins"]) ? $data["issqnvcofins"] : "R$ 0,00");
        $data["issqnvpisst"] = insertNumeroDecimalOracle(isset($data["issqnvpisst"]) ? $data["issqnvpisst"] : "R$ 0,00");
        $data["issqnvcofinsst"] = insertNumeroDecimalOracle(isset($data["issqnvcofinsst"]) ? $data["issqnvcofinsst"] : "R$ 0,00");
        $data["vdescsubtot"] = insertNumeroDecimalOracle(isset($data["vdescsubtot"]) ? $data["vdescsubtot"] : "R$ 0,00");
        $data["vacressubtot"] = insertNumeroDecimalOracle(isset($data["vacressubtot"]) ? $data["vacressubtot"] : "R$ 0,00");
        $data["vcfelei12741"] = insertNumeroDecimalOracle(isset($data["vcfelei12741"]) ? $data["vcfelei12741"] : "R$ 0,00");

        return empty($this->errors);
    }

    /**
     * @param CupomFiscal $nots
     * @return Factory|View
     * @throws AuthorizationException
     */
    public function create(CupomFiscal $nots)
    {
        $this->authorize('create', $nots);
        return $this->form(null, "create");
    }

    /**
     * @param CupomFiscal $nots
     * @param $id
     * @return RedirectResponse|Factory|View
     * @throws AuthorizationException
     */
    public function edit(CupomFiscal $nots, $id)
    {
        $this->authorize('update', $nots);
        return $this->form($id, "edit");
    }

    /**
     * @param CupomFiscal $nots
     * @param $id
     * @return RedirectResponse|Factory|View
     * @throws AuthorizationException
     */
    public function show(CupomFiscal $nots, $id)
    {
        $this->authorize('view', $nots);
        return $this->form($id, "show");
    }

    /**
     * @param $id
     * @param string $mode
     * @return Factory|RedirectResponse|View
     * @throws AuthorizationException
     */
    private function form($id, $mode = "edit")
    {
        $empresa = Session::get("empresa_padrao");
        if (! is_null($id)) {
            $cupomFiscal = CupomFiscal::from("cuponsfiscais as cf")
                ->leftJoin("planocontas as pc", "pc.id", "cf.planoconta_id")
                ->leftJoin("centrocustos as cc", "cc.id", "cf.centrocusto_id")
                ->selectRaw("cf.*, cc.descricao as centrocusto_descricao, pc.descricao as planoconta_descricao")
                ->whereRaw("cf.id = " . $id)->first();
            if (! $cupomFiscal) {
                return redirect()->route('satcfe.index')->withErrors("Cupom Fiscal não encontrado");
            }
            $this->changeParamForm($cupomFiscal);
            if ($cupomFiscal->financeiro_id) {
                $parcelasfinanceiro = Financeiroparcela::where("financeiro_id", $cupomFiscal->financeiro_id)->get();
            }
        } else {
            $cupomFiscal = new CupomFiscal();
            $cupomFiscal->empresa_id = $empresa->id;
        }
        $this->authorize('igualdade', $cupomFiscal);

        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = collect(Planoconta::menuspermissoesAll());
        $condicaopagamentos = SelectRepository::getCondPgtoToView($empresa, false)->pluck('descricao', 'id')->prepend('Selecione', '');

        $planocontasR = $planocontas->where("pagarreceber", "R");
        $planocontasD = $planocontas->where("pagarreceber", "D");
        $nfoperacaos = SelectRepository::getOperacaoToView($empresa, "sat");
        $setores = SelectRepository::getSetoresToView($empresa);
        $produtos = SelectRepository::getProdutoNf($empresa->id);

        $show = $mode === "show";

        if (! isset($parcelasfinanceiro)) {
            $parcelasfinanceiro = collect([]);
        }

        return view('satcfe.cfe_form',
            compact(
                'cupomFiscal', 'empresa', 'centrocustos', 'planocontas', 'planocontasR', 'planocontasD',
                'condicaopagamentos', 'nfoperacaos', 'setores', 'produtos', 'show', 'parcelasfinanceiro'
            )
        );
    }

    /**
     * @param $cfe
     * @return mixed
     */
    private function changeParamForm(&$cfe)
    {
        $cfe->icmsvicms = requestNumeroDecimalOracle($cfe->icmsvicms ? $cfe->icmsvicms : 0);
        $cfe->icmsvprod = requestNumeroDecimalOracle($cfe->icmsvprod ? $cfe->icmsvprod : 0);
        $cfe->icmsvdesc = requestNumeroDecimalOracle($cfe->icmsvdesc ? $cfe->icmsvdesc : 0);
        $cfe->icmsvpis = requestNumeroDecimalOracle($cfe->icmsvpis ? $cfe->icmsvpis : 0);
        $cfe->icmsvcofins = requestNumeroDecimalOracle($cfe->icmsvcofins ? $cfe->icmsvcofins : 0);
        $cfe->icmsvpisst = requestNumeroDecimalOracle($cfe->icmsvpisst ? $cfe->icmsvpisst : 0);
        $cfe->icmsvcofinsst = requestNumeroDecimalOracle($cfe->icmsvcofinsst ? $cfe->icmsvcofinsst : 0);
        $cfe->icmsvoutro = requestNumeroDecimalOracle($cfe->icmsvoutro ? $cfe->icmsvoutro : 0);
        $cfe->icmsvcfe = requestNumeroDecimalOracle($cfe->icmsvcfe ? $cfe->icmsvcfe : 0);
        $cfe->issqnvbc = requestNumeroDecimalOracle($cfe->issqnvbc ? $cfe->issqnvbc : 0);
        $cfe->issqnviss = requestNumeroDecimalOracle($cfe->issqnviss ? $cfe->issqnviss : 0);
        $cfe->issqnvpis = requestNumeroDecimalOracle($cfe->issqnvpis ? $cfe->issqnvpis : 0);
        $cfe->issqnvcofins = requestNumeroDecimalOracle($cfe->issqnvcofins ? $cfe->issqnvcofins : 0);
        $cfe->issqnvpisst = requestNumeroDecimalOracle($cfe->issqnvpisst ? $cfe->issqnvpisst : 0);
        $cfe->issqnvcofinsst = requestNumeroDecimalOracle($cfe->issqnvcofinsst ? $cfe->issqnvcofinsst : 0);
        $cfe->vdescsubtot = requestNumeroDecimalOracle($cfe->vdescsubtot ? $cfe->vdescsubtot : 0);
        $cfe->vacressubtot = requestNumeroDecimalOracle($cfe->vacressubtot ? $cfe->vacressubtot : 0);
        $cfe->vcfelei12741 = requestNumeroDecimalOracle($cfe->vcfelei12741 ? $cfe->vcfelei12741 : 0);

        return $cfe;
    }

    /**
     * @param $id
     * @return JsonResponse|void
     */
    public function transmitir($id)
    {
        try {
            DB::beginTransaction();

            $address = env('WEBSOCKET_ADDRESS', null);

            if (is_null($address)) {
                throw new Exception("URL do Websocket não encontrada, entre em contato com o suporte!");
            }

            $cfe = CupomFiscal::find($id);
            if (is_null($cfe)) {
                throw new Exception("Cupom Fiscal não encontrado");
            }
            $this->connectWS($address . "?empresa_id=" . $cfe->empresa_id . "&sender=server", $cfe);
            return;
        } catch (Exception $e) {
            DB::rollBack();
            $message = $e->getMessage() ;
            satLog($e->getTraceAsString());
            return response()->json($message);
        }
    }

    /**
     * @param $id
     * @return JsonResponse|void
     */
    public function cancelar($id)
    {
        try {
            DB::beginTransaction();

            $address = env('WEBSOCKET_ADDRESS', null);

            if (is_null($address)) {
                throw new Exception("URL do Websocket não encontrada, entre em contato com o suporte!");
            }

            $cfe = CupomFiscal::find($id);
            if (is_null($cfe)) {
                throw new Exception("Cupom Fiscal não encontrado");
            }
            $this->connectWS($address . "?empresa_id=" . $cfe->empresa_id . "&sender=server", $cfe, "CF-e Cancelamento");
            return;
        } catch (Exception $e) {
            DB::rollBack();
            $message = $e->getMessage() ;
            satLog($e->getTraceAsString());
            return response()->json($message);
        }
    }

    /**
     * @param $url
     * @param $cfe
     * @param string $message
     */
    private function connectWS($url, $cfe, $message = "CF-e Pendente")
    {
        Client\connect($url)->then(function(Client\WebSocket $conn) use ($cfe, $message) {
            $conn->send(\GuzzleHttp\json_encode(
                (object) [
                    "message"   => $message,
                    "status"    => "OK",
                    "data"      => $cfe->id
                ]
            ));

            $conn->on('message', function(Message $msg) use ($conn) {
                $payload = $msg->getPayload();
                $json = json_decode(utf8_encode($payload));
                if (json_last_error() != JSON_ERROR_NONE) {
                    echo json_last_error_msg();
                } else {
                    echo $json->message;
                }
                $conn->close();
            });
            $conn->on('error', function($error) use ($conn) {
                echo $error;
            });
        }, function (Exception $e) {
            echo "Não foi possível realizar a comunicação com o Websocket, entre em contato com o suporte: {$e->getMessage()}";
        });
    }

    /**
     * @return JsonResponse
     */
    public function test()
    {
        try {
            $gClient = new \GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1/phpcfe/']);
            $res = $gClient->get('index.php');
            return $res->getBody()->getContents();
        } catch (Exception $e) {
            satLog($e->getMessage());
            return response()->json([
                "data"              => null,
                "message"           => $e->getMessage(),
                "response_status"   => "NOK"
            ]);
        }
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function getDoc($id)
    {
        try {
            $doc = CupomFiscal::where("id", $id)->first();

            return response()->json([
                "data"              => base64_encode($doc->xml),
                "message"           => "Sucesso",
                "response_status"   => "OK"
            ]);
        } catch (Exception $e) {
            satLog($e->getMessage());
            return response()->json([
                "data"              => null,
                "message"           => $e->getMessage(),
                "response_status"   => "NOK"
            ]);
        }
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function getDocCancel($id)
    {
        try {
            //TODO precisa gerar o xml de cancelamento
//            $doc = CupomFiscal::where("id", $id)->first();

            $xml = "<CFeCanc>
                  <infCFe chCanc=\"CFe35161261099008000141599000026310003024947916\">
                        <ide>
                            <CNPJ>16716114000172</CNPJ>
                            <signAC>SGR-SAT SISTEMA DE GESTAO E RETAGUARDA DO SAT</signAC>
                            <numeroCaixa>001</numeroCaixa>
                        </ide>
                    <emit/>
                    <dest/>
                    <total/>
                  </infCFe>
                </CFeCanc>";
            return response()->json([
                "data"              => base64_encode($xml),
                "message"           => "Sucesso",
                "response_status"   => "OK"
            ]);
        } catch (Exception $e) {
            satLog($e->getMessage());
            return response()->json([
                "data"              => null,
                "message"           => $e->getMessage(),
                "response_status"   => "NOK"
            ]);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getEmpresa(Request $request)
    {
        try {
            $user_id = Input::get("user_id", "");
            $this->throwIf((int) $user_id <= 0, "Parâmetro \"user_id\" não informado ou incorreto;");

            $id = Input::get("empresa_id", null);
            $this->throwIf((int) $id <= 0, "Parâmetro \"empresa_id\" não informado ou incorreto;");

            $empresa = Empresa::join('empresa_user as empu', 'empu.empresa_id', 'empresas.id')
                ->join("empresas_grupos as eg", "eg.id", "empresas.grupo_id")
                ->where('empu.user_id', $user_id)
                ->where('empu.empresa_id', $id)
                ->select([
                    'empresas.id', 'eg.id as grupo_id', 'eg.descricao as nome_grupo', 'nome_informal',
                    "razao_social", "cnpj"
                ])
                ->first();

            $this->throwIf(
                ! $empresa,
                "A empresa com o código informado nas suas configurações não foi encontrada ou " .
                "você não possui permissões para usá-la"
            );

            return response()->json([
                "data"              => $empresa->toJson(),
                "message"           => "Sucesso!",
                "response_status"   => "OK"
            ]);
        } catch (SatException $e) {
            return response()->json([
                "data"              => null,
                "message"           => $e->getMessage(),
                "response_status"   => "NOK"
            ]);
        } catch (Exception $e) {
            satLog($e->getMessage());
            return response()->json([
                "data"              => null,
                "message"           => $e->getMessage(),
                "response_status"   => "NOK"
            ]);
        }
    }

    /**
     * @param bool $condition
     * @param string $message
     * @throws Exception
     */
    // protected function throwIf($condition, $message)
    // {
    //     if ($condition) {
    //         throw new Exception($message);
    //     }
    // }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserInfo(Request $request)
    {
        try {
            $user = null;
            $data = [
                "email"     => $request->get("username", ""),
                "password"  => base64_decode($request->get("password", ""))
            ];
            if (Auth::attempt($data)) {
                $user = Auth::user();
            }
            if (is_null($user)) {
                throw new SatException("Usuário não encontrado no sistema!");
            }
            return response()->json([
                "data"              => $user->id,
                "message"           => "Sucesso!",
                "response_status"   => "OK"
            ]);
        } catch (SatException $e) {
            return response()->json([
                "data"              => null,
                "message"           => $e->getMessage(),
                "response_status"   => "NOK"
            ]);
        } catch (Exception $e) {
            satLog($e->getMessage());
            return response()->json([
                "data"              => null,
                "message"           => $e->getMessage(),
                "response_status"   => "NOK"
            ]);
        }
    }
}
