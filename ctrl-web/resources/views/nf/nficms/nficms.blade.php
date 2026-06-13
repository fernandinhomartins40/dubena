
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
                                @can('create', App\Nficms::class)
                                <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Cst/Csosn ICMS</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" url="" btnClick="false" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th class="hidden">Id</th>
                                            <th style="width:125px;">C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="nficms-list" name="nficms-list">
                                        @foreach ($nficms as $nficms)
                                        <tr id="nficms{{$nficms->id}}">
                                            <td class="hidden">{{$nficms->id}}</td>
                                            <td>{{$nficms->codigo}}</td>
                                            <td>{{$nficms->descricao}}</td>
                                            <td>
                                                @can('view', $nficms)
                                                <button onclick="viewRegister({{$nficms}})" 
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                    <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                                @endcan
                                                @can('update', $nficms)
                                                <button onclick="editRegister({{$nficms}})" 
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                    <span class="fa fa-pencil-square-o fa-lg"></span>
                                                </button>
                                                @endcan
                                                @can('delete', $nficms)
                                                <button onclick="removeRegister({{$nficms}})"
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
                                @can('create', App\Nficms::class)
                                <button type="button" class="btn btn-nw-registro btnNovoCadastro" data-toggle="modal" data-target="#myModal">Novo Registro</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->    
            @include('nf.partials.impostos_modals')
            <meta name="csrf-token" content="{{ csrf_token() }}" />
            <!--Rota para um novo cadastro via ajax-->
            <div id='rotaStore' class="hidden">{{route('nficms.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('nficms')}}/</div>
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('nficms')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('nficms.index')}}</div>
            <!--Rota para a linguagem do plugin de paginação-->
            <div id='urlLanguage' class="hidden">{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}</div>
        </div><!-- /.content-wrapper -->
    </div>
</div>
@endsection
