
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                @can('create', App\Estoquetransferencia::class)
                                <a href="{{ URL::route('estoquetransferencias.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Transferências de Estoque</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <div class="col-md-12 ">
                                    <div class="col-md-12">
                                        <div class="col-md-3">
                                            {!! Form::label('dataInicio', 'Data Inicial:', ['class'=>'col-sm-5 control-label input-sm','style'=>'text-align:right;']) !!}
                                            <div class="col-sm-7 input-group generalDatePicker">
                                                {!! Form::text('dataInicio',@$dataInicio,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataInicio']) !!}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            {!! Form::label('dataFim', 'Data Final:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) !!}
                                            <div class="col-sm-7 input-group generalDatePicker">
                                                {!! Form::text('dataFim',@$dataFim,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataFim']) !!}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <button class="btn btn-sm btn-nw-buscas" id='btnFiltros' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                                <span class="fa fa-search fa-lg"></span>
                                            </button>
                                        <a class="btn btn-sm btn-github" type="button" href="{{route('estoquetransferencias.index')}}" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                            <span class="fa fa-recycle fa-lg"></span>
                                        </a>
                                        </div>
                                    </div>
                                    <table id="tblCadastro" class="table table-bordered table-hover table-condensed">
                                        <thead>
                                            <tr>
                                                <th>C&oacute;digo</th>
                                                <th>Data</th>
                                                <th>Origem</th>
                                                <th>Destino</th>
                                                <th>Colaborador</th>
                                                <th>Operações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="estoquetransferencia-list" name="estoquetransferencia-list">
                                            @foreach ($estoquetransferencias as $estoquetransferencia)
                                            <tr id="estoquetransferencia{{$estoquetransferencia->id}}">
                                                <td class='conteudoTd'>{{$estoquetransferencia->id}}</td>
                                                <td class='conteudoTd'>{{requestDataOracle($estoquetransferencia->datahora)}}</td>
                                                <td class='conteudoTd'>{{$estoquetransferencia->origemSetor->descricao}}</td>
                                                <td class='conteudoTd'>{{$estoquetransferencia->destinoSetor->descricao}}</td>
                                                <td class='conteudoTd'>{{$estoquetransferencia->user->name}}</td>
                                                <td class="exportar">
                                                    @can('view', $estoquetransferencia)
                                                    <button onclick="window.location.href = '{{route('estoquetransferencias.show',$estoquetransferencia->id)}}'"
                                                            class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                        <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                    <button onclick="window.location.href = '{{URL::to('estoquetransferencias/gerarPDF/'.$estoquetransferencia->id)}}'"
                                                            type="button" id='btnPdf' class="btn btn-nw-registro btn-xs" data-toggle='tooltip' data-trigger="hover"
                                                            data-placement="bottom" title="Gerar PDF">
                                                        <span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
                                                    @endcan
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div><!-- /.box-body -->
                        </div><!-- /.box -->
                    </div><!-- /.col -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Estoquetransferencia::class)
                                <a href="{{ URL::route('estoquetransferencias.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.row -->
            </div><!-- /.content-wrapper -->
        </div>
    </div>
</div>
<!-- page script -->
<script type="text/javascript">
    var exportarClick = false;
    $(document).ready(function () {
    $('#tblCadastro').dataTable({
    "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": true,
            "bPaginate": true,
            "bLengthChange": false,
            "bFilter": true,
            "bSort": true,
            "bInfo": true,
            "bAutoWidth": false,
            "pageLength": 30

    });
    });
    $('.exportar').on('click', function () {
    exportarClick = true;
    });
    $('.conteudoTd').on('click', function () {
    exportarClick = false;
    });
    $('#tblCadastro').on('click', 'tr', function () {
    var trElem = $(this).closest("tr"); // grabs the button's parent tr element
    var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
    if ($(firstTd).text() != "") {
    //console.log($(firstTd).text());
    var id = $(firstTd).text();
    var url = '{{ route("estoquetransferencias.show", ":id") }}';
    url = url.replace(':id', id);
    if (exportarClick) {

    } else {
    window.location.href = url;
    }
    }
    });
    $("#btnFiltros").on('click', function () {
    var dataInicio = $("#dataInicio").val();
    var dataFim = $("#dataFim").val();
    var url = root + '/estoquetransferencias?dataInicio=' + dataInicio + '&dataFim=' + dataFim;
    window.location.href = url;
    });
</script>
@endsection
