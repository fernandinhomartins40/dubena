
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
                                @can('create', App\Setor::class)
                                    <a href="{{ URL::route('setor.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Setores</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="setores-list" name="setores-list">
                                        @foreach ($setores as $setor)
                                        <tr id="setor{{$setor->id}}">
                                            <td>{{$setor->id}}</td>
                                            <td>{{$setor->descricao}}</td>
                                            <td>{{$setor->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                @can('view', $setor)
                                                    <button onclick="window.location.href = '{{route('setor.show',$setor->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $setor)
                                                    <button onclick="window.location.href = '{{route('setor.edit',$setor->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $setor)
                                                    <button onclick="removeRegister({{$setor}})"
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
                                @can('create', App\Setor::class)
                                    <a href="{{ URL::route('setor.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            
            @include('general.modal_del')
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('setor')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('setor.index')}}</div>

            <!-- page script -->
            <script type="text/javascript">

            </script>
        </div><!-- /.content-wrapper -->
    </div>
    @endsection
