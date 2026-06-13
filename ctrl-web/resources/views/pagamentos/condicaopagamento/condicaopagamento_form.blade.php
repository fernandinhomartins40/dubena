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
                        <div class="form-group crud_space">
                            <div class="col-sm-10 col-sm-push-1">
                                <div class="alert alert-informacao" id="info-alert" style="display: none">
                                    <button type="button" class="close" data-dismiss="alert">x</button>
                                    Nenhuma das parcelas deve ter percentual zero.
                                </div>
                            </div>
                        </div>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('tipo', 'Tipo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {!! Form::select('tipo',$tipos, $tipo, ['class'=>'form-control input-sm selectChosen']) !!}
                                                </div>
                                                {!! Form::label('nfc_tpag', 'Cód. Tipo Pagamento NF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
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
                                            {{Form::hidden('cliente_id_erro', null, ['id' => 'cliente_id_erro'])}}
                                            {{Form::hidden('cliente_nome_erro', null, ['id' => 'cliente_nome_erro'])}}
                                            <div class="form-group crud_space cartao">
                                                {!! Form::label('fornecedor_id', 'Fornecedor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    <select id="searchboxPJFornecedor" name="fornecedor_id" placeholder="Buscar Fornecedor" class="form-control" value="" data-selectize-value = '[]'></select>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space avista aprazocartao">
                                                {!! Form::label('dias_primeira', 'Dias para Pagamento:', ['class'=>'col-sm-3 control-label input-sm avista']) !!}
                                                {!! Form::label('dias_primeira', 'Dias Primeira Parcela:', ['class'=>'col-sm-3 control-label input-sm aprazocartao']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('dias_primeira',null,['class'=>'form-control input-sm number']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space aprazooutros">
                                                {!! Form::label('selecionarParcelas', 'Selecionar o Nº de Parcelas:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    <button type="button" class="btn btn-nw-registro btn-sm " id='btnSelecionarNumParcelas' data-toggle="modal" data-target="#myModal">Selecionar</button>
                                                </div>
                                            </div>
                                            {!! Form::text('num_parcelas',null,['class'=>'form-control input-sm number hidden', 'id'=>'num_parcelas']) !!}
                                            {!! Form::text('inputParcelasRetorno',null,['class'=>'form-control input-sm hidden', 'id'=>'inputParcelasRetorno']) !!}
                                            {!! Form::text('inputDiasParcelasRetorno',null,['class'=>'form-control input-sm hidden', 'id'=>'inputDiasParcelasRetorno']) !!}
                                            {!! Form::text('inputPercentualParcelasRetorno',null,['class'=>'form-control input-sm hidden', 'id'=>'inputPercentualParcelasRetorno']) !!}
                                            <div class="form-group crud_space aprazocartao">
                                                {!! Form::label('min_parcelas', 'Mín Parcelas:', ['class'=>'col-sm-3 control-label input-sm ']) !!}
                                                <div class="col-sm-1 ">
                                                    {!! Form::text('min_parcelas',null,['class'=>'form-control input-sm number ']) !!}
                                                </div>
                                                {!! Form::label('max_parcelas', 'Máx Parcelas:', ['class'=>'col-sm-3 control-label input-sm ']) !!}
                                                <div class="col-sm-1 ">
                                                    {!! Form::text('max_parcelas',null,['class'=>'form-control input-sm number ']) !!}
                                                </div>
                                                {!! Form::label('intervalo', 'Intervalo Entre Parcelas:', ['class'=>'col-sm-2 control-label input-sm ']) !!}
                                                <div class="col-sm-1 ">
                                                    {!! Form::text('intervalo',null,['class'=>'form-control input-sm number ']) !!}
                                                </div>
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
                                            <div id='parcelas'>
                                            </div>
                                            <div class="form-group crud_space margTop_25">
                                                <div class="col-sm-10 col-sm-offset-1">
                                                    <i>Para pagamentos do tipo "À prazo - outros" o campo "Dias para parcela X" deve ser em relação ao vencimento da parcela anterior</i>
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
<!-- page script -->
<script src="{{URL::to('js/condicaopagamento.js')}}">
</script>
<script>
    @if ($errors -> any())
        errorsAny = true;
    @else
        errorsAny = false;
    @endif
</script>
<script></script>
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Você precisa digitar o número de parcelas.</h4>
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
@endsection
