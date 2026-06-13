
@extends('layouts.mainmenu')

@section('content')
<link href="{{URL::to('plugins/colorpicker/bootstrap-colorpicker.css')}}" rel="stylesheet" type="text/css" />

<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" onclick="novoCadastro();" data-target="#myModal">Novo Registro</button>
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Tipos de Recessos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Cor</th>
                                            <th>Legenda</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="recessotipos-list">
                                        @foreach ($recessotipos as $recessotipo)
                                        <tr id="recessotipo{{$recessotipo->id}}">
                                            <td>{{$recessotipo->id}}</td>
                                            <td>{{$recessotipo->descricao}}</td>
                                            <td {!!$recessotipo->cor==''?'':'style="color:'.$recessotipo->cor.'";'!!}>{{$recessotipo->cor}}</td>
                                            <td>{{$recessotipo->legenda}}</td>
                                            <td>{{$recessotipo->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                <button class='btn btn-nw-geral btn-xs' id="btnEditar">Editar</button>
                                                <button class='btn btn-nw-registro btn-xs' id="btnRemover">Remover</button>
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
                                <button type="button" id="btnNovo" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" onclick="novoCadastro();" data-target="#myModal">Novo Registro</button>
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
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10">
                                        <input type="hidden" id="grupo_id_novo" name="grupo_id">
                                        <input type="hidden" id="empresa_id_novo" name="grupo_id">
                                        <input type="hidden" id="id" name="id">
                                        <input type="hidden" id="metodo" name="_method">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('legenda', 'Legenda:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-4">
                                        {!! Form::text('legenda',null,['class'=>'form-control input-sm', 'id'=>'legenda']) !!}
                                    </div>
                                    {!! Form::label('cor', 'Cor:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                    <div id="cp2" class="col-sm-5 input-group colorpicker-component">
                                        {!! Form::text('cor',null,['id'=>'cor', 'class'=>'form-control']) !!}
                                        <span class="input-group-addon"><i></i></span>
                                    </div>

                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="ativo" class="col-sm-2 control-label input-sm required">Ativo:</label>
                                    <div class="col-sm-10">

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
                            <h4 class="modal-title" id="myModalLabel">Remover Tipo</h4>
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
            <div id='rotaStore' class="hidden">{{route('recessotipo.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('recessotipo')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('recessotipo')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('recessotipo.index')}}</div>

            <!-- page script -->
            <script type="text/javascript">

                var operacao = "";
                $(document).ready(function () {
                    $('#cp2').colorpicker();
                    $('#tblCadastro').dataTable({
                        "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
                        "processing": true,
                        "bPaginate": true,
                        "bLengthChange": false,
                        "bFilter": true,
                        "bSort": true,
                        "bInfo": true,
                        "bAutoWidth": false,
                        "columns": [
                            {"data": "id"},
                            {"data": "descricao"},
                            {"data": "cor"},
                            {"data": "legenda"},
                            {"data": "ativo"},
                            {"defaultContent": ""}
                        ]
                    });
                });

                $('#tblCadastro').on('click', 'button', function () {
                    var trElem = $(this).closest("tr");// grabs the button's parent tr element
                    var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
                    var descricao = $(trElem).children("td")[1];
                    var cor = $(trElem).children("td")[2];
                    var legenda = $(trElem).children("td")[3];
                    var ativo = $(trElem).children("td")[4];
                    if ($(firstTd).text() != "") {
                        if ($(this).context.id == 'btnEditar') {
                            operacao = 'Edit';
                            $('#myModalLabelCadastro').text('Editar Registro');
                            $('#id').val($(firstTd).text());
                            $('#descricao').val($(descricao).text());
                            $('#cor').val($(cor).text());
                            $('#legenda').val($(legenda).text());
                            $('#ativo').prop("checked", $(ativo).text() == 'Sim');
                            $('#myModal').modal('show');
                        } else {
                            $('#id_del').val($(firstTd).text());
                            $('#descricao_del').val($(descricao).text());
                            $('#myModalDel').modal('show');
                        }
                    }
                    $('.btnNovoCadastro').on('click', function () {
                        $('#fmCadastroAjax')[0].reset();
                        $('#myModalLabelCadastro').text('Novo Registro');
                    });

                });

            </script>
        </div><!-- /.content-wrapper -->
    </div>
    @endsection
