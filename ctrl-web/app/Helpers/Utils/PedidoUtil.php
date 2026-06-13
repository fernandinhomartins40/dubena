<?php

namespace App\Helpers\Utils;

use StdClass;
use App\User;
use Exception;
use App\Cliente;
use App\Financeiro;
use App\Financeirorateio;
use App\Financeiroparcela;
use App\Condicaopagamentoparcela;
use App\Processors\financeiroProcessor;
use App\Services\CarbonCustom as Carbon;
use App\Repository\SelectRepository as Repository;
use DB;

/**
 * Description of PedidoUtil
 *
 * @author Jeferson
 */
final class PedidoUtil
{

    public static $empresa_id;
    public static $grupo_id;

    public static function isCancelado($situacao)
    {
        return static::isFechadoCancelado($situacao) || $situacao->entregacancelada;
    }

    public static function isFechadoCancelado($situacao)
    {
        return $situacao->fechadocancelado == '1';
    }

    public static function isRealizada($situacao)
    {
        return $situacao->fechadoconcluido || $situacao->entregafinalizada;
    }

    public static function isFechadoConcl($situacao)
    {
        return $situacao->fechadoconcluido == "1";
    }

    public static function useSameDateEntregaEntregador($situacao)
    {
        return $situacao->pedidolidomovel || $situacao->pedidorecebidomovel || $situacao->ementrega || static::isRealizada($situacao);
    }

    public static function condicaoGeraFinanceiro($condicao)
    {
        return $condicao != null and $condicao->tipo !== '5';
    }

    public static function allwedToCreateFinanceiro($condicao, $situacao)
    {
        return static::condicaoGeraFinanceiro($condicao) && !static::isFechadoCancelado($situacao);
    }

    public static function getJsonSituacaoCancelado($situacao)
    {
        return static::toJson($situacao->filter(function ($s) {
                            return static::isCancelado($s);
                        })->pluck('descricao', 'id'));
    }

    public static function getJsonSituacaoFecConcluido($situacao)
    {
        return static::toJson($situacao->where('fechadoconcluido', 1)->pluck('descricao', 'id'));
    }

    public static function getJsonSituacaoEntregaFin($situacao)
    {
        return static::toJson($situacao->where('entregafinalizada', 1)->pluck('descricao', 'id'));
    }

    public static function getJsonPedidoForm($pedido, $historico, $preco, $condicao_pagamento_cli, $produtosConvenio)
    {
        return static::toJson(compact('pedido', 'historico', 'preco', 'condicao_pagamento_cli', 'produtosConvenio'));
    }

    public static function toJson($arr)
    {
        return json_encode($arr);
    }

    public static function getClienteSelectize($cli)
    {
        return (object) [
                    'nome'   => $cli->nome,
                    'rua'    => $cli->rua->descricao,
                    'numero' => $cli->numero,
                    'bairro' => $cli->bairro->descricao,
                    'cidade' => $cli->cidade->descricao,
                    'id'     => $cli->id
        ];
    }

    public static function getRuaSelectize($pedido)
    {
        return (object) [
                    'descricao' => $pedido->entregarua->descricao,
                    'id'        => $pedido->entregarua_id
        ];
    }

    public static function isConclCanc($sit)
    {
        return $sit->fechadocancelado || $sit->fechadoconcluido;
    }

    public static function allowedEstornoParcela($financeiro_id)
    {
        $pars = Repository::getFinanceiroAllowedCanc($financeiro_id);
        if (is_null($pars))
            return true;
        else
            return is_null($pars->cheque) && is_null($pars->ec) && is_null($pars->eccheque) && is_null($pars->bol);
    }

    public static function generateParcela($numero, $dataCompetencia, $vencimento, $valorVenda, $desconto)
    {
        $parcn = new Financeiroparcela();
        $parcn["grupo_id"] = static::$grupo_id;
        $parcn["empresa_id"] = static::$empresa_id;
        $parcn["numero"] = $numero;
        $parcn["datacompetencia"] = $dataCompetencia;
        $parcn["datavencimento"] = $vencimento;
        $parcn["datahorabaixa"] = null;
        $parcn["valor"] = $valorVenda + $desconto;
        $parcn["multa"] = 0;
        $parcn["juros"] = 0;
        $parcn["desconto"] = $desconto;
        $parcn["valorefetivado"] = $valorVenda;
        $parcn["pagarreceber"] = "R";
        $parcn["baixado"] = false;
        return $parcn;
    }

    public static function gerarFinanceiro($data, $pedido, $condicaoPagamento, $config)
    {

        $finProcessor = new financeiroProcessor();
        $valorVenda = $data["valorvenda"];
        if (is_array($data)) {
            $desconto = array_key_exists("valordesconto", $data) ? $data["valordesconto"] : 0 ;
            $taxaEntrega = array_key_exists("entregataxa", $data) ? $data["entregataxa"] : 0 ;
        } else {
            $desconto = isset($data->valordesconto) ? $data->valordesconto : 0 ;
            $taxaEntrega = isset($data->entregataxa) ? $data->entregataxa : 0 ;
        }
        $previsao = Carbon::parse($data['datahoraprevisaoentrega']);
        static::$grupo_id = $pedido->grupo_id;
        static::$empresa_id = $pedido->empresa_id;
        $finProcessor->setFinanceiro(static::generateFinanceiroModel($data, $pedido, $condicaoPagamento, $desconto));
        $finProcessor->setRateios(static::generateRateioModel($config, $valorVenda, $taxaEntrega, $desconto, $finProcessor));

        $parcelasnovo = [];
        if(isset($data["nfweb"]) && $data["nfweb"]){
            //APP NF gera somente uma parcela
            $parcn = static::generateParcela(1, $previsao->copy(), $data["nfweb_vencimento"], $valorVenda, $desconto);
            array_push($parcelasnovo, $parcn);
        } else
        {
            //Não é APP NF
            $parcelas = Condicaopagamentoparcela::where('condicaopagamento_id', $condicaoPagamento->id)->get();
            if (count($parcelas) > 0) {
                $valorRestanteParcelas = $valorVenda;
                $descontoRestante = $desconto;
                $descontoOriginal = $desconto;
                $i = 0;
                $diasParcela = 0;
                foreach ($parcelas as $parcela) {
                    $diasParcela += (int) $parcela->dias;
                    $valorParcela = round((($parcela->percentualvalor / 100) * $valorVenda), 2);
                    $valorParcela = $i == count($parcelas) - 1 ? $valorRestanteParcelas : $valorParcela;
                    $desconto = round((($parcela->percentualvalor / 100) * $descontoOriginal), 2);
                    $desconto = $i == count($parcelas) - 1 ? $descontoRestante : $desconto;
                    $valorRestanteParcelas = $valorRestanteParcelas - $valorParcela;
                    $descontoRestante = $descontoRestante - $desconto;
                    $parcn = static::generateParcela($i + 1, $previsao->copy(), $previsao->copy()->addDays($diasParcela), $valorParcela, $desconto);
                    $i++;
                    array_push($parcelasnovo, $parcn);
                }
            } else if (count($parcelasnovo) == 0 and $condicaoPagamento->num_parcelas > 0) {
                $parcelas = $condicaoPagamento->num_parcelas;
                $diasParcela = $condicaoPagamento->dias_primeira;
                $valorParcelaOriginal = round($valorVenda / $parcelas, 2);
                $descontoOriginal = round($desconto / $parcelas, 2);
                $descontoTotal = $desconto;
                $valorRestanteParcelas = $valorVenda;
                for ($i = 1; $i <= $parcelas; $i++) {
                    $valorParcela = $i == (int) $parcelas ? $valorRestanteParcelas : $valorParcelaOriginal;
                    $valorRestanteParcelas = $valorRestanteParcelas - $valorParcela;
                    $desconto = $i == (int) $parcelas ? $descontoTotal - ($descontoOriginal * ($parcelas - 1)) : $descontoOriginal;
                    $parcn = static::generateParcela($i, $previsao->copy(), $previsao->copy()->addDays($diasParcela), $valorParcela, $desconto);
                    $diasParcela += $condicaoPagamento->dias_primeira;
                    array_push($parcelasnovo, $parcn);
                }
            } else if (count($parcelasnovo) == 0) {
                if ($condicaoPagamento->tipo == '4') {
                    if (is_null($pedido->cliente->convenioempresa)) {
                        throw new Exception("Este cliente não possúi convênio com nenhuma empresa.");
                    }
                    if (is_null($pedido->cliente->convenioempresa->clienteConvenio)) {
                        throw new Exception("Impossível buscar o convênio.");
                    }
                    $vencimento = $pedido->cliente->convenioempresa->clienteConvenio->diavencimento;
                    $fechamento = $pedido->cliente->convenioempresa->clienteConvenio->diafechamento;
                } else {
                    $vencimento = $previsao;
                    $fechamento = null;
                }

                if (strlen($vencimento) < 3) {
                    $vencimento = getProximoVencimento($vencimento, $fechamento);
                }

                $parcn = static::generateParcela(1, $previsao->copy(), $vencimento, $valorVenda, $desconto);
                array_push($parcelasnovo, $parcn);
            }
        }
        $finProcessor->setParcelas($parcelasnovo);
        if (!$finProcessor->gravar()) {
            throw new Exception(implode('', $finProcessor->getErrors()));
        }
        return $finProcessor->getFinanceiro()->id;
    }

    public static function generateRateioModel($config, $valorVenda, $taxaEntrega, $desconto, $financeiroProcessor)
    {
        $rateionovo = [];
        $ratn = new Financeirorateio();
        if (strlen($config->centrocusto_id) > 0 && strlen($config->planoconta_id) > 0) {
            $ratn["centrocusto_id"] = $config->centrocusto_id;
            $ratn["planoconta_id"] = $config->planoconta_id;
        } else {
            return 'Por favor, informe o Centro de Custos e Plano de Contas nas configurações da empresa!';
        }

        $total = $valorVenda + $desconto;
        $ratn["valor"] = $total - $taxaEntrega;
        $ratn["financeiro_id"] = $financeiroProcessor->getFinanceiro()->id;
        $percentRat = sprintf("%0.7f", 1 - ($taxaEntrega / $total));
        $ratn["percentual"] = $percentRat;
        array_push($rateionovo, $ratn);
        if ($taxaEntrega) {
            $ratn = new Financeirorateio();
            if (strlen($config->centrocusto_id) > 0 && strlen($config->planoconta_id) > 0) {
                $ratn["centrocusto_id"] = $config->ccfrete_id;
                $ratn["planoconta_id"] = $config->pcfrete_id;
            } else {
                return 'Por favor, informe o Centro de Custos e Plano de Contas de Frete nas configurações da empresa!';
            }

            $ratn["valor"] = $taxaEntrega;
            $ratn["financeiro_id"] = $financeiroProcessor->getFinanceiro()->id;
            $ratn["percentual"] = 1 - $percentRat;
            array_push($rateionovo, $ratn);
        }
        return $rateionovo;
    }

    public static function generateFinanceiroModel($data, $pedido, $condicaoPagamento, $desconto)
    {
        $hora = $data["datahoraprevisaoentrega"];

        $financeiro = new Financeiro();
        $financeiro->cliente_id = $pedido->cliente_id;
        $financeiro->pagarreceber = 'R';
        $financeiro->descricao = 'Pedido número: ' . $pedido->id;
        $financeiro->documento = $pedido->id;
        $financeiro->valor = $pedido->valorvenda + $desconto;
        $financeiro->datacompetencia = $hora;
        $financeiro->dataemissao = $hora;
        $financeiro->grupo_id = static::$grupo_id;
        $financeiro->empresa_id = static::$empresa_id;
        $financeiro->condicaopagamento_id = $condicaoPagamento->id;
        if(isset($data["cartaoautorizacao"]) && $data["cartaoautorizacao"] != null && $data["cartaoautorizacao"] != ''){
            $financeiro->cartaoautorizacao = $data["cartaoautorizacao"];
        }
        if(isset($data["cartaonsu"]) && $data["cartaonsu"] != null && $data["cartaonsu"] != ''){
            $financeiro->cartaonsu = $data["cartaonsu"];
        }

        return $financeiro;
    }

    public static function dadosExtras($data, $userDefault, $grupo_id, $pedido = null, $condicaopagamentogp_id = null)
    {
        static::$empresa_id = $data['empresa_id'];
        static::$grupo_id = $grupo_id;

        $data['grupo_id'] = $grupo_id;
        $data['atendenteuser_id'] = $userDefault->id;
        $data['condicaopagamento_id'] = str_replace('-0', '', $data['condicaopagamento_id']);
        $data['condicaopagamento_id'] = str_replace('-1', '', $data['condicaopagamento_id']);
        $data['condicaopagamento_id'] = str_replace('-2', '', $data['condicaopagamento_id']);
        $data['condicaopagamento_id'] = str_replace('-3', '', $data['condicaopagamento_id']);
        $data['condicaopagamento_id'] = str_replace('-4', '', $data['condicaopagamento_id']);
        $data['condicaopagamento_id'] = str_replace('-5', '', $data['condicaopagamento_id']);
        $data['valorvenda'] = insertNumeroDecimalOracle($data['valorvenda']);
        $data['valordesconto'] = array_key_exists("valordesconto", $data) ? insertNumeroDecimalOracle($data['valordesconto']) : 0 ;
        $data['entregataxa'] = insertNumeroDecimalOracle($data['entregataxa']);
        $data['datahoraprevisaoentrega'] = 20 . insertDataOracle($data['datahoraprevisaoentrega']);
        $data['datahora'] = Carbon::now();
        $data['datahoraacao'] = 20 . insertDataOracle($data['datahoraacao']);
        $data['entregatelefone'] = $data['entregatelefone'];
        $data['entregaurgente'] = isset($data['entregaurgente']) ? 1 : 0;
        $user = User::where('colaborador_id', $data['colaborador_id'])->first();
        $data['colaborador_id'] = $data['colaborador_id'];
        $data['entregadoruser_id'] = isset($user->colaborador_id) ? $user->id : null;
        $data['numerocartao'] = static::mascaraCartao($data['numerocartao']);

        $cliente = Cliente::find($data['cliente_id']);

        if (!isset($data['entregarua_id']) or $data['entregarua_id'] === '' or ! isset($data['entregabairro_id']) or $data['entregabairro_id'] === '')
            throw new Exception("Por favor, digite o endereço completo para a entrega.");

        if ($pedido !== null)
            $buscarLatLong = $data['entregarua_id'] != $pedido->entregarua_id || $data['entreganumero'] != $pedido->entreganumero;
        else
            $buscarLatLong = $cliente->rua_id != $data['entregarua_id'] || $cliente->numero != $data['entreganumero'];

        if ($buscarLatLong) {
            $latLong = buscaLatitudeLongitude($data['ufentrega'], $data['entregacidade_id'], $data['entregabairro_id'], $data['entregarua_id'], $data['entreganumero']);
            $data['latitude'] = $latLong->location->lat;
            $data['longitude'] = $latLong->location->lng;
        } else {
            $data['latitude'] = $cliente->latitude;
            $data['longitude'] = $cliente->longitude;
        }
        $data['gasdopovo'] = $cliente->gasdopovo && $data['condicaopagamento_id'] == $condicaopagamentogp_id;

        return emptyToNull($data);
    }

    public static function mascaraCartao($num)
    {
        $cartaoMascarado = '';
        $num = str_replace(' ', '', $num);
        if (strlen($num) === 16) {
            for ($i = 0; $i < strlen($num); $i++) {
                if ($i >= 2 && $i <= 7)
                    $cartaoMascarado .= "*";
                else
                    $cartaoMascarado .= substr($num, $i, 1);
            }
        }
        return $cartaoMascarado;
    }

    public static function dadosExtrasMonitoramento($data, $pedido, $config)
    {
        if (!isset($data['modalsetor_id']) || $data['modalsetor_id'] === '')
            throw new Exception('O campo Setor é obrigatório');
        if (!isset($data['modalcolaborador_id']) || $data['modalcolaborador_id'] === '')
            throw new Exception('O campo Colaborador é obrigatório');
        if (!isset($data['modalpedidosituacao_id']) || $data['modalpedidosituacao_id'] === '')
            throw new Exception('O campo Status é obrigatório');
        if (!isset($data['modalcondicaopagamento_id']) || $data['modalcondicaopagamento_id'] === '')
            throw new Exception('O campo Pagamento é obrigatório');
        if($pedido->gasdopovo && $data['modalcondicaopagamento_id'] != $pedido->condicaopagamento_id)
            throw new Exception('Não é permitido mudar a condição de pagamento para pedido do Gás do Povo');
        if($data['modalcondicaopagamento_id'] != $pedido->condicaopagamento_id && $data['modalcondicaopagamento_id'] == $config->condicaopagamentogp_id)
            throw new Exception('Não é permitido mudar a condição de pagamento para Gás do Povo');

        $user = User::where('colaborador_id', $data['modalcolaborador_id'])->first();

        $data['colaborador_id'] = $data['modalcolaborador_id'];
        $data['entregadoruser_id'] = isset($user->colaborador_id) ? $user->id : null;
        $data['condicaopagamento_id'] = $data['modalcondicaopagamento_id'];
        $data['entregasetor_id'] = $data['modalsetor_id'];
        $data['pedidosituacao_id'] = $data['modalpedidosituacao_id'];
        $data['pedidomotivoatraso_id'] = $pedido->pedidomotivoatraso_id !== null ? $pedido->pedidomotivoatraso_id : $data['pedidomotivoatraso_id'];
        $data['pedido_id'] = $data['modalpedido_id'];
        $data['colaborador_id'] = $data['modalcolaborador_id'];
        $data['produtosvalegas'] = $data['produtosvalegas'];

        return emptyToNull($data);
    }

    public static function sumFilterIndex($pedidos)
    {
        $sum = (object) [
                    'concluido'             => 0,
                    'emEntrega'             => 0,
                    'pendente'              => 0,
                    'aplicativo'            => 0,
                    'cancelado'             => 0,
                    'transferir'            => 0,
                    'atrasado'              => 0,
                    'quantidadeConcluidos'  => 0,
                    'valorvenda'            => 0,
                    'totalPedidos'          => 0
        ];
        foreach ($pedidos as $pedido) {
            if ($pedido->situacao === "emEntrega")
                $sum->emEntrega++;
            else if ($pedido->situacao === "pendente")
                $sum->pendente++;
            else if ($pedido->situacao === "cancelado")
                $sum->cancelado++;
            else if ($pedido->situacao === "transferir")
                $sum->transferir++;
            else if ($pedido->situacao === "atrasado")
                $sum->atrasado++;
            else if ($pedido->situacao === "pedido-app")
                $sum->aplicativo++;
            else if ($pedido->situacao === "concluido") {
                $sum->quantidadeConcluidos += $pedido->quantidade;
                $sum->valorvenda += $pedido->valorvenda;
                $sum->concluido++;

                if (!is_null($pedido->apipedido_id)) $sum->aplicativo++;
            }
            $sum->totalPedidos++;
        }
        return $sum;
    }

    public static function generateTableMonitoramento($pedidos)
    {
        $ped = [];
        $root = url('');
        $user = \Auth::user();

        foreach ($pedidos as $pedido) {
            if ($user->can('update', \App\Pedido::class))
                $edit = '<a data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Editar Pedido" target="_blank" onclick="window.open(\'' . $root . "/pedido/" . $pedido->id . '/edit\', \'_blank\')" class="btn btn-xs btn-nw-geral" id="btnRemoverPedido"><i class="btn-nw-geral glyphicon glyphicon-pencil"></i></a>&nbsp;';
            else
                $edit = '';

            if ($user->can('especial', \App\Nfemitida::class))
                $nfce = '<button data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Gerar NFCe" href="#" type="button" onclick="parent.geraEmite(' . $pedido->id . ',' . $pedido->nfcegerou . "," . $pedido->nfce_id . ')" class="btn btn-xs btn-nw-geral" id="btnImprimirNf"><i class="btn-nw-geral glyphicon glyphicon-list-alt"></i></button>&nbsp;';
            else
                $nfce = '';

            $coma = "<button data-toggle='tooltip' data-trigger='hover' data-placement='bottom' title='Imprimir Comanda' onclick='parent.gerarComanda(\"$pedido->id\")' type='button' class='btn btn-xs btn-nw-geral' id='btnImprimirComanda'><i class='btn-nw-geral glyphicon glyphicon-print'></i></button>&nbsp;";

            $pedido->valorvenda = number_format($pedido->valorvenda, 2, '.', ',');
            $dados = (object) [];
            $dados->classTr = $pedido->situacao;
            $dados->classTd = is_null($pedido->apipedido_id) ? '' : 'pedido-app';
            $dados->specificColumn = 'codigo';
            $dados->datahoraenvioentregador = requestDataOracle($pedido->datahoraenvioentregador);
            $dados->valor_venda = $pedido->situacao !== 'concluido' ? 0 : $pedido->valorvenda;
            $dados->quantidade_itens = $pedido->quantidade;
            $dados->codigo = $pedido->id;
            $dados->datahora = requestDataOracle($pedido->datahoraprevisaoentrega);
            $dados->cliente = $pedido->nome;
            $dados->setorcolaborador = $pedido->setor . ' - ' . $pedido->colaborador;
            $dados->status = $pedido->situacao_descricao;
            $dados->empresa = $pedido->nome_informal;
            $dados->endereco = $pedido->rua . ' Nº ' . $pedido->entreganumero . ', ' . $pedido->bairro . ', ' . $pedido->cidade;
            $dados->pagamento = $pedido->condicaopagamento;
            $dados->telefone = $pedido->entregatelefone !== null ? $pedido->entregatelefone : '';
            $dados->valor = requestNumeroDecimalOracle(str_replace(',', '', $pedido->valorvenda));
            $dados->entregataxa = requestNumeroDecimalOracle($pedido->entregataxa);
            $dados->urgente = $pedido->entregaurgente == 1 ? 'Sim' : 'Não';
            $dados->operacoes = $edit . $nfce . $coma;
            array_push($ped, $dados);
        }
        return collect($ped);
    }

    public static function getDescriptionOfModel($modelName)
    {
        $arr = [
            'Pedidosituacao'    => 'a Situação do Pedido',
            'Condicaopagamento' => "a Condiçao de Pagamento",
            'Pedido'            => 'o Pedido',
            'Produto'           => 'o Produto',
            'Nfemitida'         => 'a Nota Fiscal',
            'Cliente'           => 'o Cliente'
        ];
        $description = collect($arr)->get($modelName);

        return $description ? $description : 'o ' . $modelName;
    }

    public static function pedeValegas($sit)
    {
        return $sit->fechadoconcluido || $sit->pendente;
    }

    public static function reindexArray($original, $columns)
    {
        $newArr = [];

        foreach ($original as $colab) {
            $newArr[] = (object) collect($colab)->only($columns)->toArray();
        }

        return $newArr;
    }

    public static function checkForConvenio($pedido)
    {
        $cliente = $pedido->cliente->load("convenioempresa.clienteConvenio", "convenioempresa.produtoconvenio");
        $cliente_id = $cliente->id;

        $empresa = $cliente->convenioempresa;
        $convenio = $empresa && $empresa->clienteConvenio ? $empresa->clienteConvenio : null;

        // checa se há convenio e as compras que foram feitas com o convenio
        if ($cliente->convenio_id && $convenio && $empresa->convenioativo == "1" && $cliente->convenio == "1") return true;

        $convenioDisponivel = false;
        $fechamento = getProximoVencimento($convenio->diafechamento);
        $fechamentoAnterior = Carbon::parse($fechamento)->subMonth()->toDateTimeString();

        $totalcomprasConvenio = intVal(DB::table('pedidos p')->join('pedidosituacaos s', 's.id', 'p.pedidosituacao_id')
            ->join('condicaopagamentos cond', function ($join) {
                $join->on('cond.id', 'p.condicaopagamento_id')->where('tipo', '4');
            })
            ->join('pedidoitems it', function ($join) {
                $join->on('it.pedido_id', 'p.id')->whereRaw("fechadocancelado <> 1 AND entregacancelada <> 1");
            })
            ->where([
                ['cliente_id', $cliente_id],
                ['p.id', '!=', null]
            ])->whereBetween('datahoraprevisaoentrega', [$fechamentoAnterior, $fechamento])
            ->selectRaw("sum(quantidade) as quantidade")->get()->first()->quantidade);

        $qtdeProd = 0;
        $prodNotFound = false;
        foreach ($pedido->pedidoitem as $item) {
            $qtdeProd += $item->quantidade;

            $prod = $empresa->produtoconvenio->first(function ($prod) use ($item) {
                return $prod->produto_id == $item->produto_id;
            });

            if (is_null($prod)) {
                $prodNotFound = true;
            }
        }

        if ($prodNotFound) return false;

        $totalcomprasConvenio += $qtdeProd;

        if ($cliente->convenio && $totalcomprasConvenio <= $convenio->limitecompra) {
            $convenioDisponivel = true;
        } else {
            if ($totalcomprasConvenio <= $cliente->conveniolimite) {
                $convenioDisponivel = true;
            }
        }

        if (!$convenioDisponivel) return false;

        return true;
    }
}
