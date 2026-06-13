
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->

            @if(isset($comodato))
            {{ Form::model($comodato, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('comodato.update', $comodato->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'comodato.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif

            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Comodato
                            </h3>
                        </div>
                    </div><!-- /.box-header -->
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-sm-11">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('tipo', 'Tipo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-3">
                                                {{ Form::radio('tipo', '0', true, ['onchange' => 'selecionarTipo(this.value)']) }} Revenda para Cliente PJ<br />
                                                {{ Form::radio('tipo', '1', false, ['onchange' => 'selecionarTipo(this.value)']) }} Revenda para Cliente PF<br />
                                                {{ Form::radio('tipo', '2', false, ['onchange' => 'selecionarTipo(this.value)']) }} Distribuidora para Revenda
                                            </div>
                                            {!! Form::label('numeronota', 'Nota Fiscal:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('numeronota',null,['class'=>'form-control number']) !!}
                                            </div>
                                            <div class="col-sm-7">
                                            </div>
                                            {!! Form::label('observacao', 'Observações:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-6">
                                                {!! Form::text('observacao',null,['class'=>'form-control']) !!}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group crud_space">
                                        {{Form::hidden('cliente_id_erro',@$comodato->cliente_id, ['id'=>'cliente_id_erro'])}}
                                        {{Form::hidden('cliente_nome_erro',@$comodato->cliente->nome, ['id'=>'cliente_nome_erro'])}}
                                        @if (isset($comodato) && $comodato->cliente->cpf !== null)
                                        {{Form::hidden('cliente_cpf_cnpj_erro',@$comodato->cliente->cpf, ['id'=>'cliente_cpf_cnpj_erro'])}}
                                        @else
                                        {{Form::hidden('cliente_cpf_cnpj_erro',@$comodato->cliente->cnpj, ['id'=>'cliente_cpf_cnpj_erro'])}}
                                        @endif
                                        {!! Form::label('cliente_id', 'Nome/Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-6 searchboxPJ onlyCreate">
                                            <select id="searchboxPJ" name="cliente_id" placeholder="Buscar cliente" class="form-control" value="" data-selectize-value = '[]'></select>
                                        </div>
                                        <div class="col-sm-6 searchboxPF onlyCreate">
                                            <select id="searchboxPF" name="cliente_id" placeholder="Buscar cliente" class="form-control" value="" data-selectize-value = '[]'></select>
                                        </div>
                                        <div class="col-sm-6 searchboxPJFornecedor onlyCreate">
                                            <select id="searchboxPJFornecedor" name="cliente_id" placeholder="Buscar cliente" class="form-control" value="" data-selectize-value = '[]'></select>
                                        </div>
                                        <div class='col-sm-6 hidden onlyRead'>
                                            {{Form::text('cliente',@$comodato->cliente->nome, ['id'=>'form-control', 'class' => 'form-control', 'disabled'])}}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {!! Form::label('cnpj_cpf', 'CNPJ/CPF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            @if (@$comodato->cliente->cpf !== null)
                                            {!! Form::text('cnpj_cpf',@$comodato->cliente->cpf,['class'=>'form-control', 'disabled']) !!}
                                            @else
                                            {!! Form::text('cnpj_cpf',@$comodato->cliente->cnpj,['class'=>'form-control', 'disabled']) !!}
                                            @endif
                                        </div>
                                        {!! Form::label('inscricao_estadual', 'Inscrição Estadual/RG:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            @if (@$comodato->cliente->rg !== null)
                                            {!! Form::text('inscricaoest_rg',@$comodato->cliente->rg,['class'=>'form-control', 'disabled', 'id' => 'inscricaoest_rg']) !!}
                                            @else
                                            {!! Form::text('inscricaoest_rg',@$comodato->cliente->inscricao_estadual,['class'=>'form-control', 'disabled', 'id' => 'inscricaoest_rg']) !!}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {!! Form::label('datacontrato', 'Data Comodato/NF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-3">
                                            <div class="col-sm-10">
                                            <div class="input-group generalDateTimePicker" style="margin-left: -15px">
                                                    {!! Form::text('datacontrato',@$comodato->datacontrato,['class'=>'form-control generalDateTimePicker']) !!}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        {!! Form::label('datavencimento', 'Vencimento:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            <div class="input-group generalDatePicker">
                                                {!! Form::text('datavencimento',@$comodato->datavencimento,['class'=>'form-control generalDatePicker dataPadrao']) !!}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-2 checkbox">
                                            {!! Form::checkbox('ativo',1) !!}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space margTop_15 divPJ">
                                        <div class="col-sm-2">
                                        </div>
                                        <div class="col-sm-2">
                                        <strong>Representante Legal</strong>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space divPJ">
                                        {!! Form::label('nomerepresentante', 'Nome:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('nomerepresentante',@$cliente->clienteConvenio->nomerepresentante,['class'=>'form-control', 'id' => 'nomerepresentante']) !!}
                                        </div>
                                        {!! Form::label('cpfrepresentante', 'CPF:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('cpfrepresentante',@$cliente->clienteConvenio->cpfrepresentante,['class'=>'form-control cpf', 'id' => 'cpfrepresentante']) !!}
                                        </div>
                                        {!! Form::label('rgrepresentante', 'RG:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('rgrepresentante',@$cliente->clienteConvenio->rgrepresentante,['class'=>'form-control rg', 'id' => 'rgrepresentante']) !!}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space margTop_15 ">
                                    </div>
                                    <div class="form-group crud_space margTop_15">
                                    </div>
                                    <div class="form-group crud_space onlyCreate margTop_15">
                                        {!! Form::label('produtoclasse_id', 'Classe de Produto:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::select('produtoclasse_id',$produtoClasses, null, ['class' => 'form-control selectChosen','id'=>'produtoclasse_id']) !!}
                                        </div>
                                        {!! Form::label('produto_id', 'Produto:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-3">
                                            {!! Form::select('produto_id',[], null, ['onchange'=>'enableDisableBtnAddProduto()','class' => 'form-control selectChosen','id'=>'produto_id']) !!}
                                        </div>
                                        {!! Form::label('quantidade', 'Quantidade:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-1">
                                            {!! Form::text('quantidade',null,['onkeyup'=>'enableDisableBtnAddProduto()','class'=>'form-control number','id'=>'quantidade']) !!}
                                        </div>
                                        <button id="btnAddProduto" onclick="addProdutos()" type="button" class="btn btn-nw-buscas btn-xs">Adicionar</button>
                                    </div>
                                    <div class="col-sm-8 col-sm-push-3 margTop_15">
                                        {!! Form::hidden('produtos',null,['id'=>'produtos']) !!}
                                        <table id="tblProdutos" class="table bordered table-condensed">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Produto</th>
                                                    <th>Quantidade</th>
                                                    <th>Operação</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if(isset($comodatoprodutos))
                                                @foreach ($comodatoprodutos as $produto)
                                                <tr>
                                                    <td>{{ $produto->produto->id}}</td>
                                                    <td>{{ $produto->produto->descricao }}</td>
                                                    <td>{{ $produto->quantidade }}</td>
                                                    <td>
                                                        <button id="removerProduto" type="button" class="btn removerProduto btn-nw-registro btn-xs">Remover</button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="divInputs">

                        </div>
                        <div class="box-footer">

                            <div class="col-sm-4">
                                <button class="btn btn-nw-registro">Gravar</button>
                                <a type="button" href="{{url('comodato')}}" class="btn btn-nw-geral">Voltar</a>  
                                @if(isset($show))
                                @if($comodato->ativo == 1)
                                <a type="button" target='_blank' href='{!!url("comodato/contrato", "$comodato->id")!!}' class="btn btn-danger">Gerar Contrato</a>  
                                @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
        </div>
    </div>
</div>
{!! Form::close() !!}
<script src="{{URL::to('js/comodato.js')}}"></script>
@include('comodatos.js')

@endsection
