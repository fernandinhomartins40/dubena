<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Cliente;
use App\Boleto;
use App\Boletohistorico;
use App\Financeiroparcela;
use Illuminate\Http\Request;
use App\Ocorrenciasremessas;
use App\Processors\BoletoProcessor;
use App\Services\CarbonCustom as Carbon;

/**
 *
 *
 * Remover vínculo de boleto/boletohistorico/boletoremessafinanceiro após exportar arquivo de remessa com instrução de baixa/devolucao
 *
 *
 *
 */
class BoletoController extends Controller
{

    private $resultsPerPage = 100;
    private $empresa_id;

    public function index(Request $request, Boleto $bol)
    {
        $this->authorize('view', $bol);
        $this->empresa_id = Session::get('empresa_padrao')->id;

        $processor = new BoletoProcessor();

        $contas = $processor->getContasIndexGerar();
        $parcelasDb = $this->getParcelasIndex();

        $tipofiltro = isset($_GET['tipofiltro']) ? (int) $_GET['tipofiltro'] : 1;
        $dateType = $tipofiltro === 2 ? 'financeiroparcelas.datavencimento' : 'financeiro.dataemissao';

        if ($tipofiltro > 0 || count($_GET) === 0)
            $parcelasDb->whereBetween($dateType, $this->getDatesToFilter());

        $cliente_id = isset($_GET['cliente_id']) && !empty($_GET['cliente_id']) ? $_GET['cliente_id'] : null;
        $cliente = !is_null($cliente_id) ? Cliente::find($cliente_id) : null;
        $cliente_nome = is_null($cliente) ? null : $cliente->nome;

        if (!is_null($cliente_id))
            $parcelasDb->where('cliente_id', $cliente_id);

        $gerouBoleto = isset($_GET['gerouboleto']) ? $_GET['gerouboleto'] : false;
        $parcelasDb->whereIn('boletogerado', [$gerouBoleto, null]);
        if ($gerouBoleto) {
            if (isset($_GET['gerouremessa']) && $_GET['gerouremessa'])
                $raw = "(gerouremessa =  1)";
            else
                $raw = "(gerouremessa IS NULL OR gerouremessa = 0)";

            $parcelasDb->whereRaw($raw);
        }
        $clonedPars = clone $parcelasDb;

        $pars = $_GET;
        if (isset($pars['page']))
            unset($pars['page']);
        $url = 'boleto?';
        foreach ($pars as $key => $value) {
            $url .= "&$key=$value";
        }

        $parcelas = $parcelasDb->paginate($this->resultsPerPage)->withPath($url);

        $totais = $clonedPars->select(DB::raw("sum(valorefetivado) as valorefetivado, count(1) as count"))->get()->first();

        return view('financeiro.boletos.gerarboleto', compact('parcelas', 'contas', 'cliente_id', 'cliente_nome', 'totais'));
    }

    /**
     * retorna as datas para o filtro
     * @return array
     */
    private function getDatesToFilter()
    {
        if (isset($_GET['datafim']) && !empty($_GET['datafim']))
            $datafim = Carbon::createFromFormat('d/m/Y', $_GET['datafim'])->endOfDay()->toDateTimeString();
        else
            $datafim = Carbon::now()->endOfDay()->toDateTimeString();

        if (isset($_GET['datainicio']) && !empty($_GET['datainicio']))
            $datainicio = Carbon::createFromFormat('d/m/Y', $_GET['datainicio'])->startOfDay()->toDateTimeString();
        else
            $datainicio = Carbon::now()->startOfDay()->toDateTimeString();

        return [$datainicio, $datafim];
    }

    public function store(Request $request, Boleto $modelBoleto)
    {
        $this->authorize('create', $modelBoleto);
        $data = $request->all();
        $processor = new BoletoProcessor();
        return $processor->gerarBoleto($data);
    }

    public function update(Request $request, $id)
    {
        $boleto = Boleto::find($id);
        $this->authorize('update', $boleto);
        $data = $request->all();
        DB::beginTransaction();
        try {
            $this->checkPendencia($boleto);
            $data['ultimaocorrencia_id'] = $data['ocorrencia_id'];
            $ocorrencia = Ocorrenciasremessas::find($data['ocorrencia_id']);

            if (isset($data['prazo_protesto']) || isset($data['prazo_devolucao']))
                $data["protesto_devolucao"] = isset($data['prazo_devolucao']) ? $data['prazo_devolucao'] : $data['prazo_protesto'];

            if (isset($data['valor_abatimento'])) {
                $data['valor_abatimento'] = insertNumeroDecimalOracle($data['valor_abatimento']);
                if ($data['valor_abatimento'] > $boleto->financeiroparcela->valorefetivado) {
                    throw new \Exception("O valor do abatimento não pode ser maior que o valor do boleto");
                }
            }

            $processor = new BoletoProcessor();

            $data = $this->unsetKeys($data);

            if (!$processor->insertBoletoHistorico($boleto, $data))
                throw new \Exception("Erro ao gravar histórico do boleto");

            if ($ocorrencia->codigo == '02') {
                $destroy = $this->destroy($id, true, $processor->getLastHistorico());
                if ($destroy != 'OK|')
                    throw new \Exception($destroy);
            }

            $data['imprimiu'] = false;

            if ($ocorrencia->codigo != '04' && $ocorrencia->codigo != '03')
                $data['valor_abatimento'] = null;

            if ($ocorrencia->codigo === '01')
                throw new \Exception("O código de ocorrência não pode ser 01");
            else
                $data['alterou'] = 1;

            $data['pendencia'] = true;
            $boleto->update($data);
        } catch (\Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
        DB::commit();
        return 'OK|';
    }

    public function destroy($id, $alteroucancelou = false, $hist_id = null)
    {
        $boleto = Boleto::with('conta')->find($id);
        if ($alteroucancelou)
            $this->authorize('update', $boleto);
        else
            $this->authorize('delete', $boleto);

        DB::beginTransaction();
        try {
            $this->checkPendencia($boleto);

            if (! $alteroucancelou && $boleto->gerouremessa) {
                $msg = "O boleto já gerou remessa portanto não é possível excluí-lo";
                throw new \Exception($msg);
            }

            $parc = Financeiroparcela::with('financeiro')->where('boleto.id', $id)
                    ->join('boletos as boleto', 'boleto.financeiroparcela_id', 'financeiroparcelas.id')
                    ->select('financeiroparcelas.id', 'financeiroparcelas.financeiro_id', 'valorefetivado')
                    ->get()->first();

            if (!$alteroucancelou) {
                Financeiroparcela::where('id', $boleto->financeiroparcela_id)->update(['boletogerado' => false]);
            }
            if (!is_null($parc)) {
                $dataHist = [
                    'info_cancelamento' => "Cliente: " . $parc->financeiro->cliente_id . " Valor Total: " . $parc->valorefetivado . " Parcela:" . $parc->id,
                    'cancelado'         => true,
                    'alteroucancelou'   => $alteroucancelou
                ];
                if (is_null($hist_id)) {
                    Boletohistorico::where('boleto_id', $id)->update($dataHist);
                    $boleto->update(['financeiro_id' => null, 'financeiroparcela_id' => null, 'cancelado' => true, 'alteroucancelou' => $alteroucancelou]);
                    if (!$alteroucancelou && !$boleto->gerouremessa) {
                        if ($boleto->conta->boletosequencia == $boleto->numerosequencia) {
                            $conta = $boleto->conta;
                            $conta->update(['boletosequencia' => $conta->boletosequencia - 1]);
                        }
                    }
                } else {
                    Boletohistorico::find($hist_id)->update($dataHist);
                }
                DB::commit();
            }
            return "OK|";
        } catch (\Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
    }

    private function checkPendencia($boleto)
    {
        if ($boleto->pendencia && $boleto->ultimaocorrencia_id) {
            $msg = "Já foi dado um comando a este boleto, "
                    . "para gerar um novo comando, você deve gerar/cancelar uma remessa e efetivá-la";
            throw new \Exception($msg);
        }
    }

    private function getParcelasIndex()
    {
        $select = ['financeiroparcelas.id as parcela_id', 'financeiroparcelas.id',
            'cliente.nome as cliente', 'financeiroparcelas.numero', 'financeiroparcelas.valor',
            'financeiroparcelas.valorefetivado', 'financeiroparcelas.datavencimento',
            'financeiroparcelas.juros', 'financeiro.cliente_id', 'financeiroparcelas.multa',
            'financeiro.documento', 'financeiroparcelas.boletogerado', 'financeiroparcelas.desconto',
            'boleto.gerouremessa', 'financeiro.dataemissao'];
        $where = [
            'pagamento.tipo'                  => 1,
            'financeiroparcelas.empresa_id'   => $this->empresa_id,
            'financeiroparcelas.pagarreceber' => "R",
            'financeiroparcelas.baixado'      => 0
        ];

        $whereRaw = "chequerecebido_id IS NULL AND financeiroparcelas.agrupamento_status NOT IN (2, 3)";
        return Financeiroparcela::where($where)->select($select)->whereRaw($whereRaw)
                        ->join('financeiros as financeiro', 'financeiro.id', 'financeiroparcelas.financeiro_id')
                        ->join('condicaopagamentos as pagamento', 'financeiro.condicaopagamento_id', 'pagamento.id')
                        ->join('clientes as cliente', 'cliente.id', 'financeiro.cliente_id')
                        ->leftJoin('boletos as boleto', 'boleto.financeiroparcela_id', 'financeiroparcelas.id')
                        ->leftJoin('chequerecebidofinanceiros as cheque', 'financeiroparcelas.id', 'cheque.financeiroparcela_id')
                        ->orderBy('cliente.nome')->orderBy('financeiro.dataemissao');
    }

    public function unsetKeys($data)
    {
        if (isset($data['empresa_documento']))
            unset($data['empresa_documento']);
        if (isset($data['_method']))
            unset($data['_method']);
        if (isset($data['prazo_protesto']))
            unset($data['prazo_protesto']);
        if (isset($data['prazo_devolucao']))
            unset($data['prazo_devolucao']);
        return $data;
    }

}
