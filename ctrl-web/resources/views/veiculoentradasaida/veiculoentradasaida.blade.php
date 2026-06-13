 @extends('layouts.mainmenu') @section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                @can('create', App\Veiculoentradasaida::class)
                                    <a href="{{ URL::route('veiculoentradasaida.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                            <!--col-md-6-->
                        </div>
                        <!--col-md-12-->
                    </div>
                    <!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Movimentações de Veículos</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>Id</th>
                                            <th>Veículo</th>
                                            <th>Km Último</th>
                                            <th>Km Atual</th>
                                            <th>Tipo Movimentação</th>
                                            <th>Data Último Registro</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="agencias-list" name="veiculoentradasaida-list">
                                        @foreach ($veiculoentradasaida as $entsa)
                                        <tr>
                                            <td>{{$entsa->id}}</td>
                                            <!-- {{$entsa->descricao = $entsa->veiculo->placa . ' - ' . $entsa->veiculo->descricao}} -->
                                            <td>{{$entsa->veiculo->placa . ' - ' . $entsa->veiculo->descricao}}</td>
                                            <td>{{$entsa->ultimokm}}</td>
                                            <td>{{$entsa->km}}</td>
                                            <td>{{$entsa->entrada == "0" ? "Saída" : "Entrada"}}</td>
                                            <td>{{requestDataOracle($entsa->datahora,true,true)}}</td>
                                            <td>
                                                @can('view', $entsa)
                                                    <button onclick="window.location.href = '{{route('veiculoentradasaida.show',$entsa->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $entsa)
                                                    <button onclick="removeRegister({{$entsa}})"
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
                                @can('create', App\Veiculoentradasaida::class)
                                    <a href="{{ URL::route('veiculoentradasaida.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
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
                                    {!! Form::label('datahora_del', 'Data:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
                                        {!! Form::text('datahora_del',null,['class'=>'form-control input-sm', 'id'=>'datahora_del', 'readonly','tabindex'=>'-1']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('descricao_del', 'Veículo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
                                        {!! Form::text('descricao_del',null,['class'=>'form-control input-sm', 'id'=>'descricao_del', 'readonly','tabindex'=>'-1']) !!}
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
            <div id='rotaDel' class="hidden">{{url('veiculoentradasaida')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('veiculoentradasaida.index')}}</div>
        </div>
        <!-- /.content-wrapper -->
    </div>
</div>
@endsection