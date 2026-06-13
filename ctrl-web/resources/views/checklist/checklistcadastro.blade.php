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
                                @can('create', App\Checklistform::class)
                                    <a href="{{ URL::route('cadastrochecklist.create') }}" class="btn btn-nw-registro btnNw">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Cadastros de Checklists</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>id</th>
                                            <th>Descrição</th>
                                            <th>Data Início</th>
                                            <th>Data Fim</th>
                                            <th>Ativo</th>
                                            <th>Operações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="agencias-list" name="agencias-list">
                                        @foreach ($checklists as $check)
                                        <tr id="abastecimento{{$check->id}}">
                                            <td>{{$check->id}}</td>
                                            <td>{{$check->descricao }}</td>
                                            <td>{{requestDataOracle($check->datainicio, false)}}</td>
                                            <td>{{requestDataOracle($check->datafim, false)}}</td>
                                            <td>{{$check->ativo === "1" ? "Sim" : "Não"}}</td>
                                            <td>
                                                @can('view', $check)
                                                    <button onclick="window.location.href = '{{route('cadastrochecklist.show',$check->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $check)
                                                    <button onclick="window.location.href = '{{route('cadastrochecklist.edit',$check->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $check)
                                                    <button onclick="removeRegister({{$check}})"
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
                                @can('create', App\Checklistform::class)
                                    <a href="{{ URL::route('cadastrochecklist.create') }}" class="btn btn-nw-registro btnNw">Novo Registro</a>
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
<div id='rotaDel' class="hidden">{{url('cadastrochecklist')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('cadastrochecklist.index')}}</div>

@endsection
