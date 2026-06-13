@extends('layouts.mainmenu')
@section('content')
<style>
    .radio input[type="radio"], .radio-inline input[type="radio"], .checkbox input[type="checkbox"], .checkbox-inline input[type="checkbox"] {
        margin-left: -20px !important;
    }
</style>
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($fechamentoconvenio)) 
            {{ Form::model($fechamentoconvenio, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('fechamentoconvenio.update', $fechamentoconvenio->id))) }} 
            @else 
            {{ Form::open(['id'=>'fmCadastro','route' => 'fechamentoconvenio.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Fechamento Convênios</h3>
                        </div>
                    </div>
                    <!-- /.box-header -->
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Fechamento de Convênio</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-11">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {{ Form::label('cliente_id', 'Convênio:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-3">
                                                {{ Form::select('cliente_id',$clientes, null, ['id'=>'cliente_id', 'class' => 'form-control selectChosen']) }}
                                                {{ Form::hidden('edit',@$edit,['id'=>'edit']) }}
                                            </div>
                                            {{ Form::hidden('condicaopagamento_id',@$condicao,['id'=>'condicaopagamento_id']) }}
                                        </div>
                                        <div class="form-group crud_space">
                                            {{ Form::label('dataemissao', 'Data Emissão:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                    {{ Form::datetime('dataemissao',null,['id'=>'dataemissao', 'class'=>'form-control input-sm generalDatePicker','readonly']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            {{ Form::label('datavencimento', 'Data Vencimento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                    {{ Form::datetime('datavencimento',null,['id'=>'datavencimento','class'=>'form-control input-sm generalDatePicker','readonly']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                    {{ Form::datetime('datainicio',null,['id'=>'datainicio', 'class'=>'form-control input-sm generalDatePicker']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                    {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <button class="btn btn-sm btn-nw-buscas" id="btnFiltroPedidos" type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                                    <span class="fa fa-search fa-lg"></span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            <div class="col-md-9 col-md-push-2" style='background-color: white'>
                                                {{ Form::hidden('tblconveniopedidos_hd',null,['id'=>'tblconveniopedidos_hd']) }}
                                                <table id="tblPedidosConvenios" class="table table-bordered table-striped table-hover table-condensed" style="max-height:500px">
                                                    <thead>
                                                        <tr>
                                                            <th>Pedido</th>
                                                            <th>cliente_id</th>
                                                            <th>Cliente</th>
                                                            <th>Data Pedido</th>
                                                            <th>Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    @if (isset($pedidos))
                                                    @foreach ($pedidos as $pedido)
                                                        <tr>
                                                            <td>{{ $pedido->id }}</td>
                                                            <td>{{ $pedido->cliente_id }}</td>
                                                            <td>{{ $pedido->cliente }}</td>
                                                            <td>{{ requestDataOracle($pedido->datahoraprevisaoentrega) }}</td>
                                                            <td>{{ requestNumeroDecimalOracle($pedido->valorvenda) }}</td>
                                                        </tr>
                                                    @endforeach
                                                    @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {{ Form::label('valor', 'Valor a pagar:', ['class'=>'col-sm-9 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                {{ Form::text('valor',null,['id'=>'valor','class'=>'form-control input-sm','readonly']) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="col-md-4">
                            {{ Form::submit('Gravar', ['id'=>'btnGravarFechamento','class' => 'btn btn-nw-registro']) }}
                            <a id="goback" type="button" class="btn btn-nw-geral">Voltar</a>
                        </div>
                    </div>
                </div>
            </ul>
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/moment.js') }}"></script>
<script type="text/javascript" src="{{URL::to('js/fechamentoConvenio.js') }}"></script>
<script type="text/javascript">
var edit = '{{ isset($edit) ? "1" : "0"}}';
var conveniofechamento_id = '{{ isset($fechamentoconvenio) ? $fechamentoconvenio->id : "0"}}';
@if($errors->any())
    errors = true;
@else
    errors = false;
@endif

$(document).ready(function(){
    setTimeout(function () {
    @if(isset($show))
        desativarInputs();
        $("#btnFiltroPedidos").prop('disabled',true);
    @endif
    @if(@$edit)
        $("#cliente_id").prop('disabled',true).trigger('chosen:updated');
    @endif
    });
});

@if(str_contains(Request::url(), '.filtro'))
    setarDatas();
@endif
</script>
@endsection