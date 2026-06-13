<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Tabela de Acompanhamento de pedidos</title>
        <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

        <link href="{{URL::to('bootstrap/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css">
        <link type="text/css" href="{{URL::to('bootstrap/css/bootstrap-responsive.css')}}" rel="stylesheet">
        <link href="{{asset('css/font-awesome.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{URL::to('dist/css/AdminLTE.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{URL::to('css/form.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{URL::to('css/novolayout.css')}}" rel="stylesheet" type="text/css"/>
        <link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('css/lib/great-table.css')}}" rel="stylesheet" type="text/css" />

        <script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
        <script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}"></script>
        <script src="{{URL::to('js/shortcut.js')}}"></script>
        <script src="{{asset('js/lib/great-table.js')}}"></script>
        <script src="{{URL::to('plugins/qz-tray/js/dependencies/rsvp-3.1.0.min.js')}}"></script>
        <script src="{{URL::to('plugins/qz-tray/js/dependencies/sha-256.min.js')}}"></script>
        <script src="{{URL::to('plugins/qz-tray/js/qz-tray.js')}}"></script>
        <script src="{{asset('js\thermalPrint.js')}}"></script>
    </head>
    <body>
        <div class="panel panel-default">
            <table class="table hidden" id="tblAcompanhamentoPedidos">
                <thead>
                    <tr>
                        <th field-id="checkbox" data-none="true"><input type="checkbox" id="checkbox-all"></th>
                        <th field-id="classTr" hidden="true">classTr</th>
                        <th field-id="valor_venda" hidden="true">valor_venda</th>
                        <th field-id="quantidade_itens" hidden="true">quantidade_itens</th>
                        <th field-id="codigo" sort-by="true">Cód.</th>
                        <th field-id="datahora" sort-by="true">Data e Hora</th>
                        <th field-id="datahoraenvioentregador" sort-by="true">Data Envio Entregador</th>
                        <th field-id="cliente" sort-by="true">Cliente</th>
                        <th field-id="setorcolaborador" sort-by="true">Setor - Colaborador</th>
                        <th field-id="status" sort-by="true">Status</th>
                        <th field-id="empresa" sort-by="true">Empresa</th>
                        <th field-id="endereco" sort-by="true">Endereço</th>
                        <th field-id="pagamento" sort-by="true">Pagamento</th>
                        <th field-id="telefone" sort-by="true">Telefone</th>
                        <th field-id="valor" sort-by="true">Valor</th>
                        <th field-id="entregataxa" sort-by="true">Taxa Entrega</th>
                        <th field-id="urgente" sort-by="true">Urgente</th>
                        <th field-id="operacoes" data-none="true">Operações</th>
                    </tr>
                </thead>
            </table>
            <div id="totais">
                <div class="col-md-12 margTop_10">
                    <div class="col-md-4 p-r-0">
                        <div class="col-sm-3 p-r-0">
                            {{-- padding-left: 5px !important Square: width:12px;height:12px; --}}
                            <span class="info-box-icon concluido square-12"></span>
                            <span class="info-box-text fontSize_10 p-l-5"> Concluído
                                <br />
                                <div>
                                    <span id="divQdePedidosConcluidos">{{$sum->concluido}}</span>
                                    pedido(s)
                                </div>
                            </span>
                        </div>
                        <div class="col-sm-3 p-r-0">
                            <span class="info-box-icon emEntrega square-12"></span>
                            <span class="info-box-text fontSize_10 p-l-5"> Em Entrega
                                <br />
                                <div>
                                    <span id="divQdePedidosEmEntrega">{{$sum->emEntrega}}</span>
                                    pedido(s)
                                </div>
                            </span>
                        </div>
                        <div class="col-sm-3 p-r-0">
                            <span class="info-box-icon pendente square-12"></span>
                            <span class="info-box-text fontSize_10 p-l-5"> Pendente
                                <br />
                                <div>
                                    <span id="divQdePedidosPendentes">{{$sum->pendente}}</span>
                                    pedido(s)
                                </div>
                            </span>
                        </div>
                        <div class="col-sm-3 p-r-0">
                            <span class="info-box-icon pedido-app square-12"></span>
                            <span class="info-box-text fontSize_10 p-l-5"> Aplicativo
                                <br />
                                <div>
                                    <span id="divQdePedidosAplicativo">{{$sum->aplicativo}}</span>
                                    pedido(s)
                                </div>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 p-l-0">
                        <div class="col-sm-3 p-r-0">
                            <span class="info-box-icon cancelado square-12"></span>
                            <span class="info-box-text fontSize_10 p-l-5"> Cancelado
                                <br />
                                <div>
                                    <span id="divQdePedidosCancelado">{{$sum->cancelado}}</span>
                                    pedido(s)
                                </div>
                            </span>
                        </div>
                        <div class="col-sm-3 p-r-0">
                            <span class="info-box-icon transferir square-12"></span>
                            <span class="info-box-text fontSize_10 p-l-5"> Transferir
                                <br />
                                <div>
                                    <span id="divQdePedidosTransferir">{{$sum->transferir}}</span>
                                    pedido(s)
                                </div>
                            </span>
                        </div>
                        <div class="col-sm-3 p-r-0">
                            <span class="info-box-icon atrasado square-12"></span>
                            <span class="info-box-text fontSize_10 p-l-5"> Atrasado
                                <br />
                                <div>
                                    <span id="divQdePedidosAtrasados">{{$sum->atrasado}}</span>
                                    pedido(s)
                                </div>
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="col-sm-4">
                            <span class="info-box-text fontSize_10 alignCenter p-l-5">
                                Total de pedidos <br>
                                <span id="totalPedidos">{{$sum->totalPedidos}}</span>
                            </span>
                        </div>
                        <div class="col-sm-4">
                            <span class="info-box-text fontSize_10 alignCenter p-l-5">
                                Total de itens <br>
                                <span id="totalItens">{{$sum->quantidadeConcluidos}}</span>
                            </span>
                        </div>
                        <div class="col-sm-4">
                            <span class="info-box-text fontSize_10 alignCenter p-l-5">
                                Total vendas  <br>
                                <span id="totalVendas">{{requestNumeroDecimalOracle($sum->valorvenda)}}</span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 margTop_10">
                    <div class="col-md-4">
                        <div id="divEditaVariosStatus" class="hidden">
                            @can('update', App\Pedido::class)
                                <button type="button" id='btnEditaVariosStatus' class="btn btn-nw-buscas btn-sm">Editar Selecionados</button>
                            @endcan
                        </div>
                    </div>
                    <div class="col-md-4 col-md-offset-4">
                        <div class="col-sm-4">
                            <span class="info-box-text fontSize_10 alignCenter p-l-5">
                                <button type="button" class="btn btn-xs btn-nw-geral"><i class="btn-nw-geral glyphicon glyphicon-pencil"></i></button> Editar
                            </span>
                        </div>
                        <div class="col-sm-4">
                            <span class="info-box-text fontSize_10 alignCenter p-l-5">
                                <button type="button" class="btn btn-xs btn-nw-geral"><i class="btn-nw-geral glyphicon glyphicon-print"></i></button> Comanda
                            </span>
                        </div>
                        <div class="col-sm-4">
                            <span class="fontSize_10 alignCenter p-l-5">
                                <button type="button" class="btn btn-xs btn-nw-geral"><i class="btn-nw-geral glyphicon glyphicon-list-alt"></i></button> NFCe
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var root = '{{url("")}}';
            @can('update', App\Pedido::class)
                var pode = true;
            @endcan
            @cannot('update', App\Pedido::class)
                var pode = false;
            @endcannot

            @can('create', App\Nfemitida::class)
                var criarNf = true;
            @endcan
            @cannot('create', App\Nfemitida::class)
                var criarNf = false;
            @endcannot

            $(document).ready(function () {
                init('{!!$sum->totalPedidos!!}' / '{!!$resultsPerPage!!}', '{!!$sum->totalPedidos!!}' % '{!!$resultsPerPage!!}');

                $("#checkbox-all").click(function () {
                    $(".great-table-checkbox").each(function (_, element) {
                        $(element).trigger('click');
                    });
                });

                $(document).on('click', '.great-table-checkbox', function () {
                    let isChecked = this.checked;
                    let $tr = $(this.closest('tr'));

                    if (isChecked && !$tr.hasClass("linhaselecionada")) {
                        $tr.addClass("linhaselecionada");
                    } else if (!isChecked && $tr.hasClass("linhaselecionada")) {
                        $tr.removeClass("linhaselecionada");
                    }
                });
            });
            $(window).load(function () {
                onLoadWindow({!!$pedidosTable!!});
            });
        </script>
        <script type="text/javascript" src="{{asset('js/pedidoMonitoramentoTable.js')}}"></script>
    </body>
</html>
