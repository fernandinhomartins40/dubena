
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
                                @can('create', App\Colaboradorcomissao::class)
                                    <a href="{{ URL::route('colaboradorcomissoes.create') }}" type="button" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Comissões</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12 ">
                                {{ Form::open(['id'=>'fmFiltros', 'class' => 'form-horizontal']) }}
                                <div class="col-md-12">
                                    {!! Form::label('setor', 'Setor:', ['class'=>'col-sm-1 control-label input-sm',]) !!}
                                    <div class="col-md-3 ">
                                        {!! Form::select('setor_id',$setores, $setor,['class'=>'selectChosen', 'id' => 'setor_id']) !!}
                                    </div>
                                </div>
                                <div class="col-md-12" style="margin-top: 8px;">
                                    {!! Form::label('colaborador_id', 'Colaborador:', ['class'=>'col-sm-1 control-label input-sm',]) !!}
                                    <div class="col-md-3 ">
                                        {!! Form::hidden('hiddencolaborador_id', $colaborador,['class'=>'', 'id' => 'hiddencolaborador_id']) !!}
                                        {!! Form::select('colaborador_id', $colaboradores, null,['class'=>'selectChosen', 'id' => 'colaborador_id']) !!}
                                    </div>

                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-nw-buscas" id='btnBusca' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                            <span class="fa fa-search fa-lg"></span>
                                        </button>
                                        <button class="btn btn-sm btn-github" id='btnZeraFiltro' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                            <span class="fa fa-recycle fa-lg"></span>
                                        </button>
                                    </div>
                                </div>
                                {!! Form::close() !!}

                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>Cód</th>
                                            <th>Colaborador</th>
                                            <th>Condição de Pagamento</th>
                                            <th>Produto</th>
                                            <th>Setor</th>
                                            <th>Percentual/Valor retido</th>
                                            <th>Início</th>
                                            <th>Fim</th>
                                            <th>Ativo</th>
                                            <th style="width:80px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="colaboradorcomissoes-list" name="colaboradorcomissoes-list">
                                        @foreach ($colaboradorcomissoes as $colaboradorcomissao)
                                        <tr id="colaboradorcomissao{{$colaboradorcomissao->id}}">
                                            <td>{{$colaboradorcomissao->id}}</td>
                                            <td>{{$colaboradorcomissao->colaborador->nome}}</td>
                                            <td>{{$colaboradorcomissao->condicaopagamento?$colaboradorcomissao->condicaopagamento->descricao:""}}</td>
                                            <td>{{$colaboradorcomissao->produto->descricao}}</td>
                                            <td>{{$colaboradorcomissao->setor?$colaboradorcomissao->setor->descricao:""}}</td>
                                            @if ($colaboradorcomissao->percentual == 0)
                                            <td>{{requestNumeroDecimalOracle($colaboradorcomissao->empresavalor)}}</td>
                                            @else
                                            <td>{{requestPercentualOracle($colaboradorcomissao->percentual)}}</td>
                                            @endif
                                            <td>{{requestDataOracle($colaboradorcomissao->datainicio, false)}}</td>
                                            <td>{{requestDataOracle($colaboradorcomissao->datafim, false)}}</td>
                                            <td>{{$colaboradorcomissao->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                @can('view', $colaboradorcomissao)
                                                <button onclick="window.location.href = '{{route('colaboradorcomissoes.show',$colaboradorcomissao->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                    <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                                @endcan
                                                @can('update', $colaboradorcomissao)
                                                <button onclick="window.location.href = '{{route('colaboradorcomissoes.edit',$colaboradorcomissao->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                    <span class="fa fa-pencil-square-o fa-lg"></span>
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
                                @can('create', App\Colaboradorcomissao::class)
                                    <a href="{{ URL::route('colaboradorcomissoes.create') }}" type="button" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
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
                                    {!! Form::label('codigo', 'Cód Comissão:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
                                        <input type="hidden" id="id_del" name="id">
                                        {!! Form::text('codigo',null,['class'=>'form-control input-sm', 'id'=>'codigo_del']) !!}
                                    </div>
                                </div>
                                <div class="form-group crud_space col-sm-12">
                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-9">
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
            <script type="text/javascript">
                urlBuscaIndex = '{{ url("colaboradorcomissoes?setor_id=:setor_idcolaborador_id=:colaborador_id") }}';
                urlBuscaColaboradoresPorSetor = '{{url("colaborador/buscaColaboradorPorSetor/:setor_id")}}';
                $(".btnNovoCadastro").on('click', function() {
                window.location.href = root + '/colaboradorcomissoes/create?redirect=' + window.location.href;
                })
            </script>
            <script src="{{URL::to('js/colaboradorcomissao.js')}}"></script>
        </div><!-- /.content-wrapper -->
    </div>
    @endsection
