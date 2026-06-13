
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
                                @can('create', App\Planoconta::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Planos de Conta</h3>
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
                                            <th>Receita/Despesa</th>
                                            <th class="hidden">Tipo</th>
                                            <th>Tipo</th>
                                            <th class="hidden">naturezasped</th>
                                            <th>Natureza Sped</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="planocontas-list">
                                        @foreach ($planocontasall as $planoconta)
                                        <tr id="planoconta{{$planoconta->id}}">
                                            <td>{{$planoconta->id}}</td>
                                            <td>{{$planoconta->codigo}}</td>
                                            <td>{{$planoconta->descricao}}</td>
                                            <td>{{$planoconta->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>{{$planoconta->finalizador == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>{{$planoconta->nivel}}</td>
                                            <td>{{$planoconta->pagarreceber}}</td>
                                            @if($planoconta->investimento == 1)
                                                <!-- {{$planoconta->tipo = 1}} -->
                                                <td class="hidden">{{$planoconta->tipo}}</td>
                                                <td>Investimento</td>
                                            @elseif($planoconta->custosvariaveis == 1)
                                                <!-- {{$planoconta->tipo = 0}} -->
                                                <td class="hidden">{{$planoconta->tipo}}</td>
                                                <td>Custos Variáveis</td>
                                            @else
                                                <!-- {{$planoconta->tipo = ''}} -->
                                                <td class="hidden">{{$planoconta->tipo}}</td>
                                                <td>Outros</td>
                                            @endif
                                            <td class="hidden">{{$planoconta->naturezasped}}</td>
                                            <td>
                                                @if($planoconta->naturezasped == '01')
                                                    01 - Contas de Ativo
                                                @elseif($planoconta->naturezasped == '02')
                                                    02 - Contas de Passivo
                                                @elseif($planoconta->naturezasped == '03')
                                                    03 - Patrimônio Líquido
                                                @elseif($planoconta->naturezasped == '04')
                                                    04 - Contas de Resultado
                                                @elseif($planoconta->naturezasped == '05')
                                                    05 - Contas de Compensação
                                                @elseif($planoconta->naturezasped == '09')
                                                    09 - Outras
                                                @endif
                                            </td>
                                            <td>
                                                <!-- <button class='btn btn-nw-geral btn-xs' id="btnEditar">Editar</button>
                                                <button class='btn btn-nw-registro btn-xs' id="btnRemover">Remover</button>
                                                 -->
                                                 @can('view', $planoconta)
                                                    <button id="btnVisualizar"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $planoconta)
                                                    <button id="btnEditar"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $planoconta)
                                                    <button id="btnRemover"
                                                            class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
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
                                @can('create', App\Planoconta::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
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
                                        <input type="hidden" id="id" name="id">
                                        <input type="hidden" id="pc_id" name="id">
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
                                                <a href="#" id='btnAbrirPC' onclick='abrirPlanoConta();'>
                                                    <i class="glyphicon glyphicon-check"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('pagarreceber', 'Receita/Despesa:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
                                        {!! Form::select('pagarreceber', $pagarreceber, null, ['class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="tipo" class="col-sm-3 control-label input-sm required">Tipo:</label>
                                    <div class="col-sm-9">
                                        {!! Form::select('tipo', ['' => 'Selecione','0' => 'Custos Variáveis', '1' => 'Investimento'], null, ['id' => 'tipo', 'class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="naturezasped" class="col-sm-3 control-label input-sm required">Natureza Sped:</label>
                                    <div class="col-sm-9">
                                        {!! Form::select('naturezasped',
                                            ['' => 'Selecione',
                                            '01' => '01 - Contas de Ativo',
                                            '02' => '02 - Contas de Passivo',
                                            '03' => '03 - Patrimônio Líquido',
                                            '04' => '04 - Contas de Resultado',
                                            '05' => '05 - Contas de Compensação',
                                            '09' => '09 - Outras',
                                            ], null, ['id' => 'naturezasped', 'class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="ativo" class="col-sm-3 control-label input-sm required">Ativo:</label>
                                    <div class="col-sm-3 checkbox">
                                        {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                    </div>
                                </div>
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
            <div id='rotaStore' class="hidden">{{route('planoconta.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('planoconta')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('planoconta')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('planoconta.index')}}</div>

        </div><!-- /.content-wrapper -->
    </div>
</div>
<!-- page script -->
<script type="text/javascript">

    $(document).ready(function () {
        planoconta = true;
        pagarreceber = null;
        nivel = null;
        validaNivel = false;
        $('.btnNovoCadastro').on('click', function () {
            pagarreceber = null;
            nivel = null;
            validaNivel = false;
            $('#myModalLabelCadastro').text('Novo Registro');
            $('#fmCadastroAjax')[0].reset();
            $('#fmCadastroAjax :input').prop('disabled', false).trigger('chosen:updated');
            $('#codigoDisabled').prop('disabled', true);
            $("#codigo").val('');
            $("#pc_id").val('');
            $('#btnAbrirPC').attr('onclick', 'abrirPlanoConta()');
            $('#fmCadastroAjax :submit').show();
            pagarreceber = null;
        });
        $(".modal").on('hide.bs.modal', function () {
            if(this.id !== 'popup_planoconta')
                $('#id').val('');
            else{
                setTimeout(function () {
                    $("#id").val($("#pc_id").val());
                }, 500);
            }
        });
    });
    $("#myModal").on('show.bs.modal',function(){
        if($("#descricao").prop('disabled')){
            $('#btnAbrirPC').removeAttr('onclick', true);
        }else{
            $('#btnAbrirPC').attr('onclick', 'abrirPlanoConta()');
        }
    });
    $('#tblCadastro').on('click', 'button', function () {
        $('#tblCadastro').attr('btnClick', 'true');
        $('#fmCadastroAjax :submit').show();
        var trElem = $(this).closest("tr"); // grabs the button's parent tr element
        var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
        var codigo = $(trElem).children("td")[1];
        var descricao = $(trElem).children("td")[2];
        var ativo = $(trElem).children("td")[3];
        var finalizador = $(trElem).children("td")[4];
        var nivel = $($(trElem).children("td")[5]).text();
        var pagarreceber = $(trElem).children("td")[6];
        var tipo = $(trElem).children("td")[7];
        var naturezasped = $(trElem).children("td")[9];
        if ($(firstTd).text() !== "") {
            var btnId = $(this).context.id;
            if(btnId == "btnRemover") {
                $('#id_del').val($(firstTd).text());
                $('#descricao_del').val($(descricao).text());
                $('#myModalDel').modal('show');
                $('#fmCadastroDel :input').prop('disabled', true);
            } else {
                $('#id').val($(firstTd).text());
                $('#pc_id').val($(firstTd).text());
                $('#codigo').val($(codigo).text());
                $('#codigoDisabled').val($(codigo).text());
                $('#descricao').val($(descricao).text());
                $('#ativo').prop("checked", $(ativo).text() == 'Sim');
                $('#pagarreceber').val($(pagarreceber).text()).trigger('chosen:updated');
                $('#naturezasped').val($(naturezasped).text()).trigger('chosen:updated');
                $('#tipo').val($(tipo).text()).trigger('chosen:updated');
                if (btnId === 'btnEditar') {
                    $('#myModalLabelCadastro').text('Editar Registro');
                    $('#fmCadastroAjax :input').prop('disabled', false).trigger('chosen:updated');
                    $('#btnAbrirPC').attr('onclick', 'abrirPlanoConta()');
                    $('#codigoDisabled').prop('disabled', true);
                    if(nivel != 1)
                        $("#pagarreceber").prop('disabled', true).trigger('chosen:updated');
                    setTimeout(function () {
                        $('#descricao').focus();
                    }, 500);
                } else if(btnId == 'btnVisualizar') {
                    $('#myModalLabelCadastro').text('Visualizar Registro');
                    $('#fmCadastroAjax :input').prop('disabled', true).trigger('chosen:updated');
                    $('#fmCadastroAjax :button').prop('disabled', false);
                    $('#fmCadastroAjax :submit').prop('disabled', true);
                    $('#fmCadastroAjax :submit').hide();
                    $('#btnAbrirPC').removeAttr('onclick', true);
                }
                $('#myModal').modal('show');
            }
            $('#fmCadastroDel :button').prop('disabled', false);
            $('#fmCadastroDel :submit').prop('disabled', false);
        }
    });

</script>

@include('general.planocontas_partials.planocontas_partial1_js')
@include('general.planocontas_partials.planocontas_partial2_js')
@include('general.planocontas_partials.planocontas_partial1')

@endsection
