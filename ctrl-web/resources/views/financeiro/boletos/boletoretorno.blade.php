@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Retorno de Remessa</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Retorno de Remessa</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    {{Form::open(['url' => $url, 'id' => 'fmRetornoBoletos', 'method' => 'GET', 'class' => 'form-horizontal'])}}
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{Form::label('ocorrencia_id', 'Ocorrência:', ['class' => 'input-sm control-label col-sm-2'])}}
                                                <div class="col-sm-4">
                                                    {{Form::select('ocorrencia_id', $ocorrencias, null, ['class' => 'selectChosen', 'id' => 'ocorrencia_id'])}}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" style="margin-left: 1.5%">
                                                <div class="col-sm-12">
                                                    <table class="table no-select table-bordered table-condensed padding-table-5" style="font-size: 13.5px;" id="tblBoletos">
                                                        <thead>
                                                            <tr>
                                                                <th>Nosso Número</th>
                                                                <th>Número Documento</th>
                                                                <th>Valor</th>
                                                                <th>Valor Pago</th>
                                                                <th>Ocorrência</th>
                                                                <th>Cód. Parcela</th>
                                                                <th>Cliente</th>
                                                                <th>Tarifa</th>
                                                                <th>Diferença</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <!--{{$qtSistema = 0}}-->
                                                            @foreach($boletos as $boleto)
                                                                @if(empty($boleto['parcela_id']))
                                                                <!-- {{$class = 'cancelado'}} -->
                                                                @elseif($boleto['diferenca'] != 0)
                                                                <!--{{$qtSistema++}}-->
                                                                <!-- {{$class = ''}} -->
                                                                @else
                                                                <!--{{$qtSistema++}}-->
                                                                <!-- {{$class = 'pendente'}} -->
                                                                @endif
                                                                <tr class="{{$class}}">
                                                                    <td>{{$boleto['nossoNumero']}}</td>
                                                                    <td>{{$boleto['numeroDocumento']}}</td>
                                                                    <td>{{requestNumeroDecimalOracle($boleto['valor'])}}</td>
                                                                    <td>{{requestNumeroDecimalOracle($boleto['valorRecebido'])}}</td>
                                                                    <td>{{$boleto['ocorrencia_completa']}}</td>
                                                                    <td>{{$boleto['parcela_id']}}</td>
                                                                    <td>{{$boleto['cliente']}}</td>
                                                                    <td>{{requestNumeroDecimalOracle($boleto['valorTarifa'])}}</td>
                                                                    <td>{{requestNumeroDecimalOracle($boleto['diferenca'])}}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-sm-10 col-sm-offset-1">
                                                <div class="form-group crud_space">
                                                    <div class="col-sm-4 margTop_15">
                                                        <div id="totalParcelas">
                                                            {{$qtSistema}}/{{count($boletos)}} boletos(s) encontrados.
                                                        </div>    
                                                    </div>
                                                    <div class="margTop_15 fright">
                                                        <div id="totalParcelas">
                                                            {{Form::submit('Continuar', ['class' => 'btn btn-nw-registro'])}}
                                                        </div>    
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <div class="col-md-12 margTop_10">
                                                        <div class="col-md-4">
                                                            <span class="info-box-icon cancelado" style="width:15px;height:15px;"></span>
                                                            <span class="info-box-text fontSize_12" style="padding-left: 5px !important"> Lançamentos não encontrados</span>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <span class="info-box-icon pendente" style="width:15px;height:15px;"></span>
                                                            <span class="info-box-text fontSize_12" style="padding-left: 5px !important"> Lançamentos encontrados</span>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <span class="info-box-icon" style="width:15px;height:15px;"></span>
                                                            <span class="info-box-text fontSize_12" style="padding-left: 5px !important"> Valores com diferença</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.panel-default -->
            </div>
        </div>
    </div>
</div>

<!--Rota para um novo cadastro via ajax-->
<div id='rotaStore' class="hidden">{{route('boleto.store')}}</div>
<!--Rota para atualizar via ajax-->
<div id='rotaUpdate' class="hidden">{{url('boleto')}}/</div>
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('boleto')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('boleto.index')}}</div>

<script type="text/javascript">
    $(document).ready(function () {
        tblBoletos = $("#tblBoletos").DataTable({
            "language": {"url": urlDataTable},
            'paginate': false,
            'filter': false,
            'sort': false,
            'scrollY': "350",
            "bAutoWidth": false,
            'bInfo': false
        });
        $("#ocorrencia_id").on('change', function () {
            var ocorrencia = $(this).val();
            var i = 0;
            tblBoletos.rows().every(function () {
                var d = this.data();
                var trElem = $(tblBoletos.row(i).node());
                if(d[4] !=  $('#ocorrencia_id option:selected').text() && !isEmpty(ocorrencia))
                    $(trElem).addClass('hidden');
                else 
                    $(trElem).removeClass('hidden');
                i++;
            });
            tblBoletos.draw();
        });
    });
</script>
@endsection