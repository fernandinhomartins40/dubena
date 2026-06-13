
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
                                @can('create', App\Recesso::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Recessos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Inicio</th>
                                            <th>Final</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="recesso-list">
                                        @foreach ($recessos as $recesso)
                                        <tr id="recessos{{$recesso->id}}">
                                            <td>{{$recesso->id}}</td>
                                            <td>{{$recesso->descricao}}</td>
                                            <!-- {{$recesso->datainicio = requestDataOracle($recesso->datainicio,false)}} -->
                                            <!-- {{$recesso->datafinal = requestDataOracle($recesso->datafinal,false)}} -->
                                            <td>{{$recesso->datainicio}}</td>
                                            <td>{{$recesso->datafinal}}</td>
                                            <td>
                                                @can('view', $recesso)
                                                    <button onclick="viewRegister({{$recesso}})" 
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $recesso)
                                                    <button onclick="editRegister({{$recesso}})" 
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $recesso)
                                                    <button onclick="removeRegister({{$recesso}})"
                                                        id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
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
                                @can('create', App\Recesso::class)
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
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10">
                                        <input type="hidden" id="id" name="id">
                                        <input type="hidden" id="metodo" name="_method">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                    </div>

                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('datainicio', 'Início:', ['class'=>'col-sm-2     control-label input-sm','style'=>'text-align:right;']) !!}
                                    <div class="col-sm-4">
                                        <div class="input-group date generalDatePicker" id="datetimepicker1">
                                            {!! Form::datetime('datainicio',null,['class'=>'form-control input-sm generalDatePicker']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('datafinal', 'Final:', ['class'=>'col-sm-2     control-label input-sm','style'=>'text-align:right;']) !!}
                                    <div class="col-sm-4">
                                        <div class="input-group date generalDatePicker" id="datetimepicker1">
                                            {!! Form::datetime('datafinal',null,['class'=>'form-control input-sm generalDatePicker']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('tipo_id', 'Tipo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10">
                                        {!! Form::select('tipo_id',$tiporecessos, null, ['id'=>'tipo_id', 'class' => 'form-control  selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
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
                            <h4 class="modal-title" id="myModalLabel">Remover Cadastro</h4>
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
            <div id='rotaStore' class="hidden">{{route('recessos.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('recessos')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('recessos')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('recessos.index')}}</div>
        </div><!-- /.content-wrapper -->
    </div>
    @endsection
