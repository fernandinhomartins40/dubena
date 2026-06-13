
@extends('layouts.mainmenu')

@section('content')
<style>
    .checkbox input[type="checkbox"], .checkbox-inline input[type="checkbox"]{
        margin-left: 0px;
    }
</style>
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                @can('create', App\Centrocusto::class)
                                    <button type="button" id="" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Centros de Custo</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" url='' btnClick='false' class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Chave</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Ativo</th>
                                            <th>Permite Lançamento</th>
                                            <th>Nível</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="centrocustos-list">
                                        @foreach ($centrocustosall as $centrocusto)
                                        <tr id="centrocusto{{$centrocusto->id}}">
                                            <td>{{$centrocusto->id}}</td>
                                            <td>{{$centrocusto->codigo}}</td>
                                            <td>{{$centrocusto->descricao}}</td>
                                            <td>{{$centrocusto->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>{{$centrocusto->finalizador == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>{{$centrocusto->nivel}}</td>
                                            <td>
                                                @can('view', $centrocusto)
                                                    <button id="btnVisualizar" class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $centrocusto)
                                                    <button id="btnEditar"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $centrocusto)
                                                    <button id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                            <span class="fa fa-trash fa-lg"></span>
                                                    </button>
                                                @endcan
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
                                @can('create', App\Centrocusto::class)
                                    <button type="button" id="" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h4 class="modal-title" id="myModalLabelCadastro"></h4>
                        </div>
                        {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroAjax']) }}
                        <div class="modal-body">
                            <div class="box-body">
                                <div class="form-group crud_space col-sm-12">
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
                                        <input type="hidden" id="grupo_id_novo" name="grupo_id">
                                        <input type="hidden" id="cc_id" name="cc_id">
                                        <input type="hidden" id="empresa_id_novo" name="grupo_id">
                                        <input type="hidden" id="id" name="id">
                                        <input type="hidden" id="metodo" name="_method">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('codigo', 'Chave:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
                                        <div class="input-group">
                                            {!! Form::text('codigoDisabled',null,['class'=>'form-control input-sm', 'id'=>'codigoDisabled', 'disabled' => 'true']) !!}
                                            {!! Form::hidden('codigo',null,['id'=>'codigo']) !!}
                                            <div class="input-group-addon">
                                                <a href="#" id='btnAbrirCC' onclick='abrirCentroCusto();'>
                                                    <i class="glyphicon glyphicon-check"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="ativo" class="col-sm-3 control-label input-sm required">Ativo:</label>
                                    <div class="col-sm-9 checkbox">

                                        {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                    </div>
                                </div><!-- 
                                <div class="form-group crud_space col-sm-12">
                                    <label for="finalizador" class="col-sm-3 control-label input-sm required">Permite Lançamento:</label>
                                    <div class="col-sm-9 checkbox">
                                        {{Form::hidden('finalizador',0)}}
                                        {{ Form::checkbox('finalizador', 1, null, ['id'=>'finalizador']) }}
                                    </div>
                                </div> -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                            {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                            <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <span class="glyphicon glyphicon-remove"></span>
                                <div id="save_result"></div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>

            <div class="modal fade" id="myModalDel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h4 class="modal-title" id="myModalLabel">Remover Registro</h4>
                        </div>
                        {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroDel']) }}
                        <div class="modal-body">
                            <div class="box-body">
                                <div class="form-group crud_space col-sm-12">
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10">
                                        <input type="hidden" id="id_del" name="id">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao_del']) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                            {!! Form::submit('Remover', ['class' => 'btn btn-nw-registro']) !!}
                            <div id="saveErrorDel" class="alert alert-danger alert-dismissable" style="display:none;">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <span class="glyphicon glyphicon-remove"></span>
                                <div id="save_result"></div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
            <!--Rota para um novo cadastro via ajax-->
            <div id='rotaStore' class="hidden">{{route('centrocusto.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('centrocusto')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('centrocusto')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('centrocusto.index')}}</div>
            <!-- page script -->
        </div><!-- /.content-wrapper -->
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function () {
        $('.btnNovoCadastro').on('click', function () {
            $('#myModalLabelCadastro').text('Novo Registro');
            $('#fmCadastroAjax')[0].reset();
            $('#fmCadastroAjax :input').prop('disabled', false).trigger('chosen:updated');
            $("#codigo").val('');
            $("#cc_id").val('');
            $('#codigoDisabled').prop('disabled', true);
            $('#btnAbrirCC').attr('onclick', 'abrirCentroCusto()');
            $('#fmCadastroAjax :submit').show();
        });
        $(".modal").on('hide.bs.modal', function () {
            if(this.id !== 'popup_centrocusto')
                $('#id').val('');                
            else{
                setTimeout(function () {
                    $("#id").val($("#cc_id").val());
                }, 500);
            }
        });
    });

    // function view () {
        
    // }

    // function edit () {

    // }
    
    $('#tblCadastro').on('click', 'button', function () {
        $('#fmCadastroAjax :submit').show();
        var trElem = $(this).closest("tr"); // grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        var codigo = $(trElem).children("td")[1];
        var descricao = $(trElem).children("td")[2];
        var ativo = $(trElem).children("td")[3];
        var finalizador = $(trElem).children("td")[4];
        var pagarreceber = $(trElem).children("td")[6];
        if ($(firstTd).text() !== "") {
            if ($(this).context.id === 'btnEditar') {
                $('#myModalLabelCadastro').text('Editar Registro');
                $('#fmCadastroAjax :input').prop('disabled', false).trigger('chosen:updated');
                $('#btnAbrirCC').attr('onclick', 'abrirCentroCusto()');
                $('#id').val($(firstTd).text());
                $('#cc_id').val($(firstTd).text());
                $('#codigo').val($(codigo).text());
                $('#codigoDisabled').val($(codigo).text());
                $('#descricao').val($(descricao).text());
                $('#ativo').prop("checked", $(ativo).text() == 'Sim');
                $('#finalizador').prop("checked", $(finalizador).text() == 'Sim');
                $('#pagarreceber').val($(pagarreceber).text());
                $('#codigoDisabled').prop('disabled', true);
                $('#myModal').modal('show');
                setTimeout(function () {
                    $('#descricao').focus();
                }, 500);
            } else if($(this).context.id == 'btnVisualizar') {
                $('#fmCadastroAjax :input').prop('disabled', true).trigger('chosen:updated');
                $('#fmCadastroAjax :button').prop('disabled', false);
                $('#fmCadastroAjax :submit').prop('disabled', true);
                $('#btnAbrirCC').removeAttr('onclick', true);
                $('#fmCadastroAjax :submit').hide();
                $('#myModalLabelCadastro').text('Visualizar Registro');
                $('#id').val($(firstTd).text());
                $('#cc_id').val($(firstTd).text());
                $('#codigo').val($(codigo).text());
                $('#codigoDisabled').val($(codigo).text());
                $('#descricao').val($(descricao).text());
                $('#ativo').prop("checked", $(ativo).text() == 'Sim');
                $('#finalizador').prop("checked", $(finalizador).text() == 'Sim');
                $('#pagarreceber').val($(pagarreceber).text());
                $('#myModal').modal('show');
            } else {
                $('#id_del').val($(firstTd).text());
                $('#descricao_del').val($(descricao).text());
                $('#myModalDel').modal('show');
                $('#fmCadastroDel :input').prop('disabled', true);
            }
            $('#fmCadastroDel :button').prop('disabled', false);
            $('#fmCadastroDel :submit').prop('disabled', false);
        }
    });
/*
    $('#tblCadastro').on('click', 'tr', function () {
        var trElem = $(this).closest("tr"); // grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        var codigo = $(trElem).children("td")[1];
        var descricao = $(trElem).children("td")[2];
        var ativo = $(trElem).children("td")[3];
        var finalizador = $(trElem).children("td")[4];
        var pagarreceber = $(trElem).children("td")[6];
        var url = $('#tblCadastro').attr("url");
        var btnClick = $('#tblCadastro').attr("btnClick");
        var id = parseInt($(firstTd).text());
        if (btnClick === "false" && url === "" && !isNaN(id)) {
            $('#fmCadastroAjax :input').prop('disabled', true).trigger('chosen:updated');
            $('#fmCadastroAjax :button').prop('disabled', false);
            $('#fmCadastroAjax :submit').prop('disabled', true);
            $('#btnAbrirCC').removeAttr('onclick', true);
            $('#fmCadastroAjax :submit').hide();
            $('#myModalLabelCadastro').text('Visualizar Registro');
            $('#id').val($(firstTd).text());
            $('#cc_id').val($(firstTd).text());
            $('#codigo').val($(codigo).text());
            $('#codigoDisabled').val($(codigo).text());
            $('#descricao').val($(descricao).text());
            $('#ativo').prop("checked", $(ativo).text() == 'Sim');
            $('#finalizador').prop("checked", $(finalizador).text() == 'Sim');
            $('#pagarreceber').val($(pagarreceber).text());
            $('#myModal').modal('show');
        }
    });*/


</script>

@include('general.centrocusto_partials.centrocusto_partial1_js')
@include('general.centrocusto_partials.centrocusto_partial2_js')
@include('general.centrocusto_partials.centrocusto_partial1')

@endsection
