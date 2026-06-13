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
                                @can('create', App\Posvenda::class)
                                    <a href="{{ URL::route('posvendacadastro.create') }}" class="btn btn-nw-registro btnNw">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Cadastros de Pós-Venda</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Id</th>
                                            <th>Descrição</th>
                                            <th>Data Inicio</th>
                                            <th>Data Fim</th>
                                            <th>Ativo</th>
                                            <th>Operações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="agencias-list" name="agencias-list">
                                        @foreach ($posvendas as $posvenda)
                                        <tr id="posvenda{{$posvenda->id}}">
                                            <td>{{$posvenda->id}}</td>
                                            <td>{{$posvenda->descricao }}</td>
                                            <td>{{requestDataOracle($posvenda->datahorainicio, true)}}</td>
                                            <td>{{requestDataOracle($posvenda->datahorafim, true)}}</td>
                                            <td>{{$posvenda->ativo === "1" ? "Sim" : "Não"}}</td>
                                            <td>
                                                @can('view', $posvenda)
                                                    <button onclick="window.location.href = '{{route('posvendacadastro.show',$posvenda->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $posvenda)
                                                    <button onclick="window.location.href = '{{route('posvendacadastro.edit',$posvenda->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $posvenda)
                                                    <button onclick="removeRegister({{$posvenda}})"
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
                                @can('create', App\Posvenda::class)
                                    <a href="{{ URL::route('posvendacadastro.create') }}" class="btn btn-nw-registro btnNw">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div>
    </div>
</div>
@include('general.modal_del')
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('posvendacadastro')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('posvendacadastro.index')}}</div>

@endsection
