@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($condicaopagamento))
            {{ Form::model($condicaopagamento, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('condicaopagamento.update', $condicaopagamento->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'condicaopagamento.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Condição de Pagamento
                            </h3>
                        </div>
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <!-- form start -->
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-10">
                                            <div class="box-body">
                                                <div class="form-group crud_space">
                                                    {!! Form::label('tipo', 'Tipo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        {!! Form::select('tipos',$tipos, $tipo, ['class'=>'form-control input-sm selectChosen ', 'disabled' => 'disabled']) !!}
                                                        {!! Form::text('tipo',$tipo,['class'=>'form-control input-sm hidden']) !!}
                                                    </div>
                                                    {!! Form::label('nfc_tpag', 'Tipo Pagamento NF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        {!! Form::select('nfc_tpag', $nfc_tpag, null, ['class'=>'form-control input-sm selectChosen']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('contamovimentotipo_id', 'Tipo Recebimento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        {!! Form::select('contamovimentotipo_id', $contamovimentotipos, null, ['class'=>'form-control input-sm selectChosen']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-5">
                                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                                    </div>
                                                </div>
                                                {{Form::hidden('cliente_id_erro', @$condicaopagamento->fornecedor_id, ['id' => 'cliente_id_erro'])}}
                                                {{Form::hidden('cliente_nome_erro', @$condicaopagamento->fornecedor->nome, ['id' => 'cliente_nome_erro'])}}
                                                <div class="form-group crud_space cartao">
                                                    {!! Form::label('fornecedor_id', 'Fornecedor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-5">
                                                        <select id="searchboxPJFornecedor" name="fornecedor_id" placeholder="Buscar Fornecedor" class="form-control" value="" data-selectize-value = '[]'></select>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('dias_primeira', 'Dias para Pagamento:', ['class'=>'col-sm-3 control-label input-sm avista']) !!}
                                                    {!! Form::label('dias_primeira', 'Dias Primeira Parcela:', ['class'=>'col-sm-3 control-label input-sm aprazo aprazocartao']) !!}
                                                    <div class="col-sm-2">
                                                        {!! Form::text('dias_primeira',null,['class'=>'form-control input-sm number']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space aprazooutros">
                                                    {!! Form::label('selecionarParcelas', 'Dias para Pagamento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        <button type="button" class="btn btn-nw-registro btn-sm " id='btnSelecionarNumParcelas' data-toggle="modal" data-target="#myModal">Selecionar</button>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space aprazocartao">
                                                    @if(!isset($condicaopagamento))
                                                    {!! Form::label('intervalo', 'Intervalo Entre Parcelas:', ['class'=>'col-sm-3 control-label input-sm ']) !!}
                                                    <div class="col-sm-1 ">
                                                        {!! Form::text('intervalo',null,['class'=>'form-control input-sm number ']) !!}
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="form-group crud_space cartao">
                                                    {!! Form::label('taxa', '% Taxa:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-2">
                                                        {!! Form::text('taxa',null,['class'=>'form-control input-sm percentagem']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('enviaappnf', 'Envia App NF:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-2 checkbox">

                                                        {{ Form::checkbox('enviaappnf') }}
                                                    </div>
                                                    {!! Form::label('pedidosituacaoappnf_id', 'Situação App NF:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        {!! Form::select('pedidosituacaoappnf_id', $pedidosituacaos, null, ['class'=>'form-control input-sm selectChosen']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('appnfceauto', 'App Emite NFCe Auto:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-2 checkbox">
                                                       {{ Form::checkbox('appnfceauto') }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-2 checkbox">

                                                        {{ Form::checkbox('ativo') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!-- /.tab-pane -->
                                </div><!-- /.tab-pane -->
                            </div>
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                    <a type="button" href="{{route('condicaopagamento.index')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
            </ul><!-- /.col -->
        </div>
    </div>
</div>
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Selecione o Número de Parcelas</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12 hidden" id="erro">
                        <div class="error col-sm-2">
                        </div>
                        <div class="col-sm-4" style="color:red;">
                            Digite o Nº de Parcelas.
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('num_parcelas', 'Nº de Parcelas:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-10">
                            {!! Form::text('num_parcelas_modal',null,['class'=>'form-control input-sm number', 'id'=>'num_parcelas_modal', 'required' => 'required']) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                <button type="button" id="btnNumParcelasModal" class="btn btn-nw-registro" >Ok</button>
            </div>
        </div>
    </div>
</div>
<!-- page script -->
<script src="{{URL::to('js/condicaopagamento.js')}}">
</script>
<script>
    @if (isset($show))
    desativarInputs();
            @endif
</script>
@endsection