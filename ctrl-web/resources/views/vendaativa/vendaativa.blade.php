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
                                @can('create', App\Vendaativa::class)
                                    <a href="{{ URL::route('vendaativa.create') }}" class="btn btn-nw-registro btnNw">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Cadastros de Vendas Ativa</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="dataTable table table-bordered table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Id</th>
                                            <th>Descrição</th>
                                            <th>Data Hora</th>
                                            <th>Ativo</th>
                                            <th>Operações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="agencias-list" name="agencias-list">
                                        @if(isset($vendaativa))
                                            @foreach($vendaativa as $ativa)
                                                <tr>
                                                    <td>{{$ativa->id}}</td>
                                                    <td>{{$ativa->descricaofiltro}}</td>
                                                    <td>{{requestDataOracle($ativa->datahora)}}</td>
                                                    <td>{{$ativa->ativo == 1 ? "Sim" : "Não"}}</td>
                                                    <td>
                                                        @can('work', $ativa)
                                                            <button onclick="window.location.href = '{{route('vendaativa.show',$ativa->id)}}'"
                                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Operar">
                                                                    <span class="fa fa-cog fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                        @can('delete', $ativa)
                                                            <button onclick="removeRegister({{$ativa}})"
                                                                id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                                        <span class="fa fa-trash fa-lg"></span>
                                                            </button>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Vendaativa::class)
                                    <a href="{{ URL::route('vendaativa.create') }}" class="btn btn-nw-registro btnNw">Novo Registro</a>
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
<div id='rotaDel' class="hidden">{{url('vendaativa')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{route('vendaativa.index')}}</div>

@endsection
