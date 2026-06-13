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
                                @can('create', App\Pedidosituacao::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Status de Pedidos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" url="" btnClick="false" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Status</th>
                                            <th>Usa Android</th>
                                            <th>Vale Gás</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="pedidosituacoes-list">
                                        @foreach ($pedidosituacoes as $pedidosituacao)
                                        <tr id="pedidosituacao{{$pedidosituacao->id}}">
                                            <td>{{$pedidosituacao->id}}</td>
                                            <td>{{$pedidosituacao->descricao}}</td>

                                            @if($pedidosituacao->entregafinalizada != 0) 
                                            <td>Entrega Finalizada</td>
                                            <!-- {{$pedidosituacao->status = 0}} -->

                                            @elseif ($pedidosituacao->entregacancelada != 0)
                                            <td>Entrega Cancelada</td>
                                            <!-- {{$pedidosituacao->status = 1}} -->

                                            @elseif ($pedidosituacao->entregapendente != 0)
                                            <td>Entrega Pendente</td>
                                            <!-- {{$pedidosituacao->status = 2}} -->

                                            @elseif ($pedidosituacao->fechadoconcluido != 0)
                                            <td>Fechado Concluído</td>
                                            <!-- {{$pedidosituacao->status = 3}} -->

                                            @elseif ($pedidosituacao->fechadocancelado != 0)
                                            <td>Fechado Cancelado</td>
                                            <!-- {{$pedidosituacao->status = 4}} -->

                                            @elseif ($pedidosituacao->entregatranferida != 0)
                                            <td>Entrega Transferida</td>
                                            <!-- {{$pedidosituacao->status = 5}} -->

                                            @elseif ($pedidosituacao->ementrega != 0)
                                            <td>Em Entrega</td>
                                            <!-- {{$pedidosituacao->status = 6}} -->

                                            @elseif ($pedidosituacao->entregadoroffline != 0)
                                            <td>Entregador Off-line</td>

                                            @elseif ($pedidosituacao->pedidorecebidomovel != 0)
                                            <td>Pedido recebido no Móvel</td>
                                            <!-- {{$pedidosituacao->status = 7}} -->

                                            @elseif ($pedidosituacao->pedidolidomovel != 0)
                                            <td>Pedido Lido no Móvel</td>
                                            <!-- {{$pedidosituacao->status = 8}} -->

                                            @endif

                                            <td>{{$pedidosituacao->androidusa == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>{{$pedidosituacao->valegas == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>{{$pedidosituacao->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                @can('view', $pedidosituacao)
                                                    <button onclick="viewRegister({{$pedidosituacao}})" 
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $pedidosituacao)
                                                    <button onclick="editRegister({{$pedidosituacao}})" 
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $pedidosituacao)
                                                    <button onclick="removeRegister({{$pedidosituacao}})"
                                                        id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                            <span class="fa fa-trash fa-lg"></span>
                                                    </button>
                                                @endcan
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div> <!--col-md-12-->
                        </div><!-- /panel-body-->
                    </div><!-- panel panel-default-->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Pedidosituacao::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- col-xs-12 -->
            </div><!-- row -->
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
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" id="metodo" name="_method">
                                    {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id'=>'descricao']) !!}
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12">
                                {!! Form::label('status', 'Status:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-10">
                                    {!! Form::select('status',$status, @$statusdefault, ['class'=>'form-control input-sm selectChosen', 'id'=>'status']) !!}
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12">
                                <label for="ativo" class="col-sm-2 control-label input-sm required">Ativo:</label>
                                <div class="col-sm-1 checkbox">
                                    @if(!str_contains(Request::url(),'edit') && !isset($show))
                                    {{ Form::checkbox('ativo', 1, true, ['id'=>'ativo']) }}
                                    @else 
                                    {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                    @endif
                                </div>
                                {!! Form::label('androidusa', 'Android:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::checkbox('androidusa', 1, null, ['id'=>'androidusa']) }}
                                </div>
                                {!! Form::label('valegas', 'Vale Gás:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::checkbox('valegas', 1, null, ['id'=>'valegas']) }}
                                </div>
                                {!! Form::label('solicitacartaoautorizacao', 'Solicita CV:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::checkbox('solicitacartaoautorizacao', 1, null, ['id'=>'solicitacartaoautorizacao']) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                        {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                        <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <h5><i class="icon fa fa-ban"></i>Erro</h5>
                            <div id="save_result"></div>
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>

        @include('general.modal_del')

        <!--Rota para um novo cadastro via ajax-->
        <div id='rotaStore' class="hidden">{{route('pedidosituacao.store')}}</div>
        <!--Rota para atualizar via ajax-->
        <div id='rotaUpdate' class="hidden">{{url('pedidosituacao')}}/</div>
        <!--Rota para deletar via ajax-->
        <div id='rotaDel' class="hidden">{{url('pedidosituacao')}}/</div>
        <!--Rota para redirecionar via ajax-->
        <div id='rotaIndex' class="hidden">{{route('pedidosituacao.index')}}</div>
    </div><!-- #divCadastro-->
</div><!-- main-content -->
@endsection
