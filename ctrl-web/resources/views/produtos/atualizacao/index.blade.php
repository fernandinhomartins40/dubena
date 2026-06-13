
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
                                @can('create', App\Atualizacaoprecos::class)
                                    <a href="{{ URL::route('atualizarprecos.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div> <!--col-md-6-->
                        </div> <!--col-md-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Atualização de Preços</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                     <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Descrição</th>
                                            <th>Produto</th>
                                            <th>Tipo</th>
                                            <th>Variação</th>
                                            <th>Valor</th>
                                            <th>Alterou Produto</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($atualizacoes) && count($atualizacoes) > 0)
                                            @foreach($atualizacoes as $att)
                                                <tr>
                                                    <td>{{$att->id}}</td>
                                                    <td>{{$att->descricao}}</td>
                                                    <td>{{$att->produto}}</td>
                                                    <td>
                                                        @if($att->tipo == '1')
                                                            <!-- {{$valor = requestNumeroDecimalOracle($att->valor)}} -->
                                                            Preço Unitário
                                                        @elseif($att->tipo == '2')
                                                            <!-- {{$valor = requestNumeroDecimalOracle($att->valor)}} -->
                                                            Valor
                                                        @else
                                                            <!-- {{$valor = requestPercentualOracle($att->valor)}} -->
                                                            Percentual
                                                        @endif
                                                    </td>
                                                    <td>{{$att->variacao == 'A' ? 'Aumentou' : 'Diminuiu'}}</td>
                                                    <td>{{$valor}}</td>
                                                    <td>{{$att->mudoubase == 0 ? 'Não' : 'Sim'}}</td>
                                                    <td>
                                                        @can('view', $att)
                                                            <button onclick="window.location.href = '{{route('atualizarprecos.show',$att->id)}}'"
                                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                    <span class="fa fa-eye fa-lg"></span>
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
                                @can('create', App\Atualizacaoprecos::class)
                                    <a href="{{ URL::route('atualizarprecos.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div><!-- /.row -->
                </div><!-- /.box-header -->
            </div>
        </div>
    </div>
</div>
@endsection
