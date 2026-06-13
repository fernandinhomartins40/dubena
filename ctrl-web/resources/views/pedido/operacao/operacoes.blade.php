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
                                @can('create', App\Pedidooperacao::class)
                                    <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Operações de Pedidos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" url="" btnClick="false" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Tipo</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="-list" name="pedidooperacoes-list">
                                        @foreach ($pedidooperacoes as $pedidooperacao)
                                        <tr id="pedidooperacao{{$pedidooperacao->id}}">
                                            <td>{{$pedidooperacao->id}}</td>
                                            <td>{{$pedidooperacao->descricao}}</td>

                                            @if($pedidooperacao->convenio != 0 && $pedidooperacao->convenio != '0') 
                                            <td>Convênio</td>
                                            <!-- {{$pedidooperacao->status = 0 }} -->
                                            
                                            @elseif ($pedidooperacao->disk != 0 && $pedidooperacao->disk != '0')
                                            <td>Disk</td>
                                            <!-- {{$pedidooperacao->status = 1 }} -->

                                            @elseif ($pedidooperacao->gasbolso != 0 && $pedidooperacao->gasbolso != '0')
                                            <td>Vale Gás</td>
                                            <!-- {{$pedidooperacao->status = 2 }} -->

                                            @elseif ($pedidooperacao->pdv != 0 && $pedidooperacao->pdv != '0')
                                            <td>PDV</td>
                                            <!-- {{$pedidooperacao->status = 3 }} -->

                                            @elseif ($pedidooperacao->vendadireta != 0 && $pedidooperacao->vendadireta != '0')
                                            <td>Venda Direta</td>
                                            <!-- {{$pedidooperacao->status = 4 }} -->
                                            @endif

                                            <td>{{$pedidooperacao->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                @can('view', $pedidooperacao)
                                                    <button onclick="viewRegister({{$pedidooperacao}})" 
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $pedidooperacao)
                                                    <button onclick="editRegister({{$pedidooperacao}})" 
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $pedidooperacao)
                                                    <button onclick="removeRegister({{$pedidooperacao}})"
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
                                @can('create', App\Pedidooperacao::class)
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
                                <div class="col-sm-2 checkbox">
                                    @if(!str_contains(Request::url(),'edit') && !isset($show))
                                    {{ Form::checkbox('ativo', 1, true, ['id'=>'ativo']) }}
                                    @else 
                                    {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                    @endif
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12">
                                <label for="portaria" class="col-sm-2 control-label input-sm required">Portaria:</label>
                                <div class="col-sm-2 checkbox">
                                    {{ Form::checkbox('portaria', 1, null, ['id'=>'portaria']) }}
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
        <div id='rotaStore' class="hidden">{{route('pedidooperacao.store')}}</div>
        <!--Rota para atualizar via ajax-->
        <div id='rotaUpdate' class="hidden">{{url('pedidooperacao')}}/</div>
        <!--Rota para deletar via ajax-->
        <div id='rotaDel' class="hidden">{{url('pedidooperacao')}}/</div>
        <!--Rota para redirecionar via ajax-->
        <div id='rotaIndex' class="hidden">{{route('pedidooperacao.index')}}</div>
    </div><!-- #divCadastro-->
</div><!-- main-content -->
@endsection
