<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Storage;
use App\Conta;
use App\Boleto;
use App\Boletoremessa;
use App\Boletohistorico;
use App\Financeiroparcela;
use Illuminate\Http\Request;
use App\Ocorrenciasremessas;
use App\Boletoremessafinanceiro;
use Eduardokum\LaravelBoleto\Util;
use App\Processors\BoletoProcessor;
use App\Repository\SelectRepository;
use App\Services\CarbonCustom as Carbon;

class BoletoremessaController extends Controller
{

    private $empresa_id;

    public function index(Boletoremessa $rem)
    {
        $this->authorize('view', $rem);
        $empresa_id = Session::get('empresa_padrao')->id;
        $where = ['boletoemite' => true, 'empresa_id' => $empresa_id, 'ativo' => true];
        $contas = SelectRepository::searchConta($where, ['id', 'descricao'])
                        ->get()->pluck('descricao', 'id')->prepend('Selecione', '');

        if (count($_GET) > 0)
            return $this->filterIndexRemessa($empresa_id, $contas);

        $dataInicio = Carbon::now()->subDays(2)->toDateString();
        $dataFim = Carbon::now()->toDateString();
        $remessas = Boletoremessa::where('empresa_id', $empresa_id)
                        ->whereBetween('datahora', [$dataInicio . '00:00:00', $dataFim . '23:59:59'])
                        ->with('conta')->get();

        return view('financeiro.boletos.boletoremessa', compact('contas', 'dataInicio', 'remessas', 'dataFim'));
    }

    public function filterIndexRemessa($empresa_id, $contas)
    {
        $dataInicio = insertDataOracle($_GET['datainicio']);
        $dataFim = Carbon::createFromFormat('d/m/Y', $_GET['datafim'])->endOfDay()->toDateTimeString();
        $conta_id = $_GET['conta_id'];

        $remessas = Boletoremessa::where('empresa_id', $empresa_id)->whereBetween('datahora', [$dataInicio, $dataFim])->with('conta');

        if ($conta_id != '')
            $remessas->where('conta_id', $conta_id);

        $remessas = $remessas->get();

        return view('financeiro.boletos.boletoremessa', compact('contas', 'remessas'));
    }

    public function create(Boletoremessa $rem)
    {
        $this->authorize('create', $rem);
        $empresa_id = Session::get('empresa_padrao')->id;

        $where = ['boletoemite' => true, 'empresa_id' => $empresa_id, 'ativo' => true];
        $contas = SelectRepository::searchConta($where, ['id', 'descricao', 'boletoremessasequencia'])->get();

        if (count($_GET) > 0 && isset($_GET['datainicio']))
            return $this->filterCreateRemessa($_GET, $empresa_id, $contas);

        return view('financeiro.boletos.boletoremessa_form', compact('contas'));
    }

    public function filterCreateRemessa($filtros, $empresa_id, $contas, $remessa = null)
    {
        $dataInicio = insertDataOracle($_GET['datainicio']);
        $dataFim = insertDataOracle($_GET['datafim']);
        $conta_id = $_GET['conta_id'];
        $gerouRemessa = $_GET['gerouremessa'] === '1';
        $ocorrencia_id = $_GET['ocorrencia'];
        $conta = Conta::find($conta_id);

        $raw = "alteroucancelou = CASE WHEN CANCELADO = 0 THEN 0 ELSE 1 END ";
        if ($gerouRemessa) {
            $raw .= "AND (gerouremessa = 1 OR 0 < (SELECT COUNT(*) FROM BOLETOHISTORICOS WHERE FINANCEIROPARCELA_ID = boletos.financeiroparcela_id))";
        } else {
            $raw .= "AND gerouremessa = 0";
        }
        $boletos = Boleto::with('boletohistorico')
                ->join('financeiroparcelas parcela', 'parcela.id', 'boletos.financeiroparcela_id')
                ->join('financeiros financeiro', 'financeiro.id', 'parcela.financeiro_id')
                ->join('clientes cliente', 'cliente.id', 'financeiro.cliente_id')
                ->leftJoin('ocorrenciasremessas ocorr', 'ocorr.id', 'boletos.ultimaocorrencia_id')
                ->where('boletos.empresa_id', $empresa_id)
                ->where('conta_id', $conta_id)
                ->whereRaw($raw)
                ->whereBetween('boletos.updated_at', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']);

        if ($ocorrencia_id !== "0") {
            $boletos = $this->selectBoletos($boletos->whereRaw("ultimaocorrencia_id IN (" . $ocorrencia_id . ")"));
        } else {
            $boletos = $this->selectBoletos($boletos);
        }

        if (!is_null($remessa))
            return view('financeiro.boletos.boletoremessa_form', compact('contas', 'boletos', 'conta', 'remessa'));

        return view('financeiro.boletos.boletoremessa_form', compact('contas', 'boletos', 'conta'));
    }

    private function selectBoletos($boletos)
    {
        return $boletos->select([
                    'boletos.id', 'boletos.nossonumero', 'boletos.dv', 'parcela.id as parcela_id',
                    'financeiroparcela_id', 'parcela.valor', 'parcela.valorefetivado', 'parcela.juros',
                    'parcela.multa', 'parcela.datavencimento', 'financeiro.dataemissao',
                    'cliente.nome as cliente', 'boletos.datahora', 'cancelado', 'parcela.desconto',
                    'boletos.valor_abatimento', 'ocorr.codigo'
                ])->get();
    }

    public function store(Request $request, Boletoremessa $rem)
    {
        $this->authorize('create', $rem);
        $data = $request->all();
        $parsUrl = $data['parsUrl'];
        DB::beginTransaction();
        try {
            $boletos = json_decode($data['boletosJson']);
            $empresa = Session::get('empresa_padrao');

            $conta = Conta::find($boletos[0]->conta_id);
            $conta->update(['boletoremessasequencia' => $conta->boletoremessasequencia + 1]);

            $data['conta_id'] = $boletos[0]->conta_id;
            $data['empresa_id'] = $empresa->id;
            $data['grupo_id'] = $empresa->grupo_id;
            $data['numerosequencia'] = $conta->boletoremessasequencia;
            $data['datahora'] = Carbon::now()->toDateTimeString();
            $data['cancelado'] = false;
            $data['gerouremessa'] = false;

            $boletoRemessa = Boletoremessa::create($data);
            $boletoRemessaFinanceiro = $this->insertBoletoRemessaFinanceiro($boletos, $boletoRemessa, $data);

            if (!is_bool($boletoRemessaFinanceiro))
                return $boletoRemessaFinanceiro;
        } catch (\Exception $e) {
            DB::rollback();
            return redirect('remessa/create?' . urldecode($parsUrl))->withInput()->withErrors(getErrorsException($e));
        }
        DB::commit();
        return redirect('remessa?')->withMessageSuccess("Remessa cadastrada com sucesso!");
    }

    public function update(Request $request, $id)
    {
        $data = $request->all();
        $parsUrl = $data['parsUrl'];
        $boletoRemessa = Boletoremessa::with('boletoremessaFinanceiro')->find($id);
        $now = new \DateTime;
        $this->authorize('update', $boletoRemessa);
        DB::beginTransaction();
        try {
            $boletos = json_decode($data['boletosJson']);
            $empresa = Session::get('empresa_padrao');

            $data['empresa_id'] = $empresa->id;
            $data['grupo_id'] = $empresa->grupo_id;
            $data['numerosequencia'] = $boletoRemessa->numerosequencia;
            $data['datahora'] = $boletoRemessa->datahora;
            $data['cancelado'] = false;
            $data['gerouremessa'] = false;
            $data['conta_id'] = $boletoRemessa->conta_id;

            $boletoRemessa->boletoremessaFinanceiro()->delete();
            $boletoRemessaFinanceiro = $this->insertBoletoRemessaFinanceiro($boletos, $boletoRemessa, $data);
            $boletoRemessa->update(['updated_at' => $now]);

            if (!is_bool($boletoRemessaFinanceiro))
                return $boletoRemessaFinanceiro;
        } catch (\Exception $e) {
            DB::rollback();
            return redirect('remessa/' . $id . "/edit?" . urldecode($parsUrl))->withInput()->withErrors(getErrorsException($e));
        }
        DB::commit();
        return redirect('remessa?')->withMessageSuccess("Remessa atualizada com sucesso!");
    }

    public function show($id, Boletoremessa $rem)
    {
        $this->authorize('view', $rem);
        $empresa_id = Session::get('empresa_padrao')->id;
        $where = ['boletoemite' => true, 'empresa_id' => $empresa_id, 'ativo' => true];
        $contas = SelectRepository::searchConta($where, ['id', 'descricao', 'boletoremessasequencia'])->get();

        $remessa = Boletoremessa::where('id', $id)->with('boletoremessaFinanceiro')->first();
        $codigoBanco = $remessa->conta->banco->codigo;
        if ($remessa->gerouremessa == 1) {
            $file = $this->getFile($id, $codigoBanco);
            if (!$file) {
                Session::flash('message_info', "O arquivo de remessa não foi encontrado no servidor, contate o suporte");
            } else {
                $boletos = $file;
                $userem = true;
            }
        }
        if (!isset($userem)) {
            $boletos = Boleto::whereIn('boletos.financeiroparcela_id', $remessa->boletoremessaFinanceiro->pluck('financeiroparcela_id'))
                    ->with('boletohistorico')->leftJoin('financeiroparcelas parcela', 'parcela.id', 'boletos.financeiroparcela_id')
                    ->leftJoin('financeiros financeiro', 'financeiro.id', 'parcela.financeiro_id')
                    ->leftJoin('ocorrenciasremessas ocorr', 'ocorr.id', 'boletos.ultimaocorrencia_id')
                    ->leftJoin('clientes cliente', 'cliente.id', 'financeiro.cliente_id');

            $boletos = $this->selectBoletos($boletos);
            $userem = false;
        }
        $historicos = Boletoremessa::join('boletohistoricos hist', 'hist.boletoremessa_id', 'boletoremessas.id')
                ->where('boletoremessas.id', $id)
                ->where('hist.cancelado', true)
                ->select([
                    'hist.id', 'hist.dv', 'hist.nossonumero', 'hist.datahora',
                    'hist.info_cancelamento', DB::raw("cast(null as varchar(4)) as parcela_id"),
                    DB::raw("cast('Pedido de Baixa' as varchar(15)) as financeiroparcela_id"),
                    DB::raw("cast('true' as varchar(4)) as historico")
                ])
                ->get();
        if (count($historicos) > 0) {
            $boletos = $boletos->merge($historicos);
            $userem = false;
        }
        $show = true;
        return view('financeiro.boletos.boletoremessa_form', compact('contas', 'boletos', 'remessa', 'show', 'userem'));
    }

    public function edit($id)
    {
        $remessa = Boletoremessa::where('id', $id)->with('boletoremessaFinanceiro')->first();
        $this->authorize('update', $remessa);
        if ($remessa->gerouremessa == 1)
            return redirect("remessa/$id")->withMessageDanger('Não é possível editar uma remessa que teve o arquivo gerado!');

        $empresa_id = Session::get('empresa_padrao')->id;
        $where = ['boletoemite' => true, 'empresa_id' => $empresa_id, 'ativo' => true];
        $contas = SelectRepository::searchConta($where, ['id', 'descricao', 'boletoremessasequencia'])->get();

        if (count($_GET) > 0)
            return $this->filterCreateRemessa($_GET, $empresa_id, $contas, $remessa);

        if ($remessa->cancelado) {
            return $this->show($id);
        }

        $boletos = Boleto::whereIn('boletos.financeiroparcela_id', $remessa->boletoremessaFinanceiro->pluck('financeiroparcela_id'))
                ->with('boletohistorico')->leftJoin('financeiroparcelas parcela', 'parcela.id', 'boletos.financeiroparcela_id')
                ->leftJoin('financeiros financeiro', 'financeiro.id', 'parcela.financeiro_id')
                ->leftJoin('ocorrenciasremessas ocorr', 'ocorr.id', 'boletos.ultimaocorrencia_id')
                ->leftJoin('clientes cliente', 'cliente.id', 'financeiro.cliente_id');
        $boletos = $this->selectBoletos($boletos);

        return view('financeiro.boletos.boletoremessa_form', compact('contas', 'boletos', 'remessa'));
    }

    public function importRetorno(Request $request, Boletoremessa $rem)
    {
        $this->authorize('update', $rem);
        $file = $request->file('file-upload');

        if (is_null($file))
            return redirect()->to('/remessa')->withErrors('Arquivo não encontrado!');

        $processor = new BoletoProcessor();
        $boletos = $processor->processarRetorno($file);
        $ocorrenciasDb = Ocorrenciasremessas::where('tipo', 1)->where('uso_banco', 1)->where('codigo_banco', $processor->getCodigoBanco())->orderBy('codigo')->orderby('descricao')->select('descricao', 'id', 'codigo')->get();
        $ocorrencias = Collect([]);
        foreach ($ocorrenciasDb as $row) {
            $ocorrencias->put($row->id, $row->codigo . ' - ' . $row->descricao);
        }
        $ocorrencias = $ocorrencias->prepend('Selecione', '');
        $ocorrencias_liquidacao = [];
        if($processor->getCodigoBanco() == '104'){
            $ocorrencias_liquidacao = ['21', '22', '35'];
        } else if($processor->getCodigoBanco() == '341'){
            $ocorrencias_liquidacao = ['06', '08', '35'];
        }
        $url = 'remessa.retorno/retornoparcelas/' . implode(',', $boletos->whereIn('ocorrencia', $ocorrencias_liquidacao)->pluck('parcela_id')->toArray());
        return view('financeiro.boletos.boletoretorno', compact('boletos', 'ocorrencias', 'url'));
    }

    public function contasReceber($parcelas)
    {
        $tipo_lancamento = 'R';
        return view('financeiro.contasreceber', compact('tipo_lancamento', 'parcelas'));
    }

    public function cancelarRemessa($id)
    {
        $remessa = Boletoremessa::with('boletoremessaFinanceiro')->find($id);
        $this->authorize('update', $remessa);
        DB::beginTransaction();
        try {
            $remessa->update(['cancelado' => true]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
        return 'OK|';
    }

    public function exportRemessa(Request $request)
    {
        $id = $request->remessa_id;
        DB::beginTransaction();
        try {
            $boletoRemessa = Boletoremessa::where('id', $id)->with('boletoremessaFinanceiro', 'conta')->get()->first();
            $exist = $this->fileExists($id);
            if ($exist) {
                return $this->downloadArquivo($id);
            } else if ($boletoRemessa->gerouremessa == 0) {
                $parcelas = Financeiroparcela::whereIn('id', $boletoRemessa->boletoremessaFinanceiro->pluck('financeiroparcela_id')->toArray())->with('boleto')->get();
                $conta = $boletoRemessa->conta;
                $processor = new BoletoProcessor();
                $processor->setCodigoBanco($conta->banco->codigo);

                DB::table('boletos')->whereIn('financeiroparcela_id', $boletoRemessa->boletoremessaFinanceiro->pluck('financeiroparcela_id')->toArray())->update(['gerouremessa' => true]);
                DB::commit();
                return $processor->getRemessa($parcelas, $conta, $id, $boletoRemessa->numerosequencia, $boletoRemessa);
            } else {
                throw new \Exception("Arquivo de Remessa não encontrado.");
            }
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->to('remessa')->withErrors("Não foi possível gerar a remessa: " . $e->getMessage());
        }
    }

    public function efetivarRemessa(Request $request, Boletoremessa $rem)
    {
        $this->authorize('update', $rem);
        $id = $request->remessa_id;
        $final = $request->final == '1';
        $remessaBuilder = Boletoremessa::join('boletoremessafinanceiros fin', 'fin.boletoremessa_id', 'boletoremessas.id')
                ->join('boletos', 'boletos.financeiroparcela_id', 'fin.financeiroparcela_id')
                ->rightJoin('boletohistoricos hist', 'hist.boleto_id', 'boletos.id')
                ->leftJoin('ocorrenciasremessas correm', 'hist.ocorrencia_id', 'correm.id')
                ->where('boletoremessas.id', $id);
        DB::beginTransaction();
        try {
            $contas = collect([]);
            $historicos = collect([]);
            $md_hist = new Boletohistorico();
            $remessa = clone $remessaBuilder->selectRaw('boletoremessas.*')->get()->first();
            $bols = $remessaBuilder->select("boletos.id", "correm.codigo")->get();
            if (count($bols) > 0) {
                $select = ["boletos.financeiro_id", "boletos.financeiroparcela_id", "boletos.cancelado",
                    "boletos.alteroucancelou", "boletos.id", "contas.id as conta_id", "contas.boletosequencia",
                    "boletos.numerosequencia", "boletos.dv", "boletos.nossonumero", "financeiros.cliente_id",
                    "parc.valor", "boletos.grupo_id", "boletos.empresa_id", "boletos.ultimaocorrencia_id",
                    "boletos.valor_abatimento"];
                $boletos = Boleto::join('contas', 'boletos.conta_id', 'contas.id')
                        ->join('financeiroparcelas parc', 'boletos.financeiroparcela_id', 'parc.id')
                        ->join('financeiros', 'parc.financeiro_id', 'financeiros.id')
                        ->whereIn('boletos.id', $bols->pluck('id'))
                        ->select($select)
                        ->get();

                foreach ($boletos as $boleto) {
                    $info = "Cliente: $boleto->cliente_id, "
                            . "Valor Total: " . requestNumeroDecimalOracle($boleto->valor) . ", "
                            . "Parcela: $boleto->financeiroparcela_id";
                    $ocorrencia = Ocorrenciasremessas::find($boleto->ultimaocorrencia_id);
                    $isBaixa = is_null($ocorrencia) ? false : $ocorrencia->codigo == '02';

                    if (!$isBaixa) {
                        $info = null;
                    }
                    $historico = [
                        'grupo_id'          => $boleto->grupo_id,
                        'empresa_id'        => $boleto->empresa_id,
                        'boleto_id'         => $boleto->id,
                        'conta_id'          => $boleto->conta_id,
                        'datahora'          => Carbon::now()->toDateTimeString(),
                        'numerosequencia'   => $boleto->numerosequencia,
                        'dv'                => $boleto->dv,
                        'nossonumero'       => $boleto->nossonumero,
                        'alteracaodatahora' => Carbon::now()->toDateTimeString(),
                        'alteracaouser_id'  => \Auth::user()->id,
                        "boletoremessa_id"  => $id,
                        "info_cancelamento" => $info,
                        "cancelado"         => $isBaixa,
                        "alteroucancelou"   => $isBaixa,
                        "created_at"        => new \DateTime,
                        "updated_at"        => new \DateTime,
                    ];
                    $dataUpBol = [];
                    $fin = Financeiroparcela::find($boleto->financeiroparcela_id);
                    if ($isBaixa) {
                        if (!is_null($fin)) {
                            $fin->update(['boletogerado' => 0]);
                        }
                        $dataUpBol = [
                            'financeiro_id'        => null,
                            'financeiroparcela_id' => null,
                            'cancelado'            => true,
                            'alteroucancelou'      => true
                        ];
                    }
                    $dataUpBol['pendencia'] = false;
                    $boleto->update($dataUpBol);
                    if ($remessa->cancelado) {
                        $countHist = Boletohistorico::
                                        whereRaw('boleto_id = ' . $boleto->id
                                                . ' and boletohistoricos.boletoremessa_id <>' . $remessa->id
                                                . ' and (boletohistoricos.info_cancelamento is not null)')
                                        ->select('boletohistoricos.boleto_id', 'boletohistoricos.boletoremessa_id')
                                        ->groupBy('boletohistoricos.boleto_id', 'boletohistoricos.boletoremessa_id')
                                        ->get()->count();
                        if ($countHist === 0) {
                            $boleto->update(['gerouremessa' => false]);
                        }
                    } else {
                        $cancelaAbatimento = ! is_null($ocorrencia) && $ocorrencia->codigo == '04';

                        if ($cancelaAbatimento) {
//                            //removido dia 09/07/2018 pois a operação está sendo feita na BoletoProcessor
//                            $dataUp = [];
//                            $dataUp['desconto'] = $fin->desconto - $boleto->valor_abatimento;
//                            $dataUp['valorefetivado'] = $fin-emailkeygoogle>valor + $boleto->valor_abatimento;
//                            if ($dataUp['desconto'] < 0) {
//                                $dataUp['desconto'] = 0;
//                            }
//                            $fin->update($dataUp);
                            $boleto->update(['valor_abatimento' => null]);
                        }
                    }
                    $historicos->push($historico);
                }

                $md_hist->insert($historicos->toArray());
            }

            $conta = Conta::find($remessa->conta_id);
            if ($conta->boletoremessasequencia == $remessa->numerosequencia && $remessa->cancelado) {
                $conta->update(['boletoremessasequencia' => $remessa->numerosequencia - 1]);
            }
            Boletoremessa::find($id)->update(['efetivado' => true]);
        } catch (\Exception $e) {
            DB::rollback();
            $error = getErrorsException($e);
            return $error;
        }
        DB::commit();
        return "OK|";
    }

    private function insertBoletoRemessaFinanceiro($boletos, $boletoRemessa, $data)
    {
        $boletosDB = Boleto::whereIn('id', collect($boletos)->pluck('boleto_id'))->get();
        $msg = "";
        if (count($boletos) > 0) {
            $data['financeiro_id'] = Financeiroparcela::find($boletos[0]->financeiroparcela_id)->financeiro_id;
            foreach ($boletos as $par) {
                $boletoDB = $boletosDB->where('id', $par->boleto_id)->first();
                if (is_null($boletoDB)) {
                    throw new \Exception('Erro ao buscar o boleto [' . $par->boleto_id . ']');
                }
                if (!$boletoDB->imprimiu) {
                    $msg .= "O boleto referente a parcela nº "
                            . $boletoDB->financeiroparcela_id
                            . " ainda não foi impresso."
                            . " Para gerar a remessa você deve imprimí-lo. \n";
                }
                $data['protestardias'] = 0;
                $data['potestar'] = false;
                $data['nossonumero'] = $par->nossonumero;
                $data['financeiroparcela_id'] = $par->financeiroparcela_id;
                $data['boletoremessa_id'] = $boletoRemessa->id;
                Boletoremessafinanceiro::create($data);
            }
        }
        if ($msg) {
            throw new \Exception($msg);
        }
        return true;
    }

    /**
     * Check if there is a file with the id
     *
     * @param  int $id
     * @return boolean $exist
     */
    private function fileExists($id)
    {
        $s = DIRECTORY_SEPARATOR;
        $arch = "REM_$id.rem";
        $cnpj = $cnpj = str_replace(['/', '.', '-'], '', Session::get('empresa_padrao')->cnpj);
        $path = $s . $cnpj . $s . $arch;
        return Storage::disk('boletos')->has($path);
    }

    /**
     * Process to download the file
     *
     * @param int $id
     * @return reponse $download
     */
    private function downloadArquivo($id)
    {
        $s = DIRECTORY_SEPARATOR;
        $arch = "REM_$id.rem";
        $cnpj = $cnpj = str_replace(['/', '.', '-'], '', Session::get('empresa_padrao')->cnpj);
        $path = storage_path('app' . DIRECTORY_SEPARATOR . 'boletos') . $s . $cnpj . $s . $arch;
        return response()->download($path);
    }

    /**
     * Check and return array with boletos
     *
     * @param  int $id
     * @return array $boletos
     */
    private function getFile($id, $codigoBanco)
    {
        $s = DIRECTORY_SEPARATOR;
        $arch = "REM_$id.rem";
        $cnpj = str_replace(['/', '.', '-'], '', Session::get('empresa_padrao')->cnpj);
        $path = $s . $cnpj . $s . $arch;
        $has = Storage::disk('boletos')->has($path);
        if (!$has)
            return false;

        $file = Storage::disk('boletos')->get($path);
        $remessa = explode("\r\n", $file);
        $count = 0;
        $total = count($remessa);
        $boletos = collect([]);
        foreach ($remessa as $key => $rem) {
            if ($key == 0 && empty($rem)) {
                unset($remessa[$key]);
                continue;
            }
            if ($count < $total) {
                if($codigoBanco == '104'){
                    $regs = $this->getRegistroCaixa($rem);
                } else if($codigoBanco == '341'){
                    $regs = $this->getRegistroItau($rem);
                }

                if (!is_bool($regs))
                    $boletos->push($regs);
            }
        }
        return $boletos;
    }

    /**
     * Returns the registers formated from the remessa
     *
     * @param  string $remessa
     * @return object $objBoleto
     */
    private function getRegistroCaixa($remessa)
    {
        $ocorrencia = mb_substr($remessa, 108, 2);
        $codReg = substr($remessa, 0, 1);

        if ($codReg != '1')
            return false;
        if ($ocorrencia == '02')
            return false;

        $vencimento = mb_substr($remessa, 120, 6);
        $emissao = mb_substr($remessa, 150, 6);
        $cliente = mb_substr($remessa, 234, 40);
        $valor = (int) mb_substr($remessa, 126, 13);
        $multa = (int) mb_substr($remessa, 357, 10);
        $nosso = mb_substr($remessa, 56, 17);
        $dv = Util::modulo11($nosso);
        $dataven = Carbon::createFromFormat('d/m/y', mask($vencimento, '##/##/##'))->format('Y-m-d');
        $dataem = Carbon::createFromFormat('d/m/y', mask($emissao, '##/##/##'))->format('Y-m-d');
        $string = str_pad("", strlen($valor) - 2, "#") . ".##";
        $valorbo = mask(strval($valor), $string);
        if ($multa > 0) {
            $str = str_pad("", strlen($multa) - 2, "#") . ".##";
            $multabo = mask(strval($multa), $str);
        } else {
            $multabo = 0;
        }
        $objboleto = (object) [];
        $objboleto->id = 'Remessa';
        $objboleto->parcela_id = 'Remessa';
        $objboleto->financeiroparcela_id = 'Remessa';
        $objboleto->nossonumero = $nosso;
        $objboleto->dv = $dv;
        $objboleto->cliente = rtrim($cliente);
        $objboleto->remessa = true;
        $objboleto->datahora = $dataem;
        $objboleto->datavencimento = $dataven;
        $objboleto->valor = $valorbo;
        $objboleto->multa = $multabo;
        $objboleto->juros = 0;
        return $objboleto;
    }
    private function getRegistroItau($remessa)
    {
        $ocorrencia = mb_substr($remessa, 108, 2);
        $codReg = substr($remessa, 0, 1);

        if ($codReg != '1')
            return false;
        if ($ocorrencia == '02')
            return false;

        $vencimento = mb_substr($remessa, 120, 6);
        $emissao = mb_substr($remessa, 150, 6);
        $cliente = mb_substr($remessa, 234, 30);
        $valor = (int) mb_substr($remessa, 126, 13);
        $multa = (int) mb_substr($remessa, 160, 13);
        $nosso = mb_substr($remessa, 62, 8);
        $dv = Util::modulo11($nosso);
        $dataven = Carbon::createFromFormat('d/m/y', mask($vencimento, '##/##/##'))->format('Y-m-d');
        $dataem = Carbon::createFromFormat('d/m/y', mask($emissao, '##/##/##'))->format('Y-m-d');
        $string = str_pad("", strlen($valor) - 2, "#") . ".##";
        $valorbo = mask(strval($valor), $string);
        if ($multa > 0) {
            $str = str_pad("", strlen($multa) - 2, "#") . ".##";
            $multabo = mask(strval($multa), $str);
        } else {
            $multabo = 0;
        }
        $objboleto = (object) [];
        $objboleto->id = 'Remessa';
        $objboleto->parcela_id = 'Remessa';
        $objboleto->financeiroparcela_id = 'Remessa';
        $objboleto->nossonumero = $nosso;
        $objboleto->dv = $dv;
        $objboleto->cliente = rtrim($cliente);
        $objboleto->remessa = true;
        $objboleto->datahora = $dataem;
        $objboleto->datavencimento = $dataven;
        $objboleto->valor = $valorbo;
        $objboleto->multa = 0;
        $objboleto->juros = $multabo;
        return $objboleto;
    }

}
