
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
                                @can('create', App\Empresabem::class)
                                    <a href="{{ URL::route('empresabens.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Bens</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Nº de Série</th>
                                            <th>Valor Original</th>
                                            <th>Valor Atual</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bens-list" name="bens-list">
                                        @foreach ($bens as $empresabens)
                                        <tr id="empresabens{{$empresabens->id}}">
                                            <td>{{$empresabens->id}}</td>
                                            <td>{{$empresabens->descricao}}</td>
                                            <td>{{$empresabens->numeroserie}}</td>
                                            <td>{{requestNumeroDecimalOracle($empresabens->valororiginal)}}</td>
                                            <td>{{requestNumeroDecimalOracle($empresabens->valoratual)}}</td>
                                            <td>{{$empresabens->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                @can('view', $empresabens)
                                                    <button onclick="window.location.href = '{{route('empresabens.show',$empresabens->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $empresabens)
                                                    <button onclick="window.location.href = '{{route('empresabens.edit',$empresabens->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $empresabens)
                                                    <button onclick="removeRegister({{$empresabens}})"
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
                                @can('create', App\Empresabem::class)
                                    <a href="{{ URL::route('empresabens.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            
            @include('general.modal_del')
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('empresabens')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('empresabens.index')}}</div>

        </div><!-- /.content-wrapper -->
    </div>
    @endsection
