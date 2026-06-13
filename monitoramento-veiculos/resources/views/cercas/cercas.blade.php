
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
                                <a href="{{ URL::route('cerca.create') }}" class="btn btn-nw-registro">Novo Cadastro</a>
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Cercas</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                     <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descrição</th>
                                            <th>Setor</th>
                                            <th>Cor</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cercas-list" name="cercas-list">
                                            {{-- {{dd($Cercas)}} --}}
                                        @if (isset($Cercas) && count($Cercas) > 0)
                                            @foreach ($Cercas as $cerca)
                                            <tr id="cerca{{$cerca->id}}">
                                                <td>{{$cerca->id}}</td>
                                                <td>{{$cerca->descricao}}</td>
                                                <td>{{$cerca->setor_id ? $cerca->setor->descricao : ""}}</td>
                                                <td style="color: {{$cerca->cor}}">{{$cerca->cor}}</td>
                                                <td>{{$cerca->ativo == 1 ? 'Sim' : 'Não' }}</td>
                                                <td>
                                                    <button onclick="window.location.href = '{{route('cerca.show',$cerca->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                    <button onclick="window.location.href = '{{route('cerca.edit',$cerca->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                            <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                    <button onclick="removeRegister({{$cerca}})"
                                                        id="btnRemover" class='btn btn-nw-registro btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover">
                                                            <span class="fa fa-trash fa-lg"></span>
                                                    </button>
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
                                <a href="{{ URL::route('cerca.create') }}" class="btn btn-nw-registro">Novo Cadastro</a>
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->
            @include('general.modal_del')
            <!--Rota para deletar via ajax-->
            <div id='rotaDel' class="hidden">{{url('cerca')}}/</div>
            <div id='rotaIndex' class="hidden">{{route('cerca.index')}}</div>
        </div><!-- /.content-wrapper -->
    </div>
    
    @endsection
