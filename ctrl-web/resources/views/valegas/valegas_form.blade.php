@extends('layouts.mainmenu')

@section('content')


<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($vendavalegas))
            {{ Form::model($vendavalegas, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('vendavalegas.update', $vendavalegas->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro','route' => 'vendavalegas.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Venda de Vale Gás</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Informações Gerais</a></li>
                            @if(isset($valegas) && count($valegas) > 0)
                            <li class=""><a href="#tab_2" data-toggle="tab">Vale Gás</a></li>
                            @endif
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="col-sm-4 col-sm-offset-1">
                                                    <div class="col-sm-5">
                                                        {{ Form::label('prevenda', 'Pré-Venda', ['class' => 'col-sm-7 control-lable input-sm']) }}
                                                        <div class="col-sm-1 checkbox">
                                                            {{ Form::checkbox('prevenda',1) }}
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        {{ Form::label('fecharprevenda', 'Fechar Pré-Venda', ['class' => 'col-sm-9 control-lable input-sm']) }}
                                                        <div class="col-sm-1 checkbox">
                                                            {{ Form::checkbox('fecharprevenda',1) }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-1 col-sm-push-1">
                                                    <button id="btnprevenda" type="button" data-toggle="modal"
                                                        data-target="#prevenda_modal"
                                                        class="btn btn-nw-buscas btn-sm">Fechar Pré-Venda</button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nomecliente', 'Cliente:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    <select id="nomecliente" name="nomecliente"
                                                        class="form-control input-sm" placeholder="Buscar Cliente"
                                                        data-selectize-value='[]'>
                                                    </select>
                                                </div>
                                                {{ Form::label('datavenda', 'Data Venda:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="generalDatePicker col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{ Form::text('datavenda',null,['id'=>'datavenda','class'=>'form-control generalDatePicker input-sm']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('produto_id', $produtos, null, ['id'=> 'produto_id','class' => 'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('valorunitario', 'Valor Unitário:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::text('valorunitario', null, ['id'=>'valorunitario','class' => 'form-control input-sm dinheiro']) }}
                                                </div>
                                                {{ Form::label('quantidade', 'Quantidade:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('quantidade',null,['id'=>'quantidade','class'=>'form-control input-sm number']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('condicaopagamento_id', 'Condição de Pagamento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('condicaopagamento_id', $condicaopagamento, null, ['id'=>'condicaopagamento_id','class' => 'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('valortotal', 'Valor Total:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::text('valortotal', null, ['id'=>'valortotal','class' => 'form-control input-sm', 'disabled' => 'true']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('datavenc', 'Vecto 1° Parcela:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="generalDatePicker col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{ Form::text('datavenc',null,['id'=>'datavenc','class'=>'form-control generalDatePicker input-sm','disabled'=>'true']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-3 col-md-push-2">
                                                    <button type="button" id="btnCalcularParcelas"
                                                        class="btn btn-nw-buscas btn-sm">Calcular Parcelas</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if(isset($valegas))
                            <div class="tab-pane" id="tab_2">
                                <div class="panel-body">
                                    <div class="col-md-12">
                                        <table id="tblgasdebolso" style="margin-left:1%"
                                            class="dataTable table table-bordered table-hover table-condensed">
                                            <thead>
                                                <tr>
                                                    <th>Sequência</th>
                                                    <th>Cód. Vale Gás</th>
                                                    <th>Data Venda</th>
                                                    <th>Situação</th>
                                                    <th>Produto</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($valegas as $gas)
                                                <tr>
                                                    <td>{{ $gas->prevendasequencia }}</td>
                                                    <td>{{ $gas->codigo }}</td>
                                                    <td>{{ requestDataOracleSemHora($gas->valegasvenda->datavenda) }}
                                                    </td>
                                                    <td>{{ $gas->valeGasSituacao->descricao }}</td>
                                                    <td>{{ $gas->produto->descricao }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="col-md-4">
                            {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                            <a type="button" href="{{ route('vendavalegas.index') }}"
                                class="btn btn-nw-geral">Voltar</a>
                        </div>
                    </div>
                </div>
            </ul>
            {{ Form::hidden('cliente_id',isset($vendavalegas) ? $vendavalegas->cliente_id : null,['id' => 'cliente_id']) }}
            {{ Form::hidden('cliente_id_erro',isset($vendavalegas) ? $vendavalegas->cliente_id : null, ['id'=>'cliente_id_erro']) }}
            {{ Form::hidden('cliente_nome_erro',@$nomecliente, ['id'=>'cliente_nome_erro']) }}
            {{ Form::hidden('produto_id_hd', null, ['id'=>'produto_id_hd','class' => 'form-control input-sm']) }}
            {{ Form::hidden('quantidade_hd',null,['id'=>'quantidade_hd','class'=>'form-control input-sm']) }}
            {{ Form::hidden('parcelas_financeiro', @$parcelasfinanceiro, ['id'=>'parcelas_financeiro','class' => 'form-control input-sm']) }}
            {{ Form::hidden('valoresfechar',null,['id'=>'valoresfechar','class'=>'form-control input-sm number']) }}
            {{ Form::hidden('vista', null, ['id'=>'vista','class' => 'form-control input-sm']) }}
            {{ Form::hidden('boleto', null, ['id'=>'boleto','class' => 'form-control input-sm']) }}
            {{ Form::hidden('cartao', null, ['id'=>'cartao','class' => 'form-control input-sm']) }}
            {{ Form::hidden('valortotal_hd', null, ['id'=>'valortotal_hd','class' => 'form-control input-sm']) }}
            {{ Form::close() }}
        </div>
        @include('general.modal_parcelas')
        @include('valegas.prevenda_modal')
    </div>
</div>

<script type="text/javascript" src="{{URL::to('js/valegas.js')}}"></script>
<script src="{{URL::to('js/customajax.js')}}"></script>
<script src="{{URL::to('js/customajaxext.js')}}"></script>
<script type="text/javascript">
@if($errors->any())
    errorsany = true;
@else
    errorsany = false;
@endif

$(document).ready(function() {
    setTimeout(function() {
        @if(isset($show))
            desativarInputs();
            var showpre = $("#condicaopagamento_id").val();
            if(showpre == ""){
                $("#btnCalcularParcelas").hide();
            }
            $("#btnCalcularParcelas").text('Visualizar Parcelas');
        @else
            var url_cliente = '{{URL::to("vendavalegas/ajaxbuscarprevendacli/:id")}}';
            $("#cliente_id_md").change(function (){
                ajaxProdutosPrevenda(url_cliente);
            });
        @endif
    });
});

</script>

@endsection
