
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
                                @can('create', App\Condicaopagamento::class)
                                    <a href="{{ URL::route('condicaopagamento.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Condições de Pagamento</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Tipo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Dias Primeira</th>
                                            <th>Nº Parcelas</th>
                                            <th>Intervalo Parcelas</th>
                                            <th>Taxa</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="condicaopagamento-list" name="condicaopagamento-list">
                                        @foreach ($condicaopagamentos as $condicaopagamento)
                                        <tr id="condicaopagamento{{$condicaopagamento->id}}">
                                            <td>{{$condicaopagamento->id}}</td>
                                            @if ($condicaopagamento->tipo === '0')
                                            <td>À Vista - Outros</td>
                                            @elseif ($condicaopagamento->tipo === '1')
                                            <td>À Prazo - Outros</td>
                                            @elseif ($condicaopagamento->tipo === '2')
                                            <td>À Vista - Cartão</td>
                                            @elseif ($condicaopagamento->tipo === '3')
                                            <td>À Prazo - Cartão</td>
                                            @elseif ($condicaopagamento->tipo === '4')
                                            <td>Convênio</td>
                                            @else
                                            <td>Gás de Bolso</td>
                                            @endif
                                            <td>{{$condicaopagamento->descricao}}</td>
                                            <td>{{$condicaopagamento->dias_primeira}}</td>
                                            <td>{{$condicaopagamento->num_parcelas}}</td>
                                            <td>{{$condicaopagamento->intervalo}}</td>
                                            <td>{{$condicaopagamento->taxa !== null ? requestPercentualOracle($condicaopagamento->taxa): ''}}</td>
                                            <td>{{$condicaopagamento->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                @can('view', $condicaopagamento)
                                                    <button onclick="window.location.href = '{{route('condicaopagamento.show',$condicaopagamento->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $condicaopagamento)
                                                    <button onclick="window.location.href = '{{route('condicaopagamento.edit',$condicaopagamento->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $condicaopagamento)
                                                    <button onclick="removeRegister({{$condicaopagamento}})"
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
                                @can('create', App\Condicaopagamento::class)
                                    <a href="{{ URL::route('condicaopagamento.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            
            @include('general.modal_del')
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('condicaopagamento')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('condicaopagamento.index')}}</div>

        </div><!-- /.content-wrapper -->
    </div>
    @endsection
