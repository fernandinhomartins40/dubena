@extends('layouts.mainmenu') 

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($veiculoentradasaidas))
            {{ Form::model($veiculoentradasaidas, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('veiculoentradasaida.update', $veiculoentradasaidas->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'veiculoentradasaida.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Movimentação do Veículo</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Movimentações</a></li>
                            <li><a href="#tab_2" data-toggle="tab">Pedido Realizado</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('entradasaida', 'Movimentação:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    <div class="col-sm-10">
                                                        {{ Form::label('entrada', 'Entrada', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                        <div class="col-sm-1 checkbox">
                                                            {{ Form::radio('entradasaida', '1', true,['id'=>'entrada']) }}
                                                        </div>
                                                        {!! Form::label('saida', 'Saída', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                        <div class="col-sm-1 checkbox">
                                                            {{ Form::radio('entradasaida', '0', false,['id'=>'saida']) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('veiculo_id', 'Veículo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {!! Form::select('veiculo_id', $veiculos, null, ['id'=>'veiculo_id', 'class' => 'form-control selectChosen'])!!}
                                                </div>
                                                {!! Form::label('datahora', 'Data Atual:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {!! Form::datetime('datahora',null,['id'=>'datahora','class'=>'form-control input-sm generalDateTimePicker']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('ultimadatahora', 'Última Data:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {!! Form::datetime('ultimadatahora',null,['id'=>'ultimadatahora','class'=>'form-control input-sm generalDateTimePicker','readonly']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('km', 'Km Atual:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('km',null,['id'=>'km','class'=>'form-control input-sm number']) !!}
                                                    {!! Form::hidden('entrada_hd',null,['id'=>'entrada_hd','class'=>'form-control input-sm']) !!}
                                                    {!! Form::hidden('saida_hd',null,['id'=>'saida_hd','class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('ultimokm', 'Último Km:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('ultimokm',null,['id'=>'ultimokm','class'=>'form-control input-sm','readonly']) !!}
                                                </div>
                                                {!! Form::label('kmrodado', 'Rodou km:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('kmrodado',null,['id'=>'kmrodado','class'=>'form-control input-sm','readonly']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('temporodado', 'Tempo Rodando:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('temporodado',null,['id'=>'temporodado','class'=>'form-control input-sm','readonly']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('observacoes', 'Observações:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-8">
                                                    {{ Form::textarea('observacoes', null, ['id' => 'observacoes', 'class'=>'form-control input-sm', 'rows' => '4']) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- /.tab-pane -->
                            </div>
                            <!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_2">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space" id="div-filtros">
                                                {!! Form::label('datainicio', 'Data início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {!! Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('datafim', 'Data final:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {!! Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::select('setor_id', $setores, null, ['id'=>'setor_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;'])!!}
                                                </div>
                                                <div class="col-md-1">
                                                    <button class="btn btn-sm btn-nw-buscas" id="btnFiltroPedidos" type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                                        <span class="fa fa-search fa-lg"></span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" id="div-table">
                                                <div class="col-md-11 col-md-push-1">
                                                    <table id="tblPedidosSetor" class="table table-bordered table-striped table-hover table-condensed" style="max-height:300px;max-width:1100px">
                                                        <thead>
                                                            <tr>
                                                                <th>Cód. Pedido</th>
                                                                <th>Status</th>
                                                                <th>Cliente</th>
                                                                <th>Endereço</th>
                                                                <th>Valor Pedido</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <hr id="hr-botoes" />
                                            <div class="form-group crud_space" id="div-botoes">
                                                <div class="col-md-4 col-md-push-5">
                                                    <button class="btn btn-nw-buscas btn-sm" type='button' id='btnVincularPedidos'>Adicionar Selecionados</button> 
                                                    <button class="btn btn-nw-buscas btn-sm" type='button' id='btnRemoverSelecionados'>Remover Selecionados</button> 
                                                </div>                                                
                                            </div>
                                            <hr id="hr-botoes-after"/>
                                            <div class="form-group crud_space" >
                                                <div class="col-md-3 col-md-push-1">
                                                    <h1 class="panel-title ">Vinculados</h1>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::hidden('tblvincpedidos_hd',@$pedidos,['id'=>'tblvincpedidos_hd','class'=>'form-control input-sm']) !!}
                                                <div class="col-md-11 col-md-push-1">
                                                    <table id="tblPedidosVinculados" class="table table-bordered table-striped table-hover table-condensed" style="max-height:300px;max-width:1300px;">
                                                        <thead style="text-align:center;">
                                                            <tr>
                                                                <th>Cód. Pedido</th>
                                                                <th>Status</th>
                                                                <th>Cliente</th>
                                                                <th>Endereço</th>
                                                                <th>Valor Pedido</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if(isset($pedidos))
                                                            @foreach($pedidos as $pedido)
                                                                <tr>
                                                                    <td>{{$pedido->pedido_id}}</td>
                                                                    <td>{{$pedido->status}}</td>
                                                                    <td>{{$pedido->cliente}}</td>
                                                                    <td>{{$pedido->rua}}</td>
                                                                    <td>{{requestNumeroDecimalOracle($pedido->valor)}}</td>
                                                                </tr>
                                                            @endforeach
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- /.tab-pane -->
                            </div>
                            <!-- /.tab-pane -->
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['id'=>'gravar','class' => 'btn btn-nw-registro']) !!}
                                    <a type="button" href="{{url('veiculoentradasaida')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
            <!-- /.col -->
        </div>
        {!! Form::close() !!}
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/moment.js')}}"></script>
<script type="text/javascript" src="{{URL::to('js/veiculoentradasaida.js')}}"></script>
 {{-- DATA TABES SCRIPT 
 page script  --}}
<script type="text/javascript">
$(document).ready(function(){
    setTimeout(function () {
    @if(isset($show))
        desativarInputs();
        $("#btnFiltroPedidos").prop('disabled',true);
        $("#btnVincularPedidos").prop('disabled',true);
        $("#btnRemoverSelecionados").prop('disabled',true);
        ocultarElementos();
    @endif
    });
});

</script>
@endsection
