<?php

namespace App\Http\Controllers;

use App\Helpers\Utils\NfUtil;
use DB;
use App\Condicaopagamento;
use App\Condicaopagamentoparcela;
use App\Contamovimentotipo;
use App\Pedidosituacao;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\CondicaoPagamentoRequest;

class CondicaopagamentoController extends Controller
{

    protected $tipos = [
        0 => 'À Vista - Outros',
        1 => 'À Prazo - Outros',
        2 => 'À Vista - Cartão',
        3 => 'À Prazo - Cartão',
        4 => 'Convênio',
        5 => 'Vale Gás'
    ];

    function index(Condicaopagamento $con)
    {
        $this->authorize('view', $con);
        $condicaopagamentos = Condicaopagamento::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id],
        ])->get();
        return View('pagamentos.condicaopagamento.condicaopagamento', compact('condicaopagamentos'));
    }

    function create(Condicaopagamento $con)
    {
        $this->authorize('create', $con);
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $tipos = $this->tipos;
        $tipo = '';
        $nfc_tpag = $this->getTPag();
        $pedidosituacaos = Pedidosituacao::where('empresa_id', Session::get('empresa_padrao')->id)->where('ativo', true)->pluck('descricao', 'id')->prepend("Selecione", "");
        $contamovimentotipos = Contamovimentotipo::where('ativo', true)
            ->where('grupo_id', $grupo_id)
            ->select(DB::raw("((CASE WHEN pagarreceber = 'P' THEN 'Pagamentos - ' WHEN pagarreceber = 'R' THEN 'Recebimentos - ' ELSE 'Ambos - ' END) || descricao) descricao"), "id")
            ->orderBy('descricao')
            ->pluck('descricao', 'id')
            ->prepend('Selecione', '');

        return View('pagamentos.condicaopagamento.condicaopagamento_form', compact('tipos', 'tipo', 'nfc_tpag', 'pedidosituacaos', 'contamovimentotipos'));
    }

    function store(CondicaoPagamentoRequest $request, Condicaopagamento $con)
    {
        $this->authorize('create', $con);
        DB::beginTransaction();
        try {
            $data = $request->only('tipo');
            $this->validateTpag($request->only('nfc_tpag')['nfc_tpag']);
            if ($data['tipo'] === '0') {
                $dados = $request->only('descricao', 'dias_primeira', 'ativo', 'tipo', 'enviaappnf', 'pedidosituacaoappnf_id', 'appnfceauto', 'contamovimentotipo_id');
                $dados['empresa_id'] = Session::get('empresa_padrao')->id;
                $dados['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
                $dados['nfc_tpag'] = $request->only('nfc_tpag')['nfc_tpag'];
                $condicaopagamento = Condicaopagamento::create($dados);
            } else if ($data['tipo'] === '1') {
                $dados = $request->only('descricao', 'dias_primeira', 'ativo', 'num_parcelas', 'intervalo', 'tipo', 'enviaappnf', 'pedidosituacaoappnf_id', 'appnfceauto', 'contamovimentotipo_id');
                $dados['empresa_id'] = Session::get('empresa_padrao')->id;
                $dados['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
                $dados['dias_primeira'] = '0';
                $percetualValorTotal = 0;
                $dados['nfc_tpag'] = $request->only('nfc_tpag')['nfc_tpag'];
                $condicaopagamento = Condicaopagamento::create($dados);
                for ($i = 1; $i <= $dados['num_parcelas']; $i++) {
                    $condicaopagamentoparcelas = new Condicaopagamentoparcela();
                    $percentualvalor = $request->only('percentual' . $i)['percentual' . $i];
                    $dias = $request->only('dias' . $i)['dias' . $i];
                    $percetualValorTotal = insertPercentualOracle($percentualvalor) + $percetualValorTotal;
                    if ($dias !== '' and $percentualvalor !== '') {
                        $condicaopagamentoparcelas->dias = $dias;
                        $condicaopagamentoparcelas->percentualvalor = $percentualvalor !== null ? insertPercentualOracle($percentualvalor) : (100 - $percetualValorTotal);
                        $condicaopagamentoparcelas->condicaopagamento_id = $condicaopagamento->id;
                        $condicaopagamentoparcelas->save();
                    } else {
                        throw new \Exception('Os dias e o percentual de cada parcela não devem ser nulo!');
                    }
                }
            } else if ($data['tipo'] === '2') {
                $dados = $request->only('descricao', 'dias_primeira', 'ativo', 'tipo', 'taxa', 'fornecedor_id', 'enviaappnf', 'pedidosituacaoappnf_id', 'appnfceauto', 'contamovimentotipo_id');
                $dados['empresa_id'] = Session::get('empresa_padrao')->id;
                $dados['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
                if ('0,00 %' === $dados['taxa']) {
                    DB::rollback();
                    throw new \Exception('A taxa não pode ser igual a zero');
                }
                $dados['taxa'] = insertPercentualOracle($dados['taxa']);
                $dados['nfc_tpag'] = $request->only('nfc_tpag')['nfc_tpag'];
                $condicaopagamento = Condicaopagamento::create($dados);
            } else if ($data['tipo'] === '3') {
                $dados = $request->only(
                    'descricao',
                    'dias_primeira',
                    'ativo',
                    'min_parcelas',
                    'intervalo',
                    'max_parcelas',
                    'taxa',
                    'tipo',
                    'fornecedor_id',
                    'enviaappnf',
                    'pedidosituacaoappnf_id',
                    'appnfceauto',
                    'contamovimentotipo_id'
                );
                if ($dados['min_parcelas'] >= $dados['max_parcelas']) {
                    DB::rollback();
                    throw new \Exception('O valor mínimo de parcelas não dever ser maior ou igual que o máximo');
                }
                if ($dados['taxa'] === '0,00 %') {
                    DB::rollback();
                    throw new \Exception('A taxa não pode ser igual a zero');
                }
                $dados['taxa'] = insertPercentualOracle($dados['taxa']);
                for ($i = $dados['min_parcelas']; $i <= $dados['max_parcelas']; $i++) {
                    $condicaoPagamento = new Condicaopagamento();
                    $condicaoPagamento->tipo = $dados['tipo'];
                    $condicaoPagamento->grupo_id = Session::get('empresa_padrao')->grupo_id;
                    $condicaoPagamento->empresa_id = Session::get('empresa_padrao')->id;
                    $condicaoPagamento->descricao = strtoupper($dados['descricao']) . ' ' . $i . ' PARCELA(S)';
                    $condicaoPagamento->dias_primeira = $dados['dias_primeira'];
                    $condicaoPagamento->num_parcelas = $i;
                    $condicaoPagamento->intervalo = $dados['intervalo'];
                    $condicaoPagamento->ativo = $dados['ativo'];
                    $condicaoPagamento->enviaappnf = $dados['enviaappnf'];
                    $condicaoPagamento->appnfceauto = $dados['appnfceauto'];
                    $condicaoPagamento->pedidosituacaoappnf_id = $dados['pedidosituacaoappnf_id'];
                    $condicaoPagamento->nfc_tpag = $request->only('nfc_tpag')['nfc_tpag'];
                    $condicaoPagamento->taxa = $dados['taxa'];
                    $condicaoPagamento->fornecedor_id = $dados['fornecedor_id'];
                    $condicaoPagamento->save();
                }
            } else {
                $dados = $request->only('descricao', 'ativo', 'tipo', 'enviaappnf', 'pedidosituacaoappnf_id', 'appnfceauto', 'contamovimentotipo_id');
                if ($dados['descricao'] === '') {
                    throw new \Exception('O campo Descrição é obrigatório.');
                }
                $dados['dias_primeira'] = 0;
                $dados['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
                $dados['empresa_id'] = Session::get('empresa_padrao')->id;
                $dados['nfc_tpag'] = $request->only('nfc_tpag')['nfc_tpag'];
                $condicaopagamento = Condicaopagamento::create($dados);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/condicaopagamento/create')
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('condicaopagamento.index')->withMessageSuccess("Cadastrado com sucesso!");
    }

    function edit($id, Condicaopagamento $con)
    {
        $this->authorize('update', $con);
        $condicaopagamento = Condicaopagamento::findOrFail($id);
        $this->authorize('igualdade', $condicaopagamento);
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $tipos = $this->tipos;
        $condicaopagamento->taxa = requestPercentualOracle($condicaopagamento->taxa);
        $tipo = $condicaopagamento->tipo;
        $pedidosituacaos = Pedidosituacao::where('empresa_id', Session::get('empresa_padrao')->id)->where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id')->prepend("Selecione", "");
        $contamovimentotipos = Contamovimentotipo::where('ativo', true)
            ->where('grupo_id', $grupo_id)
            ->select(DB::raw("((CASE WHEN pagarreceber = 'P' THEN 'Pagamentos - ' WHEN pagarreceber = 'R' THEN 'Recebimentos - ' ELSE 'Ambos - ' END) || descricao) descricao"), "id")
            ->orderBy('descricao')
            ->pluck('descricao', 'id')
            ->prepend('Selecione', '');

        $nfc_tpag = $this->getTPag();
        if ($tipo !== '1') {
            return View(
                'pagamentos.condicaopagamento.condicaopagamento_edit',
                compact('condicaopagamento', 'tipos', 'tipo', 'nfc_tpag', 'pedidosituacaos', 'contamovimentotipos')
            );
        } else {
            $condicaopagamentoparcelas = Condicaopagamentoparcela::where('condicaopagamento_id', $id)->get();
            $num_parcelas = count($condicaopagamentoparcelas);
            return View(
                'pagamentos.condicaopagamento.condicaopagamentoprazo_edit',
                compact(
                    'condicaopagamento',
                    'tipos',
                    'tipo',
                    'condicaopagamentoparcelas',
                    'num_parcelas',
                    'nfc_tpag',
                    'pedidosituacaos',
                    'contamovimentotipos'
                )
            );
        }
    }

    function show($id, Condicaopagamento $con)
    {
        $this->authorize('view', $con);
        $condicaopagamento = Condicaopagamento::findOrFail($id);
        $this->authorize('igualdade', $condicaopagamento);
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $tipos = $this->tipos;
        $condicaopagamento->taxa = requestPercentualOracle($condicaopagamento->taxa);
        $tipo = $condicaopagamento->tipo;
        $show = true;
        $nfc_tpag = $this->getTPag();
        $pedidosituacaos = Pedidosituacao::where('empresa_id', Session::get('empresa_padrao')->id)->where('ativo', true)->pluck('descricao', 'id')->prepend("Selecione", "");
        $contamovimentotipos = Contamovimentotipo::where('ativo', true)
            ->where('grupo_id', $grupo_id)
            ->select(DB::raw("((CASE WHEN pagarreceber = 'P' THEN 'Pagamentos - ' WHEN pagarreceber = 'R' THEN 'Recebimentos - ' ELSE 'Ambos - ' END) || descricao) descricao"), "id")
            ->orderBy('descricao')
            ->pluck('descricao', 'id')
            ->prepend('Selecione', '');

        if ($tipo !== '1') {
            return View(
                'pagamentos.condicaopagamento.condicaopagamento_edit',
                compact('condicaopagamento', 'show', 'tipos', 'tipo', 'nfc_tpag', 'pedidosituacaos', 'contamovimentotipos')
            );
        } else {
            $condicaopagamentoparcelas = Condicaopagamentoparcela::where('condicaopagamento_id', $id)->get();
            $num_parcelas = count($condicaopagamentoparcelas);
            return View(
                'pagamentos.condicaopagamento.condicaopagamentoprazo_edit',
                compact(
                    'condicaopagamento',
                    'show',
                    'tipos',
                    'tipo',
                    'condicaopagamentoparcelas',
                    'num_parcelas',
                    'nfc_tpag',
                    'pedidosituacaos',
                    'contamovimentotipos'
                )
            );
        }
    }

    function update(CondicaoPagamentoRequest $request, $id, Condicaopagamento $con)
    {
        $this->authorize('update', $con);
        $condicaoPagamento = Condicaopagamento::findOrFail($id);
        $this->authorize('igualdade', $condicaoPagamento);
        DB::beginTransaction();
        $tipo = $request->only('tipo');
        try {
            $this->validateTpag($request->only('nfc_tpag')['nfc_tpag']);

            if ($tipo['tipo'] === '4' || $tipo['tipo'] === '5') {
                $data = $request->only('descricao', 'ativo', 'enviaappnf', 'appnfceauto');
            } else if ($tipo['tipo'] === '3') {
                $data = $request->only('descricao', 'dias_primeira', 'intervalo', 'taxa', 'ativo', 'fornecedor_id', 'enviaappnf', 'appnfceauto');
                $data['taxa'] = insertPercentualOracle($data['taxa']);
            } else if ($tipo['tipo'] === '2') {
                $data = $request->only('descricao', 'dias_primeira', 'taxa', 'ativo', 'fornecedor_id', 'enviaappnf', 'appnfceauto');
                $data['taxa'] = insertPercentualOracle($data['taxa']);
            } else if ($tipo['tipo'] === '1') {
                $data = $request->only('descricao', 'num_parcelas', 'ativo', 'enviaappnf', 'appnfceauto');
                $percetualValorTotal = 0;
                for ($i = 1; $i <= $data['num_parcelas']; $i++) {
                    $percentualvalor = $request->only('percentual' . $i)['percentual' . $i];
                    $dias = $request->only('dias' . $i)['dias' . $i];
                    $percetualValorTotal = insertPercentualOracle($percentualvalor) + $percetualValorTotal;
                    if ($dias !== '' and $percentualvalor !== '') {
                        $percentualvalor = $percentualvalor !== null ? insertPercentualOracle($percentualvalor) : (100 - $percetualValorTotal);
                        Condicaopagamentoparcela::where('id', $request->only('idparcela' . $i)['idparcela' . $i])
                            ->update(['percentualvalor' => $percentualvalor, 'dias' => $dias]);
                    } else {
                        throw new \Exception('Os dias e o percentual de cada parcela não devem ser nulo!');
                    }
                }
            } else if ($tipo['tipo'] === '0') {
                $data = $request->only('descricao', 'ativo', 'dias_primeira', 'enviaappnf', 'appnfceauto');
            } else {
                DB::rollback();
                throw new \Exception('O tipo está incorreto!');
            }
            $data['nfc_tpag'] = $request->only('nfc_tpag')['nfc_tpag'];
            $data['pedidosituacaoappnf_id'] = $request->only('pedidosituacaoappnf_id')['pedidosituacaoappnf_id'];
            $data['contamovimentotipo_id'] = $request->only('contamovimentotipo_id')['contamovimentotipo_id'];

            $condicaoPagamento->update($data);
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/condicaopagamento/' . $id . '/edit')
                ->withMessageDanger($e->getMessage() . ' - ' . $e->getLine())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('condicaopagamento.index')->withMessageSuccess('Atualizado com sucesso!');
    }

    function destroy($id, Condicaopagamento $con)
    {
        $this->authorize('delete', $con);
        $condicao = Condicaopagamento::find($id);
        $this->authorize('igualdade', $condicao);
        DB::beginTransaction();
        try {
            Condicaopagamento::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function getTPag()
    {
        return NfUtil::getTPag();
    }

    private function validateTpag($nfc_tpag)
    {
        $raw = "grupo_id = " . Session::get('empresa_padrao')->grupo_id . " AND "
            . "(nfceemite = 1 OR nfeemite =  1)";
        $first = DB::table('empresas e')->whereRaw($raw)->selectRaw('count(*) as count')->get()->first();
        $nfEmit = is_null($first) ? false : $first->count > 0;
        if (strlen($nfc_tpag) === 0 && $nfEmit) {
            $msg = 'O campo Tipo Pagamento NF é obrigatório quando alguma empresa do grupo emite NF-e ou NFC-e';
            throw new \Exception($msg);
        }
    }
}
