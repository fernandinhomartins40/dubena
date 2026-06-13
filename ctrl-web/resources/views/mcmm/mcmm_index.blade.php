
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
                                @can('create', App\Mcmm::class)
                                    <a href="{{ URL::route('mcmm.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Mapa de Controle de Movimento Mensal</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                     <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Movimento Mês/Ano</th>
                                            <th>Responsável</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="mcmms-list" name="mcmms-list">
                                        @foreach ($mcmm as $mcmm)
                                        <tr id="mcmm{{$mcmm->id}}">
                                            <td>{{$mcmm->id}}</td>
                                            <td>{{requestDataOracle($mcmm->datamovimento, false)}}</td>
                                            <td>{{$mcmm->responsavel}}</td>
                                            <td>
                                                @can('view', $mcmm)
                                                    <button onclick="window.location.href = '{{URL::to('mcmm') . '/' . $mcmm->id}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $mcmm)
                                                    <button onclick="window.location.href = '{{URL::to('mcmm') . '/' . $mcmm->id . '/edit'}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $mcmm)
                                                    <!-- {{$mcmm->descricao = requestDataOracle($mcmm->datamovimento, false)}} -->
                                                    <button onclick="removeRegister({{$mcmm}})"
                                                        id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                            <span class="fa fa-trash fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('view', $mcmm)
                                                    <!-- {{$mcmm->descricao = requestDataOracle($mcmm->datamovimento, false)}} -->
                                                    <button onclick="window.open('{{URL::to('mcmm.print') . '?id=' . $mcmm->id}}', '_blank')"
                                                                id="btnPrintDoc" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Imprimir Documento">
                                                            <span class="fa fa-file-pdf-o fa-lg"></span>
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
                                @can('create', App\Mcmm::class)
                                    <a href="{{ URL::route('mcmm.create') }}" class="btn btn-nw-registro">Novo Registro</a>
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
<div id='rotaDel' class="hidden">{{url('mcmm')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('mcmm.index')}}</div>
@include('general.modal_del')
@endsection
