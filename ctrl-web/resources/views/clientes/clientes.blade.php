
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="row">
                    <div class="col-md-12" style="margin-bottom:1%">
                        <div class="col-md-6">
                            @can('create', App\Cliente::class)
                                <a href="{{ URL::route('cliente.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Clientes/Fornecedores</h3>
                    </div>
                    <div class="panel-body">
                        <i class="col-sm-12" style="margin-left: 23%">Quando o foco estiver em um dos campos pressione CTRL + Espaço para pesquisar!</i>
                        <div class="col-md-12 form-horizontal margTop_20">
                            <div class="col-md-8">
                                {{Form::label('name', 'Nome:', ['class' => 'control-label input-sm col-sm-4'])}}
                                <div class="col-sm-4">
                                    {{Form::text('name', null, ['class' => 'form-control input-sm', 'id' => 'name', 'autofocus'])}}
                                </div>
                                {{Form::label('cod', 'Código:', ['class' => 'control-label input-sm col-sm-1'])}}
                                <div class="col-sm-2">
                                    {{Form::text('cod', null, ['class' => 'form-control input-sm', 'id' => 'cod'])}}
                                </div>
                                <div class="col-sm-1">
                                    <button id="btnFiltro" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Clientes"><span class="fa fa-search fa-lg"></span></button>
                                </div>
                                <hr />
                            </div>
                        </div>
                        <div class="col-md-12 margTop_20">
                            <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                <thead>
                                    <tr>
                                        <th style="width:100px;">C&oacute;digo</th>
                                        <th>Descri&ccedil;&atilde;o</th>
                                        <th>Cliente</th>
                                        <th>Fornecedor</th>
                                        <th>Transportador</th>
                                        <th>Ativo</th>
                                        <th style="width:80px;">Operação</th>
                                    </tr>
                                </thead>
                                <tbody id="clientes-list" name="clientes-list">
                                    @foreach ($clientes as $cliente)
                                    <tr id="cliente{{$cliente->id}}">
                                        <td>{{$cliente->id}}</td>
                                        <td>{{$cliente->nome}}</td>
                                        <td>{{$cliente->cliente == 1 ? 'Sim' : 'Não'}}</td>
                                        <td>{{$cliente->fornecedor == 1 ? 'Sim' : 'Não'}}</td>
                                        <td>{{$cliente->transportador == 1 ? 'Sim' : 'Não'}}</td>
                                        <td>{{$cliente->ativo == 1 ? 'Sim' : 'Não'}}</td>
                                        <td>
                                            @can('view', $cliente)
                                                <button onclick="window.location.href = '{{route('cliente.show',$cliente->id)}}'"
                                                    class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                        <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                            @endcan
                                            @can('update', $cliente)
                                                <button onclick="window.location.href = '{{route('cliente.edit',$cliente->id)}}'"
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
                    </div>
                </div>


                <div class="row">
                    <div class="col-md-12">
                        <div class="col-md-5">
                            @can('create', App\Cliente::class)
                                <a href="{{ URL::route('cliente.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->

    </div><!-- /.content-wrapper -->
</div>
<script src="{{asset('js/clienteIndex.js')}}" type="text/javascript"></script>
@endsection
