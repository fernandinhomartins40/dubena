
@extends('monitora.layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Empresas</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                     <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Nome Informal</th>
                                            <th>Ativo</th>
                                            <th style="width:150px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="empresas-list" name="empresas-list">
                                        @foreach ($Empresas as $empresa)
                                        <tr id="empresa{{$empresa->id}}">
                                            <td>{{$empresa->id}}</td>
                                            <td>{{$empresa->nome_informal}}</td>
                                            <td>{{$empresa->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                            <td>
                                                <button onclick="window.location.href = '{{route('monitora.empresa.show',$empresa->id)}}'"
                                                    class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                        <span class="fa fa-eye fa-lg"></span>
                                                </button>
                                                <button onclick="window.location.href = '{{route('monitora.empresa.edit',$empresa->id)}}'"
                                                    class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                        <span class="fa fa-pencil-square-o fa-lg"></span>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                </div><!-- /.col -->
            </div><!-- /.row -->

        </div><!-- /.content-wrapper -->
    </div>
    @endsection
