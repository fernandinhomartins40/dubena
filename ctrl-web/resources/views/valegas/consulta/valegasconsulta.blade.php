
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Consulta Vale Gás</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                {{ Form::open(['id'=>'fmFiltros', 'class' => 'form-horizontal', 'method' => 'get']) }}
                                {!! Form::label('codigo', 'Cód. Vale Gás:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                <div class="col-md-2">
                                    {!! Form::text('codigo',null,['class'=>'input-sm form-control', 'id' => 'codigo']) !!}
                                </div>
                                {!! Form::label('situacao', 'Situação:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                <div class="col-md-2">
                                    {!! Form::select('situacao', $situacoes, null,['class'=>'selectChosen', 'id' => 'situacao']) !!}
                                </div>
                                {!! Form::label('cliente_id', 'Cliente:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                <div class="col-md-3">
                                    {!! Form::select('cliente_id', $clientes, null,['class'=>'selectChosen', 'id' => 'cliente_id']) !!}
                                </div>
                                <div class="col-md-2" >
                                    <button class="btn btn-sm btn-nw-buscas" id='btnBusca' type="submit" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                        <span class="fa fa-search fa-lg"></span>
                                    </button>
                                    <a class="btn btn-sm btn-github" type="button" href="{{route('valegasconsulta.index')}}" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                        <span class="fa fa-recycle fa-lg"></span>
                                    </a>
                                </div>
                                {!! Form::close() !!}

                                <div class="col-md-12">

                                    <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTableSemFilter">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Cód. Vale Gás</th>
                                                <th>Data Venda</th>
                                                <th style="max-width: 15%;width: 15%">Baixado/Cancelado</th>
                                                <th>Situação</th>
                                                <th>Cliente</th>
                                            </tr>
                                        </thead>
                                        <tbody id="" name="estoquerequisicao-list">
                                            @foreach ($valegasitens as $valegas)
                                            <tr id="estoquerequisicao{{$valegas->id}}">
                                                <td class='conteudoTd'>{{$valegas->id}}</td>
                                                <td class='conteudoTd'>{{$valegas->codigo}}</td>
                                                <td class='conteudoTd'>{{requestDataOracle($valegas->valegasvenda->datavenda, false)}}</td>
                                                <td class='conteudoTd'>{{requestDataOracle($valegas->databaixa, false)}}</td>
                                                <td class='conteudoTd'>{{$valegas->valeGasSituacao->descricao}}</td>
                                                <td class='conteudoTd'>{{$valegas->cliente->nome}}</td>
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
    </div>
</div>
@endsection
