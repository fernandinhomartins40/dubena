@extends('layouts.mainmenu') @section('content')

<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                @can('create', App\Veiculotrocaoleo::class)
                                    <a href="{{ URL::route('veiculotrocaoleo.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                            <!--col-md-6-->
                        </div>
                        <!--col-md-12-->
                    </div>
                    <!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Trocas de Óleo</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Id</th>
                                            <th>Data</th>
                                            <th>Veículo</th>
                                            <th>Condutor</th>
                                            <th>Km Última troca</th>
                                            <th>Km na Troca</th>
                                            <th>Km Próxima Troca</th>
                                            <th>Redimento do Óleo</th>
                                            <th>Km Alerta Antes</th>
                                            <th>Operações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="trocaoleo-list" name="trocaoleo-list">
                                        @foreach ($veiculotrocaoleo as $oleo)
                                        <tr id="veiculotrocaoleos{{$oleo->id}}">
                                            <td>{{$oleo->id}}</td>
                                            <td>{{$oleo->data}}</td>
                                            <td>{{$oleo->placa}}</td>
                                            <td>{{$oleo->nome}}</td>
                                            <td>{{$oleo->kmultimatrocaoleo}}</td>
                                            <td>{{$oleo->kmtrocaoleo}}</td>
                                            <td>{{$oleo->oleoproximatroca}}</td>
                                            <td>{{$oleo->oleorendimento}}</td>
                                            <td>{{$oleo->kmalertaantes}}</td>
                                            <td>
                                                @can('view', $oleo)
                                                    <button onclick="window.location.href = '{{route('veiculotrocaoleo.show',$oleo->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $oleo)
                                                    <button onclick="removeRegister({{$oleo}})"
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
                        </div>
                        <!-- /.box-body -->
                    </div>
                    <!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Veiculotrocaoleo::class)
                                    <a href="{{ URL::route('veiculotrocaoleo.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
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
                        {!! Form::label('id_del', 'Código:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-9">
                            {!! Form::text('id',null,['class'=>'form-control input-sm', 'id'=>'id_del', 'readonly','tabindex'=>'-1']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('data', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-9">
                            {!! Form::text('data',null,['class'=>'form-control input-sm', 'id'=>'data_del', 'readonly','tabindex'=>'-1']) !!}
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

<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('veiculotrocaoleo')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('veiculotrocaoleo.index')}}</div>


@endsection