
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
                                @can('create', App\Recessotipo::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Tipos de recessos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>legenda</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Cor</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="recesso-list">
                                        @foreach ($tiporecessos as $tiporecessos)
                                        <tr id="recessos{{$tiporecessos->id}}">
                                            <td>{{$tiporecessos->id}}</td>
                                            <td>{{$tiporecessos->legenda}}</td>
                                            <td>{{$tiporecessos->descricao}}</td>
                                            <td style="color: {{$tiporecessos->cor}}">{{$tiporecessos->cor}}</td>
                                            <td>{{$tiporecessos->ativo==1?'Sim':'Não'}}</td>
                                            <td>
                                                @can('view', $tiporecessos)
                                                    <button onclick="viewRegister({{$tiporecessos}})" 
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $tiporecessos)
                                                    <button onclick="editRegister({{$tiporecessos}})" 
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $tiporecessos)
                                                    <button onclick="removeRegister({{$tiporecessos}})"
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
                                @can('create', App\Recessotipo::class)
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
                            <h4 class="modal-title" id="myModalLabelCadastro">Novo Registro</h4>
                        </div>
                    {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroAjax']) }}
                        <div class="modal-body">
                            <div class="box-body">
                                <input type="hidden" id="id" name="id">
                                <input type="hidden" id="metodo" name="_method">
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-10">
                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    <label for="legenda" class="col-sm-2 control-label input-sm">Legenda:</label>
                                    <div class="col-sm-4">
                                        <input class="form-control input-sm" id="legenda" name="legenda" type="text">
                                    </div>
                                    {!! Form::label('cor', 'Cor:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                    <div class="col-sm-4">
                                        <input class="form-control input-sm" id="cor" name="cor" type="color">
                                    </div>
                                </div>

                                <div class="form-group crud_space col-sm-12">
                                    <label for="ativo" class="col-sm-2 control-label input-sm required">Ativo:</label>
                                    <div class="col-sm-1">
                                        {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                        {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) !!}
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
            <div id='rotaStore' class="hidden">{{route('tiporecessos.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('tiporecessos')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('tiporecessos')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('tiporecessos.index')}}</div>
    </div><!-- /.content-wrapper -->
</div>
@endsection
