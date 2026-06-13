
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
                                @can('create', App\Estoquerequisicao::class)
                                    <a href="{{ URL::route('estoquerequisicao.create') }}" class="btn criar-novo btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Requisições de Estoque</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12 ">
                                <div class="col-md-12">
                                    <div class="col-md-3">
                                        {{ Form::label('filtro', 'Data Inicial:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) }}
                                        <div class="col-sm-7 input-group generalDatePicker">
                                            {{ Form::text('dataInicial',$dataInicial,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataInicial']) }}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        {{ Form::label('filtro', 'Data Final:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) }}
                                        <div class="col-sm-7 input-group generalDatePicker">
                                            {{ Form::text('dataFinal',$dataFinal,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataFinal']) }}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-nw-buscas" id='btnFiltros' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                            <span class="fa fa-search fa-lg"></span>
                                        </button>
                                        <a class="btn btn-sm btn-github" type="button" href="{{route('estoquerequisicao.index')}}" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                            <span class="fa fa-recycle fa-lg"></span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">

                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Colaborador</th>
                                            <th>Data e Hora</th>
                                            <th>Centro de Custos</th>
                                            <th>Plano de Contas</th>
                                            {{-- <th>Exportar</th> --}}
                                            <th>Operações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="estoquerequisicao-list" name="estoquerequisicao-list">
                                        @foreach ($dados as $estoquerequisicao)
                                        @if($estoquerequisicao->cancelado == 1)
                                        <tr id="estoquerequisicao{{$estoquerequisicao->id}}" class="danger">
                                            @else
                                        <tr id="estoquerequisicao{{$estoquerequisicao->id}}">
                                            @endif
                                            <td class='conteudoTd'>{{$estoquerequisicao->id}}</td>
                                            <td class='conteudoTd'>{{$estoquerequisicao->user->name}}</td>
                                            <td class='conteudoTd'>{{requestDataOracle($estoquerequisicao->datahora)}}</td>
                                            <td class='conteudoTd'>{{$estoquerequisicao->centro}}</td>
                                            <td class='conteudoTd'>{{$estoquerequisicao->plano}}</td>
                                            <td class="exportar">
                                                @if ($estoquerequisicao->cancelado == 0)
                                                    @can('view', $estoquerequisicao)
                                                        <a href="{{route('estoquerequisicao.show',$estoquerequisicao->id)}}"
                                                            class='btn btn-tela btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                <span class="fa fa-eye fa-lg"></span>
                                                        </a>
                                                    @endcan
                                                    @can('update', $estoquerequisicao)
                                                        <a href="{{route('estoquerequisicao.edit',$estoquerequisicao->id)}}"
                                                            class='btn btn-tela btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                        </a>
                                                    @endcan
                                                    @can ('delete', $estoquerequisicao)
                                                        <span data-toggle="modal" data-target="#modalSenha" id="btnCancelar">
                                                            <button class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Cancelar" onclick="onClickRemover({{$estoquerequisicao}})">
                                                                <span class="fa fa-trash fa-lg"></span>
                                                            </button>
                                                        </span>
                                                    @endcan
                                                    @can ('view', $estoquerequisicao)
                                                        <button onclick="window.location.href = '{{URL::to('estoquerequisicao/gerarPDF/'.$estoquerequisicao->id)}}'"
                                                            type="button" id='btnPdf' class="btn btn-nw-registro btn-xs" data-toggle='tooltip' data-trigger="hover"
                                                            data-placement="bottom" title="Gerar PDF">
                                                        <span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
                                                    @endcan
                                                @else
                                                    @can ('view', $estoquerequisicao)
                                                        <button onclick="window.location.href = '{{route('estoquerequisicao.show',$estoquerequisicao->id)}}'"
                                                            class='btn btn-tela btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                <span class="fa fa-eye fa-lg"></span>
                                                        </button>
                                                    @endcan
                                                    <button type="button" id="" disabled='disabled' class="btn btn-xs ">Cancelada</button>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Estoquerequisicao::class)
                                    <a href="{{ URL::route('estoquerequisicao.create') }}" class="btn criar-novo btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            <div class="modal fade" id="myModalDel" modal='true' tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h4 class="modal-title" id="myModalLabel">Tem certeza que deseja cancelar a requisição desta data?</h4>
                        </div>
                        {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroDel']) }}
                        <div class="modal-body">
                            <div class="box-body">
                                {{ Form::text('id_del',null,['class'=>'hidden', 'id'=>'id_del']) }}
                                {{ Form::datetime('dataFinal',$dataFinal,['class'=>'form-control input-sm generalDatePicker ', 'readonly', 'tabindex' => '-1']) }}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                            {{ Form::submit('Ok', ['class' => 'btn btn-nw-registro']) }}
                            <div id="saveErrorDel" class="alert alert-danger alert-dismissable" style="display:none;">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <span class="glyphicon glyphicon-remove"></span>
                                <div id="save_result"></div>
                            </div>
                        </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
            @include('general.modal_senhamestra')
        </div>
    </div>
</div>

<!--Rota para deletar via ajax-->
<div id='rotaSenha' class="hidden">{{url('empresaconfig/verificasenhamestre')}}</div>
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('estoquerequisicao')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden"></div>
<!--Rota para a linguagem do plugin de paginação-->

<!-- page script -->
<script type="text/javascript">
    var exportarClick = false;
    var operacoesClick = false;

    $('.exportar').on('click', function () {
        exportarClick = true;
    });

    $('.operacoes').on('click', function () {
        operacoesClick = true;
    });

    $('#btnFiltros').on('click', function () {
        var urlFiltro = '{{url("estoquerequisicao/filter/:dataInicial/:dataFinal")}}';
        var dataInicial = $("#dataInicial").val();
        var dataFinal = $("#dataFinal").val();
        dataInicial = insertDataOracle(dataInicial);
        dataFinal = insertDataOracle(dataFinal);
        urlFiltro = urlFiltro.replace(':dataInicial', dataInicial);
        urlFiltro = urlFiltro.replace(':dataFinal', dataFinal);
        window.location.href = urlFiltro;
    });

    $('.conteudoTd').on('click', function () {
        operacoesClick = false;
        exportarClick = false;
    });

    $(document).ready(function () {
        var urlAtual = $(location).attr('href');
        $("#rotaIndex").text(urlAtual);
    });

    $("#tblCadastro tbody").on( 'click', '.btn-tela', function ( e ) {
        redirecionar( $(this) );
    });

    $(".criar-novo").click( function () {
        redirecionar( $(this) );
    });

    const onClickRemover = function (estoque) {
        $("#id_del").val(estoque.id);
    };

    const redirecionar = function ( $btn ) {
        var href = $btn.attr('href');
        var current = window.location.href;
        if ( current.includes('filter') ) {
            var sp = current.split('filter');
            var index = 'filter' + sp[1];
            href = href + "?index=" + index;
            $btn.attr('href', href);
        }
    };
</script>
@endsection
