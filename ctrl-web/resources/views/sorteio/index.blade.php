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
                                @can('create', App\Sorteio::class)
                                    <a href="{{ URL::route('sorteio.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Sorteios</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" url="" btnClick="false" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th class="hidden">Cód</th>
                                            <th>Busca Inicio</th>
                                            <th>Busca Final</th>
                                            <th>Data do Sorteio</th>
                                            <th>Apenas APP</th>
                                            <th>Pedido Sorteado</th>
                                            <th>Cliente</th>
                                        </tr>
                                    </thead>
                                    <tbody id="nfcst-list" name="nfcst-list">
                                        @foreach ($sorteios as $sorteio)
                                        <tr id="sorteio{{$sorteio->id}}">
                                            <td class="hidden">{{$sorteio->id}}</td>
                                            <td>{{$sorteio->datainicio}}</td>
                                            <td>{{$sorteio->datafim}}</td>
                                            <td>{{$sorteio->datasorteio}}</td>
                                            <td>{{$sorteio->app ? "Sim" : "Não"}}</td>
                                            <td>{{$sorteio->pedido_id}}</td>
                                            <td>{{$sorteio->nome}}</td>
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
                                @can('create', App\Sorteio::class)
                                    <a href="{{ URL::route('sorteio.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.content-wrapper -->
    </div>
</div>
@endsection
