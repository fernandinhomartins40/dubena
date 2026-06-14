
@extends('monitora.layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                <button type="button" class="btnNovoCadastro btn btn-nw-registro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Tipos de Veículos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" btnClick='false' url='' urlupdate='' class="table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Tipo</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="veiculotipos-list">
                                        @foreach ($veiculotipos as $veiculotipo)
                                        <tr id="veiculotipo{{$veiculotipo->id}}">
                                            <td>{{$veiculotipo->id}}</td>
                                            <td>{{$veiculotipo->descricao}}</td>
                                            <td>{{$veiculotipo->tiporastreamento}}</td>
                                            <td>{{$veiculotipo->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                <button class='btn btn-nw-geral btn-xs' id="btnEditar" style='max-height:30px;font-size:14px;margin-top:-5px;'>Editar</button>
                                                <button class='btn btn-nw-registro btn-xs' id="btnRemover" style='max-height:30px;font-size:14px;margin-top:-5px;'>Remover</button>
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
                                <button type="button" class="btnNovoCadastro btn btn-nw-registro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
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
                                        <input type="hidden" id="empresa_id_novo" name="grupo_id">
                                        <input type="hidden" id="id" name="id">
                                        <input type="hidden" id="metodo" name="_method">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('tiporastreamento', 'Tipo Padrão:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
                                        {!! Form::select('tiporastreamento',$tipos, null, ['class'=>'form-control input-sm selectChosen', 'id'=>'tiporastreamento']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="ativo" class="col-sm-3 control-label input-sm required">Ativo:</label>
                                    <div class="col-sm-9 checkbox">

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
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
                                        <input type="hidden" id="id_del" name="id">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao_del']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="ativo" class="col-sm-3 control-label input-sm required">Ativo:</label>
                                    <div class="col-sm-9 checkbox">
                                        {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo_del']) }}
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
            <div id='rotaStore' class="hidden">{{route('monitora.veiculotipo.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('veiculotipo')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('veiculotipo')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('monitora.veiculotipo.index')}}</div>
            <!--Rota para a linguagem do plugin de paginação-->
            <div id='urlLanguage' class="hidden">{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}</div>

            <!-- page script -->
            <script type="text/javascript">

                var operacao = "";
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
                        "columns": [
                            {"data": "id"},
                            {"data": "descricao"},
                            {"data": "tiporastreamento"},
                            {"data": "ativo"},
                            {"defaultContent": ""}
                        ],
                         "columnDefs": [
                            {
                                "targets": [ 2 ],
                                "visible": false
                            }
                        ]
                    });
                    $('.btnNovoCadastro').on('click', function () {
                        $('#myModalLabelCadastro').text('Novo Registro');
                        $('#fmCadastroAjax')[0].reset();
                        $('#fmCadastroAjax :input').prop('disabled', false);
                        $('#fmCadastroAjax :submit').show();
                    });

                    //var column = table.column( $(this).attr('data-column') );
                    // column.visible( ! column.visible() );
                });

                $('#tblCadastro').on('click', 'button', function () {
                    $('#tblCadastro').attr('btnClick', 'true');
                    $('#fmCadastroAjax :submit').show();
                    var row = $(this).closest('tr');
                    var data = $('#tblCadastro').dataTable().fnGetData(row);
                    if(data.id != ""){
                        if ($(this).context.id == 'btnEditar') {
                            $('#fmCadastroAjax :input').prop('disabled', false);
                            operacao = 'Edit';
                            $('#myModalLabelCadastro').text('Editar Registro');
                            $('#id').val(data.id);
                            $('#descricao').val(data.descricao);
                            $('#ativo').prop("checked", data.ativo == 'Sim');
                            $('#tiporastreamento').val(data.tiporastreamento);
                            $('#tiporastreamento').trigger("chosen:updated");
                            $('#myModal').modal('show');
                        } else {
                            $('#fmCadastroDel :input').prop('disabled', true);
                            $('#id_del').val(data.id);
                            $('#descricao_del').val(data.descricao);
                            $('#ativo_del').prop("checked", data.ativo == 'Sim');
                            $('#myModalDel').modal('show');
                        }
                    };
                    $('#fmCadastroDel :button').prop('disabled', false);
                    $('#fmCadastroDel :submit').prop('disabled', false);
                });

                $('#tblCadastro').on('click', 'tr', function () {
                    var trElem = $(this).closest("tr"); // grabs the button's parent tr element
                    var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
                    var descricao = $(trElem).children("td")[1];
                    var ativo = $(trElem).children("td")[2];
                    var url = $('#tblCadastro').attr("url");
                    var btnClick = $('#tblCadastro').attr("btnClick");
                    var id = parseInt($(firstTd).text());
                    if (btnClick === "false" && url === "" && !isNaN(id)) {
                        $('#fmCadastroAjax :input').prop('disabled', true);
                        $('#fmCadastroAjax :button').prop('disabled', false);
                        $('#fmCadastroAjax :submit').prop('disabled', true);
						$('#tiporastreamento').prop('disabled', true).trigger("chosen:updated");
						
                        $('#fmCadastroAjax :submit').hide();
                        $('#myModalLabelCadastro').text('Visualizar Registro');
                        $('#id').val($(firstTd).text());
                        $('#descricao').val($(descricao).text());
                        $('#ativo').prop("checked", $(ativo).text() == 'Sim');
                        $('#myModal').modal('show');
                    }
                });

            </script>
        </div><!-- /.content-wrapper -->
    </div>
    @endsection
