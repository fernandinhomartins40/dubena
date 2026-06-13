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
                                @can('create', App\Promocao::class)
                                    <a href="{{ URL::route('promocao.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div><!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Promoções</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;">C&oacute;d</th>
                                            <th style="width:400px;">Descri&ccedil;&atilde;o</th>
                                            <th>Produto</th>
                                            <th>Prêmio</th>
                                            <th style="width:100px;">Início</th>
                                            <th style="width:100px;">Fim</th>
                                            <th style="width:40px;">Ativo</th>
                                            <th style="width:130px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="promocaos-list" name="promocaos-list">
                                        @foreach ($promocoes as $promocao)
                                        <tr id="promocao{{$promocao->id}}">
                                            <td>{{$promocao->id}}</td>
                                            <td>{{$promocao->descricao}}</td>
                                            <td id="produto">{{@$promocao->produto->descricao}}</td>
                                            <td>{{@$promocao->premio->descricao}}</td>
                                            <td>{{date("d/m/y - H:i", strtotime($promocao->datahorainicio))}}</td>
                                            <td>{{date("d/m/y - H:i", strtotime($promocao->datahorafim))}}</td>
                                            <td>{{$promocao->ativo == 1 ? "Sim" : "Não"}}</td>
                                            <th>
                                                @can('view', $promocao)
                                                    <button onclick="window.location.href = '{{route('promocao.show',$promocao->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $promocao)
                                                    <button onclick="window.location.href = '{{route('promocao.edit',$promocao->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('delete', $promocao)
                                                    <button onclick="removeRegister({{$promocao}})"
                                                        id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                                <span class="fa fa-trash fa-lg"></span>
                                                    </button>
                                                @endcan
                                            </th>
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
                                @can('create', App\Promocao::class)
                                    <a href="{{ URL::route('promocao.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div><!-- /.col -->
                    </div><!-- /.row -->
                    <meta name="csrf-token" content="{{ csrf_token() }}" />
                </div><!-- #divCadastro -->
                @include('general.modal_del')
                <!--Rota para deletar via ajax-->
                <div id='rotaDel' class="hidden">{{url('promocao')}}/</div>
                <!--Rota para redirecionar via ajax-->
                <div id='rotaIndex' class="hidden">{{route('promocao.index')}}</div>

            </div><!-- .row -->
        </div><!-- #mainContent -->
        @endsection
