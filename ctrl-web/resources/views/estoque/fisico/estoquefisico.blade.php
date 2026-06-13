
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
                                @can('create', App\Estoquefisico::class)
                                    <a href="{{ URL::route('estoquefisico.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Estoque Físico</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                 <thead>
                                    <tr>
                                        <th>Cód.</th>
                                        <th>Data Competencia</th>
                                        <th>Efetivado</th>
                                        <th>Operações</th>
                                    </tr>
                                </thead>
                                <tbody id="estoquefisicos-list" name="estoquefisico-list">
                                    @foreach ($estoquefisico as $estfisico)
                                    <tr id="estfisico{{$estfisico->id}}">
                                        <td>{{$estfisico->id}}</td>
                                        <td>{{requestDataOracle($estfisico->datacompetencia, false)}}</td>
                                        <td>{{$estfisico->efetivado == 1 ? "Sim" : "Não"}}</td>
                                        <td>
                                            @can('view', $estfisico)
                                                <button onclick="window.location.href = '{{route('estoquefisico.show',$estfisico->id)}}'"
                                                    class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                        <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                            @endcan
                                            @can('update', $estfisico)
                                                <button onclick="window.location.href = '{{route('estoquefisico.edit',$estfisico->id)}}'"
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
                            @can('create', App\Estoquefisico::class)
                                <a href="{{ URL::route('estoquefisico.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->
        @include('general.modal_del')
        <!--Rota para deletar via ajax-->
        <div id='rotaDel' class="hidden">{{url('estoquefisico')}}/</div>
        <!--Rota para redirecionar via ajax-->
        <div id='rotaIndex' class="hidden">{{route('estoquefisico.index')}}</div>
    </div><!-- /.content-wrapper -->
</div>
@endsection
