<!DOCTYPE html>
<html>

<head>
    <link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />
    <title>{{$titulo}}</title>
    <style>
        .bordered { border:1px solid;font-family: Arial; text-align:center }
        .noborderleftright { border-top:1px solid;border-bottom:1px solid;font-family: Arial; text-align:center }
        .noborderleft { border-top:1px solid;border-bottom:1px solid;border-right:1px solid;font-family: Arial; text-align:center }
        .noborderright { border-top:1px solid;border-bottom:1px solid;border-left:1px solid;font-family: Arial; text-align:center }
        .borderedDashed { border:1px dashed;font-family: Arial; text-align:center }
        .borderedl { border:1px solid;font-family: Arial; text-align:left }
        .noborder { border-spacing: 0px; border-collapse: collapse;}
        .noborderspaced { border-spacing: 1px; border-collapse: separate;}
        .fontSize14{font-size: 13px;}
        .fontSize15{font-size: 15px;}
        .marginLeft10{margin-left:10px;}
        .fontSize15{font-size:13.5px;}
        .table500{min-width:500px;}
        .destaque{background-color: lightgray;}
        .money{text-align:right;}
        table { border-spacing: 0px; border-collapse: collapse; margin-left: auto; margin-right: auto; }
        td,th{padding: 3px 7px;}

        p {
            margin: 0;
            padding: 2px;
        }
        @page { margin-top: 10px; }
        body { margin-top: 10px; font-size:11px; font-family: Arial;}

        thead:before,
        thead:after,
        tbody:before,
        tbody:after,
        tfoot:before,
        tfoot:after {
            display: none;
        }
    </style>

</head>
<body class="skin-blue layout-top-nav">
    <div style="font-size:14px;text-align:center;padding-top:20px;">
        {{$titulo}}
    </div>
    <br />
    <br />
    <div style="font-size:14px;text-align:left;padding-top:20px;">
        Dia Entrega: {{$fe->cliente->clienteConvenio->diafechamento}} - Dia Recebimento: {{$fe->cliente->clienteConvenio->diavencimento}} <br/>
        {{$fe->cliente->nome}} <br/>
        {{$fe->cliente->rua->descricao}},
        {{$fe->cliente->numero}} - 
        {{$fe->cliente->bairro->descricao}}
    </div>
    <div style="position:absolute;right:0;padding-top:-60px;">
        @if(Session::get('empresa_padrao')->logo != null)
        <img id="imgInicial" style="max-height:70px;" src="data:image/png;base64,{{Session::get('empresa_padrao')->logo }}" alt="Logotipo"/>
        @else
        <img id="imgInicial" style="max-height:60px;" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo"/>
        @endif
    </div>
    <br />
    <div style="position:absolute;float:left;padding-top:2px;">
        Emissão: {{Carbon\Carbon::now('America/Sao_Paulo')->format('d/m/Y H:i:s')}}
    </div>
    <br />
    <hr />
    <br />
    <div class="content-wrapper">
        <section class="content">
            <div class="fontSize14">
            <!-- {{$clienteant = null}} -->
            @foreach($clientes as $cliente)
                <div style="margin-left:0px;text-align:center;">
                    <p class="fontSize15" style=""><strong>Cliente: {{$cliente->cliente}}</strong></p>
                </div>
                <table class="table500" style="padding: 2px 2px">
                        <thead>                            
                        @if($clienteant == null)
                            <tr class="bordered destaque">
                                <th class="bordered">Data</th>
                                <th class="bordered">Quantidade</th>
                                <th class="bordered">Valor</th>
                            </tr>
                        @endif
                        </thead>
                    <tbody>
                    @foreach($pedidos as $pedido)
                        @if($pedido->cliente_id == $cliente->cliente_id)     
                            <tr class="bordered">
                                <td class="bordered" width="35%">{{$pedido->data}}</td>
                                <td class="bordered" width="35%">{{$pedido->quantidade}}</td>
                                <td class="bordered money">{{$pedido->precovenda}}</td>
                            </tr>
                        @endif
                    @endforeach
                        <tr class="bordered destaque">
                            <td class="bordered">Total</td>
                            <td class="bordered">{{$cliente->totalquantidade}}</td>
                            <td class="bordered money">{{requestNumeroDecimalOracle($cliente->totalcliente)}}</td>
                        </tr>
                    </tbody>
                </table>
                <!-- {{$clienteant = $cliente->cliente_id}} -->
            @endforeach
                <div class="fontSize15" style="margin-left:0px;padding-top:20px;">
                    <p><strong>Valor final com {{$fe->cliente->clienteConvenio->comissao}}% de desconto: {{requestNumeroDecimalOracle($total)}}</strong></p>
                </div>
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
</body>

</html>