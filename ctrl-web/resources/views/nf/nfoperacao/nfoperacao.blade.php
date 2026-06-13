
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
                                @can('create', App\Nfoperacao::class)
                                    <a id="btnNovo" class="btn btn-nw-registro" href="{{ URL::route('nfoperacao.create') }}">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Operações NFe</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th style="width:125px;">C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>CFOP</th>
                                            <th>Tipo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="nfoperacao-list" name="nfoperacao-list">
                                        @foreach ($nfoperacao as $nfoperacao)
                                        <tr id="nfoperacao{{$nfoperacao->id}}">
                                            <td>{{$nfoperacao->id}}</td>
                                            <td>{{$nfoperacao->descricao}}</td>
                                            <td>{{$nfoperacao->cfop}}</td>
                                            <td>{{$nfoperacao->tiponf == 0 ? 'Entrada' : "Saída"}}</td>
                                            <td>
                                                @can('view', $nfoperacao)
                                                    <button onclick="window.location.href = '{{route('nfoperacao.show',$nfoperacao->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $nfoperacao)
                                                    <button onclick="window.location.href = '{{route('nfoperacao.edit',$nfoperacao->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $nfoperacao)
                                                    <button onclick="removeRegister({{$nfoperacao}})"
                                                        id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                                <span class="fa fa-trash fa-lg"></span>
                                                    </button>
                                                @endcan
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <div id='rotaDel' class="hidden">{{url('nfoperacao')}}/</div>
                                    <!--Rota para redirecionar via ajax-->
                                    <div id='rotaIndex' class="hidden">{{route('nfoperacao.index')}}</div>
                                    <!--Rota para a linguagem do plugin de paginação-->

                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->

                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Nfoperacao::class)
                                    <a id="btnNovo" class="btn btn-nw-registro" href="{{ URL::route('nfoperacao.create') }}">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->

            @include('general.modal_del')

        </div><!-- /.content-wrapper -->
    </div>
</div>
@endsection
