@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            @if(isset($estoquerequisicoes))
            {{ Form::model($estoquerequisicoes, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'route' => array('estoquerequisicoes.update', $estoquerequisicoes->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'estoquerequisicao.store', 'class' => 'form-horizontal']) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Requisição de Estoque</h3>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space col-sm-12">
                                                {{ Form::label('data', 'Data:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) }}
                                                <div class="col-sm-3">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datahora',null,['class'=>'form-control input-sm generalDateTimePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('colaborador', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::text('user_id', $colaborador, array('class'=>'form-control input-sm', 'disabled'))}}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space col-sm-12">
                                                {{ Form::label('centrocusto_id', 'Centro Custo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{Form::hidden('centrocusto_id',$centrocusto_id, ['id'=>'centrocusto_id'])}}
                                                    <div class="input-group">
                                                        {{ Form::text('centrocusto_descricao',$centrocusto_descricao,['id'=>'centrocusto_descricao', 'class'=>'form-control input-sm', 'readonly']) }}
                                                        <span class="input-group-btn">
                                                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcusto" onclick="abrirCentroCusto();">Mudar</button>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('planoconta_id', 'Plano Conta:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{Form::hidden('planoconta_id',$planoconta_id, ['id'=>'planoconta_id'])}}
                                                    <div class="input-group">
                                                        {{ Form::text('planoconta_descricao',$planoconta_descricao,['id'=>'planoconta_descricao', 'class'=>'form-control input-sm', 'readonly']) }}
                                                        <span class="input-group-btn">
                                                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPconta" onclick="abrirPlanoConta();">Mudar</button>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space col-sm-12">
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('setor_id', $setor, null,['class'=>'form-control input-sm selectChosen', 'onChange' => 'atualizarProdutos()']) }}
                                                </div>
                                                {{ Form::label('produto', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('produto', $produtosEstoque, null, ['class' => 'form-control selectChosen']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space col-sm-12">

                                                {{ Form::label('quantidade', 'Quantidade:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::text('quantidade', null, ['class' => 'form-control floatQtde']) }}
                                                </div>
                                                {{ Form::label('customedio', 'Custo:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::text('customedio', null, ['class' => 'form-control']) }}
                                                </div>
                                                <div class="col-sm-1">
                                                    <button type="button" onclick="buscaQuantidadeProduto('{{url('consultaestoquesetor/buscaquantidadeproduto/:produto_id/:setor_id')}}')" id="addProdutos" class='btn btn-nw-buscas btn-xs'>
                                                        Adicionar
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space col-sm-12">
                                                {{ Form::label('observacoes', 'Observações:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-8">
                                                    {{ Form::textarea('observacoes',null,['rows'=>'2', 'class'=>'form-control input-sm', 'id'=>'observacoes']) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group crud_space col-sm-8 col-sm-push-2">
                                            <hr class='thin'>
                                            <table id="tblProdutosRequisicao" class="table table-bordered table-hover table-condensed  bg-success">
                                                <thead>
                                                    <tr>
                                                        <th style="width:8%">Cód</th>
                                                        <th style="width:20%">Produto</th>
                                                        <th style="width:22%">Setor</th>
                                                        <th style="width:20%">Cód Setor</th>
                                                        <th style="width:10%">Qtde</th>
                                                        <th style="width:10%">Custo</th>
                                                        <th style="width:10%">Operação</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyProdutosList" name="tbodyProdutosList">
                                                </tbody>
                                                {{ Form::hidden('produtos', null, ['class' => 'form-control', 'id' => 'produtos']) }}
                                                {{ Form::text('inputSetor_id', null, ['class' => 'form-control hidden', 'id' => 'inputSetor_id']) }}
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="col-md-4">
                                {{ Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) }}
                                <a id="buttonBack" type="button" href="{{url('estoquerequisicao')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div><!-- /.tab-content -->
                </div>
            </ul><!-- /.col -->
        </div>
        {{ Form::close() }}
    </div>
</div>
@include('financeiro.centrocustos_partial1_js')
@include('financeiro.planocontas_partial1_js')
<script src='{{url("js/estoquerequisicao.js")}}'></script>
<script>
    var listaProdutos = '';
    var qdeEidProduto = '';
    var produtoId = '';
    var valorFinal = '';
    var urlProduto = '{{ url("produto/ajax/:id") }}';
    var urlBuscaProdutosAjax = '{{ url("produto/buscaporsetor/:id") }}';
   setTimeout( function () { 
       @if ($errors -> any())
        carregarProdutosErro();
    @endif
    }, $(document).ready());
</script>
@include('financeiro.centrocustos_partial2_js')
@include('financeiro.planocontas_partial2_js')
@include('financeiro.centrocustos_partial1')
@include('financeiro.planocontas_partial1')

@endsection
