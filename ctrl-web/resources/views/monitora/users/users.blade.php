
@extends('monitora.layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Usuários</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                    <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Login</th>                                            
                                            <th>Nome</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="users-list" name="users-list">
                                        @foreach ($users as $user)
                                        <tr id="user{{$user->id}}">
                                            <td>{{$user->id}}</td>
                                            <td>{{$user->email}}</td>
                                            <td>{{$user->name}}</td>
                                            <td>
                                                <button onclick="window.location.href = '{{route('monitora.user.show',$user->id)}}'"
                                                    class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                        <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                                <button onclick="window.location.href = '{{route('monitora.user.edit',$user->id)}}'"
                                                    class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                </button>
                                                <button onclick="removeRegister({{$user}})"
                                                    id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                            <span class="fa fa-trash fa-lg"></span>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                </div><!-- /.col -->
            </div><!-- /.row -->
            <div id='rotaDel' class="hidden">{{url('user')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('monitora.user.index')}}</div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
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
                        {{ Form::label('id_del', 'Código:', ['class'=>'col-sm-3 control-label input-sm']) }}
                        <div class="col-sm-9">
                            {{ Form::text('id',null,['class'=>'form-control input-sm', 'id'=>'id_del', 'readonly','tabindex'=>'-1']) }}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {{ Form::label('name_del', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) }}
                        <div class="col-sm-9">
                            {{ Form::text('name_del',null,['class'=>'form-control input-sm', 'id'=>'name_del', 'readonly','tabindex'=>'-1']) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                {{ Form::submit('Remover', ['class' => 'btn btn-nw-registro']) }}
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

@endsection
