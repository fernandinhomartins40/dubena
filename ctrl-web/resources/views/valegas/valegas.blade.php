
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
                                @can('create', App\Valegasvenda::class)
                                    <a href="{{ URL::route('vendavalegas.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Vender Vale Gás</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="form-group crud_space">
                                <div class="col-sm-3">
                                    {!! Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-4 control-label input-sm']) !!}
                                    <div class="col-sm-8">
                                        <div class="input-group generalDatePicker">
                                            {!! Form::text('datainicio',null,['class'=>'form-control input-sm generalDatePicker', 'id' => 'datainicio']) !!}
                                            <span class="input-group-addon">
                                                <i class="glyphicon glyphicon-calendar"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-3">
                                    {!! Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-4 control-label input-sm']) !!}
                                    <div class="col-sm-8">
                                        <div class="input-group generalDatePicker">
                                            {!! Form::text('datafim',null,['class'=>'form-control input-sm generalDatePicker', 'id' => 'datafim']) !!}
                                            <span class="input-group-addon">
                                                <i class="glyphicon glyphicon-calendar"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-2">
                                    <button class="btn btn-sm btn-nw-buscas" id='btnBuscar' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                        <span class="fa fa-search fa-lg"></span>
                                    </button>
                                    <a class="btn btn-sm btn-github" id='btnLimpar' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar" href="{{ route('vendavalegas.index') }}">
                                        <span class="fa fa-recycle fa-lg"></span>
                                    </a>
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                <div class="col-md-12">
                                    <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                        <thead>
                                            <tr>
                                                <th>C&oacute;digo</th>
                                                <th>Data</th>
                                                <th>Cliente</th>
                                                <th>Valor Total</th>
                                                <th>Pré-Venda</th>
                                                <th style="width:200px;">Operação</th>
                                            </tr>
                                        </thead>
                                        <tbody id="clientes-list" name="clientes-list">
                                            @foreach ($vendavalegas as $vgas)
                                                @if($vgas->cancelado != 1)
                                                    <tr>
                                                @else
                                                    <tr class="danger">
                                                @endif
                                                <td>{{ $vgas->id }}</td>
                                                <td>{{ requestDataOracle($vgas->datavenda,false) }}</td>
                                                <td>{{ $vgas->Cliente->nome }}</td>
                                                <td>{{ $vgas->valortotal === null ? "Pré-Venda" : requestNumeroDecimalOracle($vgas->valortotal) }}</td>
                                                <td>{{ $vgas->prevenda === "0" ? "Não" : "Sim" }}</td>
                                                <td>
                                                    @if($vgas->cancelado != 1)
                                                        <!--<a href="{{URL::to('vendavalegas/duplicata/'.$vgas->id)}}" target="_blank" type="button" id="btnPdf" class="btn btn-xs btn-danger">PDF</a>-->
                                                        @can('view', $vgas)
                                                            <button onclick="window.location.href = '{{route('vendavalegas.show',$vgas->id)}}'"
                                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                    <span class="fa fa-eye fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                        @can('delete', $vgas)
                                                            <span data-toggle="modal" data-target="#modalSenha" class="btnCancelar" id="{{ $vgas->id }}">
                                                                <button class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Cancelar">
                                                                    <span class="fa fa-trash fa-lg"></span>
                                                                </button>
                                                            </span>
                                                        @endcan
                                                    @else
                                                        @can('view', $vgas)
                                                            <button onclick="window.location.href = '{{route('vendavalegas.show',$vgas->id)}}'"
                                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                    <span class="fa fa-eye fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                        <button type="button" id="" disabled='disabled' class="btn btn-xs ">Cancelada</button>
                                                    @endif
                                                    @if($vgas->cancelado != 1)
                                                        @can('view', $vgas)
                                                            <a href="{{URL::to('vendavalegas/duplicata/'.$vgas->id)}}" target="_blank"
                                                                    type="button" id='btnPdf' class="btn btn-nw-registro btn-xs" data-toggle='tooltip' data-trigger="hover"
                                                                    data-placement="bottom" title="Gerar Duplicata">
                                                                        <span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="urlRedirect"></div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Valegasvenda::class)
                                    <a href="{{ URL::route('vendavalegas.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div><div id="urlRedirect"></div>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div>
    </div>
</div>
@include('general.modal_senhamestra')
@include('valegas.imprimir_modal')
<!--Rota para deletar via ajax-->
<div id='rotaSenha' class="hidden">{{url('empresaconfig/verificasenhamestre')}}</div>

<script type="text/javascript" src="{{URL::to('js/valegas.js')}}"></script>
<script type="text/javascript">
    const url = "{{route('vendavalegas.index')}}"

    $(document).ready(function () {
        let inicio = getParametro("inicio");
        let fim = getParametro("fim");

        if (inicio) $("#datainicio").val(inicio);
        if (fim) $("#datafim").val(fim);
    });

    $("#btnBuscar").click(function () {
        let inicio = $("#datainicio").val();
        let fim = $("#datafim").val();

        window.location.href = `${url}?inicio=${inicio}&fim=${fim}`;
    });

    @if ($errors->any())
        errorsany = true;
    @else
        errorsany = false;
    @endif

    @if(isset($_GET['cliente']))
    @can('imprimirGas',App\Valegasvenda::class)
        if(performance.navigation.type != 1) {
            bootbox.confirm({
                title: "Confirmação",
                message: "Deseja imprimir o vale gás?",
                buttons: {
                    cancel: {
                        label: "Não",
                        className: "btn-nw-geral pull-center"
                    },
                    confirm: {
                        label: "Sim",
                        className: "btn-nw-registro pull-center"
                    }
                },
                callback: function (result) {
                    if (result) {
                        let id = '{{$_GET["cliente_id"]}}';
                        let nome = '{{$_GET["cliente"]}}';
                        let prevenda = '{{$_GET["prevenda"]}}';
                        $("#imprimir_modal").modal('show');
                        selectizeAddItem(id, nome);
                        if(prevenda != "0"){
                            $("#checkprevenda").prop('checked',true);
                        }
                    }
                }
            });
        }
    @endcan
    @cannot('imprimirGas',App\Valegasvenda::class)
        if(performance.navigation.type != 1) {
            bootbox.alert('Você precisará de permissão para imprimir os vale gás.');
        }
    @endcannot
    @endif
</script>
@endsection
