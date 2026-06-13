
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
                                <a href="{{ URL::route('agencia.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Agências</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                 <table id="tblCadastro" urlupdate="{{ route("agencia.edit", ":id") }}" url="{{ route("agencia.show", ":id") }}" btnClick="false" class="table table-bordered table-hover table-condensed dataTable">
                                   <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descri&ccedil;&atilde;o</th>
                                            <th>Banco</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="agencias-list" name="agencias-list">
                                        @foreach ($agencias as $agencia)
                                        <tr id="agencia{{$agencia->id}}">
                                            <td>{{$agencia->id}}</td>
                                            <td>{{$agencia->descricao}}</td>
                                            <td>{{$agencia->banco->descricao}}</td>
                                            <td>{{$agencia->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                <button class='btn btn-nw-geral btn-xs' id="btnEditar">Editar</button>
                                                <button class='btn btn-nw-registro btn-xs' id="btnRemover">Remover</button>
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
                                <a href="{{ URL::route('agencia.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            @include('general.modal_del')
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('agencia')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('agencia.index')}}</div>
        </div><!-- /.content-wrapper -->
    </div>
    @endsection
