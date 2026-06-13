
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
                                @can('create', App\Inventario::class)
                                    <a href="{{ URL::route('inventario.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Controle de Inventário</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                     <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Data</th>
                                            <th>Mês Entrega</th>
                                            <th>Valor Inventário</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($inventario) && count($inventario) > 0)
                                            @foreach($inventario as $inv)
                                                <tr>
                                                    <td>{{$inv->id}}</td>
                                                    <td>{{$inv->descricao}}</td>
                                                    <td>{{$inv->mesentrega}}</td>
                                                    <td>{{requestNumeroDecimalOracle($inv->valorinventario)}}</td>
                                                    <td>
                                                        @can('view', $inv)
                                                            <button onclick="window.location.href = '{{route('inventario.show',$inv->id)}}'"
                                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                    <span class="fa fa-eye fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                        @can('update', $inv)
                                                            <button onclick="window.location.href = '{{route('inventario.edit',$inv->id)}}'"
                                                                class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                        <span class="fa fa-pencil-square-o fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                        @can('delete', $inv)
                                                            <button onclick="removeRegister({{$inv}})"
                                                                id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                                        <span class="fa fa-trash fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Inventario::class)
                                    <a href="{{ URL::route('inventario.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div><!-- /.row -->
                </div><!-- /.box-header -->
            </div>
        </div>
    </div>
</div>
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('inventario')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('inventario.index')}}</div>
@include('general.modal_del')
@endsection
