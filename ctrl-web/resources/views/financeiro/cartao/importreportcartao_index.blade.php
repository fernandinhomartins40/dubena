@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Importação de Relatório de Cartão</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Importação de Relatório de Cartão</a></li>
                        </ul>
                        <div class="tab-content">
                            {{Form::open(['url' => @$url, 'id' => 'fmParcelas', 'method' => 'GET', 'class' => 'form-horizontal'])}}
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space" style="margin-left: 1.5%">
                                                <div class="col-sm-2 col-sm-offset-5">
                                                    <label class="mousehover-pointer" id="btnUpload">
                                                        <span class="btn btn-sm btn-nw-registro fa fa-upload fa-lg" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Arquivo">
                                                        </span>
                                                        &nbsp;&nbsp;&nbsp;&nbsp;<span>Selecione</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" style="margin-left: 1.5%">
                                                <div class="col-sm-12">
                                                    <table class="table no-select table-bordered table-condensed" style="font-size: 13.5px;" id="tblParcelas">
                                                        <thead>
                                                            <tr>
                                                                <th>Cód. Parcela</th>
                                                                <th>Cliente</th>
                                                                <th>Data Venda</th>
                                                                <th>Data Baixa</th>
                                                                <th>Autorização</th>
                                                                <th>Líquido (Sistema)</th>
                                                                <th>Bruto (Operadora)</th>
                                                                <th>Pago</th>
                                                                <th>Diferença</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <!--{{$totalSistema = 0}}-->
                                                            <!--{{$totalCartao = 0}}-->
                                                            <!--{{$totalTarifa = 0}}-->
                                                            <!--{{$qtSistema = 0}}-->
                                                            @if(isset($data))
                                                            @foreach($data as $row)
                                                            <!-- {{$financeiro = $row['financeiro']}} -->
                                                            <!-- {{$cliente = is_null($financeiro) ? null : $financeiro['financeiro']['cliente']}} -->
                                                            <!-- {{$valorParcela = is_null($financeiro) ? 0 : $financeiro['valorefetivado']}} -->
                                                            <!--{{$totalSistema += $valorParcela}}-->
                                                            <!--{{$totalTarifa += insertNumeroDecimalOracle($row[3]) - insertNumeroDecimalOracle($row[7])}}-->
                                                            <!--{{$totalCartao += insertNumeroDecimalOracle($row[3])}}-->
                                                            <!-- {{$valorParcela = requestNumeroDecimalOracle($valorParcela)}} -->
                                                            <!-- {{$diff = insertNumeroDecimalOracle($valorParcela) - insertNumeroDecimalOracle($row[3])}}-->
                                                            @if(is_null($financeiro))
                                                            <!-- {{$class = 'cancelado'}} -->
                                                            @elseif($valorParcela != $row[3])
                                                            <!--{{$qtSistema++}}-->
                                                            <!-- {{$class = 'pendente'}} -->
                                                            @else
                                                            <!--{{$qtSistema++}}-->
                                                            <!-- {{$class = ''}} -->
                                                            @endif
                                                            <tr class="{{$class}}">
                                                                <td>{{is_null($financeiro) ? "" : $financeiro['id']}}</td>
                                                                <td>{{is_null($cliente) ? "" : $cliente->nome}}</td>
                                                                <td>{{$row[1]}}</td>
                                                                <td>{{$row[0]}}</td>
                                                                <td>{{$row[10]}}</td>
                                                                <td>{{$valorParcela}}</td>
                                                                <td>{{$row[3]}}</td>
                                                                <td>{{$row[7]}}</td>
                                                                <td>{{requestNumeroDecimalOracle($diff)}}</td>
                                                            </tr>
                                                            @endforeach
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-sm-10 col-sm-offset-1">
                                                <div class="form-group crud_space">
                                                    <div class="col-sm-2 margTop_15">
                                                        <div id="totalParcelas">
                                                            @if(isset($data))
                                                            {{$qtSistema}}/{{count($data)}} registros
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <i>
                                                        <div class="col-sm-3 col-sm-offset-1 margTop_15">
                                                            <div>
                                                                @if(isset($data))
                                                                Total Cartão: {{requestNumeroDecimalOracle($totalCartao)}}
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-3 margTop_15">
                                                            <div>
                                                                @if(isset($data))
                                                                Total Sistema: {{requestNumeroDecimalOracle($totalSistema)}}
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-3 margTop_15">
                                                            <div>
                                                                @if(isset($data))
                                                                Tarifa: {{requestNumeroDecimalOracle($totalTarifa)}}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </i>
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
                            <div class="box-footer">
                                <div class="col-md-4">
                                    @if(isset($data) && count($data) > 0)
                                    {{Form::submit('Continuar', ['class' => 'btn btn-nw-registro'])}}
                                    @endif
                                </div>
                            </div>
                            {{Form::close()}}
                        </div>
                    </div>
                </div><!-- /.panel-default -->
            </div>
        </div>
    </div>
</div>
@include('general.modals.upload_file')
<form action="" id="fmAux" method="post">
    <input type="hidden" name='file-upload' id="file">
</form>

<script type="text/javascript">
    $("#file-upload").attr('accept', '.csv');
    var validFormatUpload = ['csv'];
    var callbackUpload = function () {
        var url = root + '/importReportCartao';
        $("#fmUpload").off().attr({
            'action': url,
            'method': 'post'
        }).on('submit', function () {
            if (isEmpty($("#file-upload").val())) {
                bootbox.alert('Selecione um arquivo');
                return false;
            }
        });
    };
    $("#btnUpload").on('click', function () {
        $("#modal-upload-file").modal('show');
    });
    $(document).ready(function () {
        tblParcelas = $("#tblParcelas").DataTable({
            "language": {"url": urlDataTable},
            'paginate': false,
            'filter': false,
            'sort': false,
            'scrollY': "350",
            "bAutoWidth": false,
            'bInfo': false
        });
    });
</script>
@endsection
