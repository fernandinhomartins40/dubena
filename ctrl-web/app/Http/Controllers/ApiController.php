<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Monitoramentochamadas;
use App\Empresa;
use App\EmpresasGrupo;
use App\Android;
use App\Processors\androidProcessor;
use App\User;
use App\Pedidomotivoatraso;
use App\Pedidosituacao;
use App\Pedido;
use App\Empresaconfig;
use App\Veiculo;
use App\Setor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientErrorResponseException;
use App\Setorcolaboradores;
use App\Services\CarbonCustom as Carbon;
use App\Valegas;
use App\Androidmensagem;
use App\Http\Resources\ApiResources;
use App\Http\Resources\Classes\AppConfig;
use DB;

class ApiController extends Controller
{

    public function setAndroidRegistration(Request $request)
    {
        $data = $request->all();
        $empresa = Empresa::find($data['revenda_id']);
        if ($empresa == null) {
            return 'Erro: Revenda não encontrada.';
        }
        $usuario = User::where('email', $data['usuario'])->first();
        $android = new Android();
        $android->grupo_id = $empresa->grupo_id;
        $android->empresa_id = $empresa->id;
        $android->descricao = "Descrição padrão";
        if (isset($data['descricao']) && $data['descricao'] != '') {
            $android->descricao = $data['descricao'];
        }
        $android->serie = "serie";
        $android->androidid = $data['id'];
        $android->urlservidor = $data['url'];
        $android->registrationid = $data['token'];
        $android->ativo = true;
        if ($usuario != null) {
            $android->user_id = $usuario->id;
            if ($usuario->colaborador_id != null) {
                $android->colaborador_id = $usuario->colaborador_id;
                $setores = Setorcolaboradores::where('colaborador_id', $usuario->colaborador_id);
                if ($setores != null) {
                    $android->setor_id = $setores->first()->setor_id;
                }
            }
        }
        $processor = new androidProcessor($data['id']);
        if ($processor->registrarAndroid($android))
            return "OK";
        else
            return implode(' ', $processor->getErrors());
    }

    public function getUsuarios(Request $request)
    {
        $data = $request->all();
        $empresa = Empresa::find($data['revenda_id']);
        $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
        if ($android != null && $android->setor != null) {
            if ($android->setor->setorcolaboradores != null) {
                $colaboradors = $android->setor->setorcolaboradores->pluck('colaborador_id');
                $users = User::whereIn('colaborador_id', $colaboradors)
                    ->where('usaandroid', true)
                    ->where('ativo', true)
                    ->select('email', 'id', 'name', 'password')
                    ->get();
                return json_encode(["status" => 'OK', "dados" => $users]);
            }
        }
        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getEmpresas(Request $request)
    {
        $data = $request->all();
        $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
        if ($android != null && $android->empresa != null) {
            $empresa = $android->empresa;
            if ($android->empresa->empresaconfigs != null) {
                $config = $android->empresa->empresaconfigs->first();
                $registro = array();
                $registro["codigo"] = $empresa->id;
                $registro["razao_social"] = $empresa->razao_social;
                $registro["endereco"] = $empresa->rua->descricao . ", " . $empresa->numero . " - " . $empresa->bairro->descricao;
                $registro["nome_fantasia"] = $empresa->nome_fantasia;
                $registro["cnpj"] = $empresa->cnpj;
                $registro["inscricao_estadual"] = $empresa->inscricao_estadual;
                $registro["cidade"] = $empresa->cidade->descricao;
                $registro["uf"] = $empresa->uf;
                $registro["telefone"] = $empresa->telefone1;
                $registro["valida_gb"] = $config->validagasbolso;
                $registro["valida_coordenadas"] = $config->validacordenadasentrega;
                $registro["valida_atraso"] = $config->validaatraso;
                $registro["tempo_entrega"] = $config->tempoentrega;
                $registro["tempo_entrega_urgente"] = $config->tempourgente;
                $registro["valida_pix"] = $config->validapixentrega;
                return json_encode(["status" => 'OK', "dados" => [$registro]]);
            }
        }
        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getPedidosMotivosAtrasos(Request $request)
    {
        $data = $request->all();
        $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
        if ($android != null && $android->empresa != null) {
            $motivos = Pedidomotivoatraso::where('grupo_id', $android->grupo_id)
                ->where('ativo', true)
                ->select('id', 'descricao')->get();
            return json_encode(["status" => 'OK', "dados" => $motivos]);
        }
        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getPedidosSituacoes(Request $request)
    {
        $data = $request->all();
        $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
        if ($android != null && $android->empresa != null) {
            $situacoes = Pedidosituacao::where('grupo_id', $android->grupo_id)
                ->where('ativo', true)
                ->where('androidusa', true)
                ->select(
                    'id',
                    'descricao',
                    'entregafinalizada',
                    'entregacancelada',
                    'entregapendente',
                    'entregatranferida',
                    'ementrega',
                    'valegas',
                    'pedidorecebidomovel',
                    'pedidolidomovel',
                    'solicitacartaoautorizacao'
                )->get();
            return json_encode(["status" => 'OK', "dados" => $situacoes]);
        }
        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getVeiculos(Request $request)
    {
        $data = $request->all();
        $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
        if ($android != null && $android->empresa != null) {
            if ($android->setor != null) {
                $veiculos_id = $android->setor->veiculos->pluck('id')->toArray();
                $veiculos = Veiculo::whereIn('id', $veiculos_id)
                    ->select('id', 'placa', 'descricao', 'colaborador_id')->get();
                foreach ($veiculos as $v) {
                    $v->ativo = ($android->colaborador_id == $v->colaborador_id ? 1 : 0);
                }

                return json_encode(["status" => 'OK', "dados" => $veiculos]);
            }
        }
        return json_encode(["status" => 'NOK', "dados" => []]);
    }


    public function gravarTelefone(Request $request)
    {
        $data = $request->all();
        try {
            $empresa = Empresa::find($data['empresa_id']);
            $data['grupo_id'] = $empresa->grupo_id;
            $data['telefone'] = str_replace(' ', '', $data['telefone']);
            if (substr($data['telefone'], 0, 1) === '0')
                $data['telefone'] = substr($data['telefone'], 1, strlen($data['telefone']));
            if (strlen($data['telefone']) === 11) {
                $tel = '(';
                for ($i = 0; $i < strlen($data['telefone']); $i++) {
                    if ($i == 1)
                        $tel .= substr($data['telefone'], $i, 1) . ") ";
                    else if ($i === 6)
                        $tel .= substr($data['telefone'], $i, 1) . "-";
                    else
                        $tel .= substr($data['telefone'], $i, 1);
                }
            } else {
                $tel = '(';
                for ($i = 0; $i < strlen($data['telefone']); $i++) {
                    if ($i == 1)
                        $tel .= substr($data['telefone'], $i, 1) . ") ";
                    else if ($i === 5)
                        $tel .= substr($data['telefone'], $i, 1) . "-";
                    else
                        $tel .= substr($data['telefone'], $i, 1);
                }
            }
            $data['telefone'] = $tel;
            $mon = Monitoramentochamadas::create($data);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return "OK|";
    }

    public function testarToken(Request $request)
    {
        $data = $request->all();
        return $data['telefone'] == '123456' ? "OK" : 'NOT';
    }

    public function sendNotificacaoMovelTeste(Request $request = null, $android_id)
    {
        if ($request !== null) {
            $data = $request->all();
        } else {
            $data = ['android_id' => $android_id];
        }

        $androids = Android::where('id', $data['android_id'])->pluck('registrationid');
        if ($androids) {
            if (count($androids) == 0) {
                return;
            }

            $message = [
                "message" => "Teste de notificação.",
                "hasSound" => true,
                "android_channel_id" => "my_channel_01"
            ];

            $fields = [
                'registration_ids' => json_encode($androids),
                'data'             => $message,
                'priority'         => 'high'
            ];

            try {
                $response = $this->sendNotifications($fields);

                return $response;
            } catch (\Exception $e) {
                info("Error ao enviar notificação: " . $e->getMessage() . " Code :" . $e->getCode() . " Line " . $e->getLine() . " on " . $e->getFile() . PHP_EOL);

                return $e->getMessage();
            }
        }
    }

    public function sendPedidoPendente(Request $request = null, $pedido_id = null)
    {
        if ($request !== null) {
            $data = $request->all();
        } else {
            $data = ['pedido_id' => $pedido_id];
        }

        $pedido = Pedido::find($data['pedido_id']);

        if ($pedido != null) {
            if ($pedido->pedidoSituacao->entregapendente && $pedido->pedidoSituacao->androidusa) {
                $config = Empresaconfig::where('empresa_id', $pedido->empresa_id)->first();

                if ($config->androidutiliza) {
                    $androids = Android::where('colaborador_id', $pedido->colaborador_id)->where('ativo', true)->pluck('registrationid');

                    if ($config->androidenviatodos) {
                        $androids = Android::where('setor_id', $pedido->entregasetor_id)->pluck('registrationid');
                    }

                    if (count($androids) == 0) {
                        return;
                    }

                    $message = [
                        "message" => "Pedido " . $data["pedido_id"] . " enviado para entrega.",
                        "hasSound" => true,
                        "android_channel_id" => "my_channel_01"
                    ];

                    $fields = [
                        'registration_ids' => json_encode($androids),
                        'data'             => $message,
                        'priority'         => 'high'
                    ];

                    try {
                        $response = $this->sendNotifications($fields);

                        return $response;
                    } catch (\Exception $e) {
                        info("here " . $e->getMessage());
                        return $e->getMessage();
                    }

                    // try {
                    //     $gClient = new Client(['base_uri' => 'https://fcm.googleapis.com']);
                    //     $res = $gClient->post(
                    //         'fcm/send',
                    //         array(
                    //             'headers' => $headers,
                    //             'json'    => $fields
                    //         )
                    //     );
                    //     return json_encode($res->getBody()->getContents());
                    // } catch (BadResponseException $e) {
                    //     $response = $e->getResponse();
                    //     return $response->getBody()->getContents();
                    // }
                }
            }
        }
    }

    public function getPedidosPendentes(Request $request)
    {
        $data = $request->all();
        $android = null;

        if (isset($data['androidid'])) {
            $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
        }

        if ($android != null && $android->empresa != null) {
            $empresa = $android->empresa;
            if ($android->empresa->empresaconfigs != null) {
                $config = $android->empresa->empresaconfigs->first();
                if ($config->androidutiliza) {
                    $situacoes = array();
                    $situacoes = Pedidosituacao::where('entregapendente', true)->pluck('id');
                    $condicoes = array();
                    array_push($condicoes, ['empresa_id', $android->empresa_id]);
                    array_push($condicoes, ['entregasetor_id', $android->setor_id]);
                    if (!$config->androidenviatodos) {
                        array_push($condicoes, ['colaborador_id', $android->colaborador_id]);
                    }

                    $pedidos = Pedido::with(
                        'cliente',
                        'pedidosituacao',
                        'condicaopagamento',
                        'setor',
                        'empresa',
                        'pedidoitem',
                        'colaborador',
                        'entregarua',
                        'entregabairro',
                        'entregacidade'
                    )
                        ->where($condicoes)
                        ->whereIn('pedidosituacao_id', $situacoes)
                        ->get();
                    $result = array();
                    foreach ($pedidos as $pedido) {
                        $ped = array();
                        $ped["id"] = $pedido["id"];
                        $ped["razao_social"] = $pedido->cliente->nome;
                        $ped["datahora"] = Carbon::parse(is_null($pedido["datahoraenvioentregador"]) ? $pedido["datahoraprevisaoentrega"] : $pedido["datahoraenvioentregador"])->format('Y-m-d H:i:s');
                        $ped["valorvenda"] = $pedido["valorvenda"];
                        $ped["entreganumero"] = $pedido["entreganumero"];
                        $ped["entregacomplemento"] = $pedido["entregacomplemento"] == null ? '' : $pedido["entregacomplemento"];
                        $ped["observacao"] = $pedido["observacao"] == null ? '' : $pedido["observacao"];
                        $ped["entregapontoreferencia"] = $pedido["entregapontoreferencia"] == null ? '' : $pedido["entregapontoreferencia"];
                        $ped["entregarua"] = $pedido->entregarua->descricao;
                        $ped["condicao"] = $pedido->condicaopagamento->descricao;
                        $ped["entregabairro"] = $pedido->entregabairro->descricao;
                        $ped["pedidosituacao_id"] = $pedido["pedidosituacao_id"];
                        $ped["pedidosituacao_descricao"] = $pedido->pedidosituacao->descricao;
                        $ped["entregacidade"] = $pedido->entregacidade->descricao;
                        $ped["entregauf"] = $pedido->entregacidade->uf;
                        $ped["urgente"] = $pedido->entregaurgente ? 'S' : 'N';
                        $ped["motivo_atraso"] = $pedido->pedidomotivoatraso_id == null ? '-1' : $pedido->pedidomotivoatraso_id;
                        $ped["convenio"] = $pedido->cliente->convenioempresa == null ? '' : $pedido->cliente->convenioempresa->nome;
                        $ped["cartao"] = $pedido->condicaopagamento->tipo == 2 || $pedido->condicaopagamento->tipo == 3 ? '1' : '0';
                        $ped["app"] = $pedido->apipedido_id == null ? 'N' : 'S';
                        $ped["gasdopovo"] = $pedido->gasdopovo;
                        $itens = array();
                        foreach ($pedido->pedidoitem as $item) {
                            $it = array();
                            $it["pedido_id"] = $item->pedido_id;
                            $it["id"] = $item->id;
                            $it["produto"] = $item->produto->descricao;
                            $it["quantidade"] = $item->quantidade;
                            $it["precovendaunitario"] = $item->precovendaunitario;
                            $it["precovendatotal"] = $item->precovendatotal;
                            $it["unidademedida"] = $item->unidademedida == null ? '' : $item->unidademedida->sigla;
                            array_push($itens, $it);
                        }
                        $ped["itens"] = $itens;
                        array_push($result, $ped);
                    }

                    return json_encode(["status" => 'OK', "dados" => $result]);
                }
                return json_encode(["status" => 'NOK', "dados" => []]);
            }
            return json_encode(["status" => 'NOK', "dados" => []]);
        }
        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function setPedidoSituacao(Request $request)
    {
        try {
            $data = $request->all();
            $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
            if ($android != null && $android->empresa != null) {
                $pedido_id = $data["pedido_id"];
                $pedido = Pedido::find($pedido_id);
                if (is_null($pedido))
                    return json_encode(["status" => 'NOK', "dados" => "Erro ao alterar status. Pedido não encontrado."]);
                if ($pedido->pedidosituacao->fechadoconcluido || $pedido->pedidosituacao->fechadocancelado)
                    return json_encode(["status" => 'NOK', "dados" => "Erro ao alterar status. Pedidos cancelados ou já fechados não podem ser alterados."]);
                $pedidosituacao_id = $data["pedidosituacao_id"];
                $pedidosituacao = Pedidosituacao::find($pedidosituacao_id);


                $tz = getTimezone(Empresa::find($pedido->empresa_id)->uf);
                if ($pedidosituacao->pedidorecebidomovel)
                    $pedido->update(['datahoraenvioentregador' => Carbon::now($tz)->toDateTimeString()]);
                if ($pedidosituacao->entregafinalizada || $pedidosituacao->fechadoconcluido)
                    $pedido->update(['entregadatahora' => Carbon::now($tz)->toDateTimeString()]);
                $latitude = $data["latitude"];
                $longitude = $data["longitude"];
                $pedidomotivoatraso_id = $data["pedidomotivoatraso_id"];
                $cartao_autorizacao = isset($data["cartao_autorizacao"]) ? $data["cartao_autorizacao"] : "";
                $controller = new PedidoController();
                $controller->updateStatus(
                    $pedido_id,
                    $pedidosituacao_id,
                    $pedido,
                    ($pedidomotivoatraso_id == -1 ? null : $pedidomotivoatraso_id),
                    ($latitude == "" ? null : $latitude),
                    ($longitude == "" ? null : $longitude),
                    $cartao_autorizacao
                );
                return json_encode(["status" => 'OK', "dados" => "OK"]);
            }
            return json_encode(["status" => 'NOK', "dados" => "Erro ao alterar status. Verifique o cadastro do android na revenda."]);
        } catch (\Exception $e) {
            return json_encode(["status" => 'NOK', "dados" => getErrorsException($e)]);
        }
    }

    public function getValeGas(Request $request)
    {
        $data = $request->all();
        $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
        if ($android != null && $android->empresa != null) {
            $valegas_barras = $data["valegas"];
            $valegas = Valegas::where('codigo', $valegas_barras)->get();
            if (count($valegas) == 0) {
                return json_encode(["status" => 'NOK', "dados" => "Vale Gás não encontrado no banco de dados."]);
            }
            foreach ($valegas as $vg) {
                if ($vg->valeGasSituacao->descricao == "Cancelado") {
                    return json_encode(["status" => 'NOK', "dados" => "Vale Gás cancelado."]);
                } elseif ($vg->valeGasSituacao->descricao == "Baixado") {
                    return json_encode(["status" => 'NOK', "dados" => "Vale Gás já utilizado anteriormente."]);
                }
            }
            return json_encode(["status" => 'OK', "dados" => "OK"]);
        }
        return json_encode(["status" => 'NOK', "dados" => "NOK"]);
    }

    public function setAndroidMensagem(Request $request)
    {
        try {
            $data = $request->all();
            $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
            if ($android != null && $android->empresa != null) {
                $empresa = $android->empresa;
                $msg = $data["msg"];
                $androidmsg = new Androidmensagem();
                $androidmsg->empresa_id = $android->empresa->id;
                $androidmsg->grupo_id = $android->empresa->grupo_id;
                $androidmsg->user_id = $android->user_id;
                $androidmsg->descricao = $msg;
                $androidmsg->android_id = $android->id;
                $androidmsg->tipo = 1;
                $androidmsg->situacao = 'E';
                $androidmsg->save();
                return json_encode(["status" => 'OK', "dados" => "OK"]);
            } else {
                return json_encode(["status" => 'NOK', "dados" => "Dados do celular não encontrados"]);
            }
        } catch (\Exception $e) {
            return json_encode(["status" => 'NOK', "dados" => $e->getMessage()]);
        }
    }

    public function setVeiculoAtivo(Request $request)
    {
        try {
            $data = $request->all();
            $veiculo_id = $data["veiculo_id"];
            $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
            if ($android != null && $android->empresa != null) {
                $veiculos = Veiculo::where('colaborador_id', $android->colaborador_id);
                $veiculos->update(array('colaborador_id' => null));
                $veiculo = Veiculo::findOrFail($veiculo_id);
                $veiculo->colaborador_id = $android->colaborador_id;
                $veiculo->save();
                return json_encode(["status" => 'OK', "dados" => "OK"]);
            } else {
                return json_encode(["status" => 'NOK', "dados" => "Dados do celular não encontrados"]);
            }
        } catch (\Exception $e) {
            return json_encode(["status" => 'NOK', "dados" => $e->getMessage()]);
        }
    }



    public function getRastreamentoGrupos(Request $request)
    {
        $data = $request->all();
        $usuario = User::where('email', $data['user'])->first();
        $empresas = array();
        if (isset($data['empresa_id'])) {
            $empresas = Empresa::where('id', $data['empresa_id'])->get();
        } else {
            $empresas = $usuario->empresas;
        }
        if ($usuario != null) {
            $registros = array();
            foreach ($empresas as $empresa) {
                $grupo = EmpresasGrupo::find($empresa->grupo_id);
                if ($grupo->ativo) {
                    $registro = array();
                    $registro["id"] = $grupo->id;
                    $registro["descricao"] = $grupo->descricao;
                    array_push($registros, $registro);
                }
            }
            //$grupos = EmpresasGrupo::where('ativo', true)->first();

            return json_encode(["status" => 'OK', "dados" => $registros]);
        }

        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getRastreamentoEmpresas(Request $request)
    {
        $data = $request->all();
        $usuario = User::where('email', $data['user'])->first();
        $empresas = array();
        if (isset($data['empresa_id'])) {
            $empresas = Empresa::where('id', $data['empresa_id'])->get();
        } else {

            $empresas = $usuario->empresas;
        }


        if ($usuario != null) {
            $registros = array();
            foreach ($empresas as $empresa) {
                if ($empresa->ativo) {
                    $registro = array();
                    $registro["id"] = $empresa->id;
                    $registro["grupo_id"] = $empresa->grupo_id;
                    $registro["razao_social"] = $empresa->razao_social;
                    $registro["nome_fantasia"] = $empresa->nome_fantasia;
                    $registro["cnpj"] = $empresa->cnpj;
                    $registro["nome_informal"] = $empresa->nome_informal;
                    $registro["inscricao_estadual"] = $empresa->inscricao_estadual;
                    $registro["latitude"] = $empresa->latitude;
                    $registro["longitude"] = $empresa->longitude;
                    $config = Empresaconfig::where('empresa_id', $empresa->id)->first();
                    $registro["keygooglemaps"] = $config == null ? '' : $config->keygooglemaps;


                    array_push($registros, $registro);
                }
            }
            //$grupos = EmpresasGrupo::where('ativo', true)->first();

            return json_encode(["status" => 'OK', "dados" => $registros]);
        }

        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getRastreamentoVeiculos(Request $request)
    {
        $data = $request->all();
        $usuario = User::where('email', $data['user'])->first();
        $empresas = array();
        if (isset($data['empresa_id'])) {
            $empresas = Empresa::where('id', $data['empresa_id'])->get();
        } else {
            $empresas = $usuario->empresas;
        }
        if ($usuario != null) {
            $registros = array();
            foreach ($empresas as $empresa) {
                if ($empresa->ativo) {
                    $veiculos = Veiculo::where('empresa_id', $empresa->id)->get();
                    foreach ($veiculos as $veiculo) {
                        if ($veiculo->veiculotipo != null && $veiculo->veiculotipo->tiporastreamento != null) {
                            $registro = array();
                            $registro["id"] = $veiculo->id;
                            $registro["grupo_id"] = $veiculo->grupo_id;
                            $registro["empresa_id"] = $veiculo->empresa_id;
                            $registro["veiculotipo_id"] = $veiculo->veiculotipo->tiporastreamento;
                            $registro["descricao"] = $veiculo->descricao;
                            $registro["placa"] = $veiculo->placa;
                            $registro["ativo"] = $veiculo->ativo;
                            if (!$veiculo->usarastreamento) {
                                $registro["ativo"] = "0";
                            }
                            $registro["motorista"] = $veiculo->colaborador == null ? '' : $veiculo->colaborador->nome;
                            array_push($registros, $registro);
                        }
                    }
                }
            }
            //$grupos = EmpresasGrupo::where('ativo', true)->first();

            return json_encode(["status" => 'OK', "dados" => $registros]);
        }

        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getRastreamentoSetors(Request $request)
    {
        $data = $request->all();
        $usuario = User::where('email', $data['user'])->first();
        $empresas = array();
        if (isset($data['empresa_id'])) {
            $empresas = Empresa::where('id', $data['empresa_id'])->get();
        } else {
            $empresas = $usuario->empresas;
        }
        if ($usuario != null) {
            $registros = array();
            foreach ($empresas as $empresa) {
                if ($empresa->ativo) {
                    $setors = Setor::where('empresa_id', $empresa->id)->get();
                    foreach ($setors as $setor) {
                        //if($setor->ativo && $setor->usarastreamento){
                        $registro = array();
                        //dd($setor->latitude);
                        $registro["id"] = $setor->id;
                        $registro["grupo_id"] = $setor->grupo_id;
                        $registro["empresa_id"] = $setor->empresa_id;
                        $registro["descricao"] = $setor->descricao;
                        $registro["latitude"] = $setor->latitude;
                        $registro["longitude"] = $setor->longitude;
                        $registro["rua"] = $setor->rua->descricao;
                        $registro["numero"] = $setor->numero;
                        $registro["cep"] = $setor->cep;
                        $registro["bairro"] = $setor->bairro->descricao;
                        $registro["cidade"] = $setor->cidade->descricao;
                        $registro["uf"] = $setor->uf;

                        $registro["ativo"] = $setor->ativo;
                        if (!$setor->usarastreamento) {
                            $registro["ativo"] = false;
                        }
                        array_push($registros, $registro);
                        //}
                    }
                }
            }
            //$grupos = EmpresasGrupo::where('ativo', true)->first();

            return json_encode(["status" => 'OK', "dados" => $registros]);
        }

        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getRastreamentoUsers(Request $request)
    {
        $data = $request->all();
        $usuario = User::where('email', $data['user'])->first();
        $empresas = array();
        if (isset($data['empresa_id'])) {
            $empresas = Empresa::where('id', $data['empresa_id'])->get();
        } else {
            $empresas = $usuario->empresas;
        }
        if ($usuario != null) {
            $registros = array();
            foreach ($empresas as $empresa) {
                if ($empresa->ativo) {
                    $users = $empresa->users;

                    foreach ($users as $user) {

                        if ($user->ativo && $user->usarastreamento) {
                            $registro = array();
                            $oauthClient = \App\Oauthclient::where('user_id', $user->id)->get()->first();
                            //dd($setor->latitude);
                            $registro["id"] = $user->id;
                            $registro["grupo_id"] = $empresa->grupo_id;
                            $registro["empresa_id"] = $user->empresa_id;
                            $registro["email"] = $user->email;
                            $registro["name"] = $user->name;
                            $registro["password"] = $user->password;
                            $registro["client_id"] = $oauthClient->id;
                            $registro["ativo"] = $user->ativo;
                            array_push($registros, $registro);
                        }
                    }
                }
            }
            //$grupos = EmpresasGrupo::where('ativo', true)->first();

            return json_encode(["status" => 'OK', "dados" => $registros]);
        }

        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    public function getRastreamentoPedidos(Request $request)
    {
        $data = $request->all();
        $usuario = User::where('email', $data['user'])->first();

        if (isset($data['empresa_id'])) {
            $emp = Empresa::where('id', $data['empresa_id'])->get();
        } else {
            $emp = $usuario->empresas;
        }

        $firstEmp = $emp->first();
        if (!isset($firstEmp->uf))
            return json_encode(["status" => 'NOK', "dados" => []]);

        $tz = getTimezone($firstEmp->uf);
        $empresas = $emp->pluck('id');

        $situacoes = Pedidosituacao::where('entregapendente', true)->pluck('id');

        $pedidos = Pedido::with(
            'cliente',
            'pedidosituacao',
            'condicaopagamento',
            'setor',
            'empresa',
            'pedidoitem',
            'colaborador',
            'entregarua',
            'entregabairro',
            'entregacidade'
        )
            ->whereIn('pedidosituacao_id', $situacoes)
            ->whereIn('empresa_id', $empresas)
            ->get();
        $result = array();
        foreach ($pedidos as $pedido) {
            $ped = array();
            $ped["codigo_pedido"] = $pedido["id"];
            $ped["data_hora_entrega"] = Carbon::parse($pedido["entregadatahora"])->format('d-m-Y H:i:s');
            $ped["data_hora_envio"] = Carbon::parse($pedido["datahora"])->format('d-m-Y H:i:s');
            $ped["dif"] = Carbon::parse(is_null($pedido["datahoraenvioentregador"]) ? $pedido["datahoraprevisaoentrega"] : $pedido["datahoraenvioentregador"])->diffInMinutes(Carbon::now($tz));
            $ped["desc_rua"] = $pedido->entregarua->descricao;
            $ped["razao_social"] = $pedido->cliente->nome;
            $ped["numero_entrega"] = $pedido["entreganumero"];
            $ped["bairro_entrega"] = $pedido->entregabairro->descricao;
            $ped["desc_cidade"] = $pedido->entregacidade->descricao . "/" . $pedido->entregacidade->uf;
            $ped["entrega_urgente"] = $pedido->entregaurgente ? 'S' : 'N';
            $ped["desc_setores"] = $pedido->setor->descricao;
            $ped["nome_colaborador"] = $pedido->colaborador->nome;
            $ped["latitude"] = $pedido->latitude != null ? $pedido->latitude : ($pedido->cliente->latitude != null ? $pedido->cliente->latitude : ($pedido->empresa->latitude != null ? $pedido->empresa->latitude : 0));
            $ped["longitude"] = $pedido->longitude != null ? $pedido->longitude : ($pedido->cliente->longitude != null ? $pedido->cliente->longitude : ($pedido->empresa->longitude != null ? $pedido->empresa->longitude : 0));
            $ped["endereco"] = $pedido->entregarua->descricao . ', ' . $pedido["entreganumero"] . ' - ' . $pedido->entregabairro->descricao . ' - ' . $pedido->entregacidade->descricao . " - " . $pedido->entregacidade->uf;
            $ped["endereco1"] = $pedido->entregarua->descricao . ', ' . $pedido["entreganumero"] . ' - ' . $pedido->entregabairro->descricao;
            $ped["codigo_clientes"] = $pedido->cliente_id;
            $config = Empresaconfig::where('empresa_id', $pedido->empresa_id)->first();
            $ped["tempoentrega"] = $config->tempoentrega;
            $ped["tempourgente"] = $config->tempourgente;
            //$ped["latitude"] = $pedido->latitude;
            //$ped["longitude"] = $pedido->longitude;
            array_push($result, $ped);
        }
        return json_encode(["status" => 'OK', "dados" => $result]);
    }
    public function getPedidosReport(Request $request)
    {
        $data = $request->all();
        $empresa = Empresa::find($data['revenda_id']);
        $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
        $dataInicial = Carbon::parse(insertDataOracle($data["data_inicial"]))->toDateString() . " 00:00:00";
        $dataFinal = Carbon::parse(insertDataOracle($data["data_final"]))->toDateString() . " 23:59:59";
        //$dataFinal = Carbon::parse(insertDataOracle($data["data_final"]." 23:59:59"));

        if ($android != null && $android->setor != null) {
            $pedidos = Pedido::join('pedidosituacaos sit', 'sit.id', 'pedidos.pedidosituacao_id')
                ->join('ruas r', 'r.id', 'pedidos.entregarua_id')
                ->join('bairros b', 'b.id', 'pedidos.entregabairro_id')
                ->join('condicaopagamentos c', 'c.id', 'pedidos.condicaopagamento_id')
                ->where('pedidos.empresa_id', $android->empresa_id)
                ->where('pedidos.entregasetor_id', $android->setor_id)
                ->whereBetween('pedidos.datahoraprevisaoentrega', [$dataInicial, $dataFinal])
                ->where(function ($query) {
                    $query->where('sit.entregafinalizada', '=', 1)
                        ->orWhere('sit.fechadoconcluido', '=', 1);
                })
                ->orderBy('pedidos.id')
                ->select(
                    'pedidos.id',
                    'sit.descricao as sitdescricao',
                    'pedidos.valorvenda',
                    'r.descricao as ruadescricao',
                    'b.descricao as bairro',
                    'pedidos.entreganumero',
                    'c.descricao as condicao',
                    DB::raw("(SELECT SUM(i.QUANTIDADE) FROM pedidoitems i WHERE i.pedido_id = pedidos.id) AS qtde")
                )
                ->get();
            return json_encode(["status" => 'OK', "dados" => $pedidos]);
        }
        return json_encode(["status" => 'NOK', "dados" => []]);
    }

    private function sendNotifications($data)
    {
        $config = new AppConfig();

        $config->setConfig();

        $url = $config->api_url;

        $url = str_finish($url, '/') . "api/sendNotificationDelivery";

        $api = new ApiResources($url);

        $api->setAuthorizationCode($config->api_authorization);

        $response = $api->post($data, $url);

        return $response;
    }
}
