@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        @if(isset($promotor))
        {{ Form::model($promotor, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'route'
        => array('promover.update', $promotor->id))) }}
        @else
        {{ Form::open(['id'=>'fmCadastro', 'route' => 'promover.store', 'class' => 'form-horizontal']) }}
        @endif

        <div class="col-sm-12">
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Promover Vendas</h3>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('ausente', 'Cliente Ausente:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('ausente', '1', null, ["id" => "ausente"]) }}
                                                </div>
                                                {{ Form::label('cidade_id', 'Cidade:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('cidade_id', @$cidades, null,['id' => 'cidade_id', 'class'=>'form-control input-sm selectChosen']) }}
                                                </div>
                                            </div>
                                            <div id="convenio-container" class="form-group crud_space hidden">
                                                <div id="convenio-div" class="fontSize_12" style="color: green; text-align: center;">

                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('cliente', 'Cliente:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-6">
                                                    <div class="input-group">
                                                        {{ Form::text('cliente', null, ['id' => 'cliente', 'class'=>'form-control input-sm']) }}
                                                        <span class="input-group-addon">
                                                            <a href="#" id="buscarClienteByName">
                                                                <i class="glyphicon glyphicon-search"></i>
                                                            </a>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('rua', 'Rua:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group">
                                                        {{ Form::text('rua', @$rua, ['id' => 'rua', 'class'=>'form-control input-sm']) }}
                                                        {{ Form::hidden('rua_id', @$rua_id, ['id' => 'rua_id']) }}
                                                        <span class="input-group-addon">
                                                            <a href="#" id="buscarRua">
                                                                <i class="glyphicon glyphicon-search"></i>
                                                            </a>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('numero', 'Número:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group">
                                                    {!! Form::text('numero',null,['id' => 'numero', 'class'=>'form-control input-sm number ']) !!}
                                                        <span class="input-group-addon">
                                                            <a href="#" id="buscarCliente">
                                                                <i class="glyphicon glyphicon-search"></i>
                                                            </a>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space endereco-container">
                                                {!! Form::label('bairro', 'Bairro:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group">
                                                        {!! Form::text('bairro',null,['id' => 'bairro', 'class'=>'form-control input-sm']) !!}
                                                        {!! Form::hidden('bairro_id',null,['id' => 'bairro_id']) !!}
                                                        <span class="input-group-addon">
                                                            <a href="#" id="buscarBairro">
                                                                <i class="glyphicon glyphicon-search"></i>
                                                            </a>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('complemento', 'Complemento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('complemento',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space endereco-container">
                                                {!! Form::label('ponto_referencia', 'Ponto Referência:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('ponto_referencia',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('setor_id', $setores, null,['id' => 'setor_id', 'class'=>'form-control input-sm selectChosen']) }}
                                                </div>
                                            </div>
                                            <hr>
                                            <div>
                                                {{ Form::hidden('cliente_id', null, ['id' => 'cliente_id']) }}
                                                {{ Form::hidden('alltables', null, ['id' => 'alltables']) }}
                                                {{ Form::hidden('uf', null, ['id' => 'uf']) }}
                                                {{ Form::hidden('descpara', json_encode($descpara), ['id' => 'descpara']) }}
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                {!! Form::label('tipopessoa_id', 'Tipo de Pessoa:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::select('tipopessoa_id', $tipopessoas, @$tipopessoa, ['class' => 'form-control selectChosen']) !!}
                                                </div>
                                                {!! Form::label('nome', 'Nome/Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {!! Form::text('nome',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                <div class="fisica">
                                                    {!! Form::label('cpf', 'CPF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-2">
                                                        {!! Form::text('cpf',null,['class'=>'form-control input-sm cpf ']) !!}
                                                    </div>
                                                    {!! Form::label('rg', 'RG:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-2">
                                                        {!! Form::text('rg',null,['class'=>'form-control input-sm rg']) !!}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                <div class="juridica">
                                                    {!! Form::label('cnpj', 'CNPJ:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-2">
                                                        {!! Form::text('cnpj',null,['class'=>'form-control input-sm cnpj ']) !!}
                                                    </div>
                                                    {!! Form::label('inscricao_estadual', 'Insc. Est.:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-2">
                                                        {!! Form::text('inscricao_estadual',null,['class'=>'form-control input-sm ']) !!}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                {!! Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::select('segmento_id', $segmentos, null, ['class' => 'form-control selectChosen ']) !!}
                                                </div>
                                                <div class="fisica">
                                                    {!! Form::label('sexo', 'Sexo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-2">
                                                        {!! Form::select('sexo',["" => "Selecione", "F"=>"Feminino","M"=>"Masculino"], null, ['class' => 'form-control selectChosen', 'id' =>'sexo']) !!}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                <div class="fisica">
                                                    {!! Form::label('datanascimento', 'Nascimento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePickerDefaultDateFalse">
                                                            {!! Form::text('datanascimento',null,['class'=>'form-control input-sm generalDatePickerDefaultDateFalse', 'id' => 'datanascimento']) !!}
                                                            <span class="input-group-addon">
                                                                <i class="glyphicon glyphicon-calendar"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2 col-sm-push-2 media-pt-10">
                                                    <button id="btnHistorico"
                                                        type="button" class="btn btn-nw-buscas btn-sm"
                                                        data-toggle="modal" data-target="#popup_historico"
                                                    >
                                                        Histórico
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                {{ Form::label('cliProdutoPreco', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('selectProdutosPrecos',$produtos,null,['class'=>'form-control selectChosen', 'id' => 'selectProdutosPrecos']) }}
                                                </div>
                                                {{ Form::label('valordesc', 'Desconto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::text('valordesc', null, ['id'=>'valor0', 'class' => 'form-control input-sm dinheiroNoZero']) }}
                                                </div>
                                                <div class="col-sm-2 media-pt-10">
                                                    <button id="btnAddPreco" type="button" class="btn btn-nw-buscas btn-sm">
                                                        Add
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                <div class="col-sm-8 col-sm-push-2">
                                                    {{ Form::hidden('produtos',null,['id' => 'produtos']) }}
                                                    <table id="tblProdutosPrecos" class="table table-bordered table-hover table-condensed">
                                                        <thead>
                                                            <tr>
                                                                <th>codigo</th>
                                                                <th style='width: 10%;'>Cód.</th>
                                                                <th style='width: 40%;'>Produto</th>
                                                                <th></th>
                                                                <th style='width: 15%;'>Desconto</th>
                                                                <th></th>
                                                                <th></th>
                                                                <th style='width: 15%;'>Para</th>
                                                                <th style='width: 15%;'>Operação</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if(isset($promotor))
                                                                @foreach ($promotor->clienteProduto as $cliProduto)
                                                                <tr id="">
                                                                    <td>{{ $cliProduto->id }}</td>
                                                                    <td>{{ $cliProduto->produto->id }}</td>
                                                                    <td>{{ $cliProduto->produto->descricao }}</td>
                                                                    <td>
                                                                        {{ is_null($cliProduto->preco)
                                                                            ? " "
                                                                            : requestNumeroDecimalOracle($cliProduto->preco)
                                                                        }}
                                                                    </td>
                                                                    @if (is_null($cliProduto->tipo))
                                                                        <td></td>
                                                                    @else
                                                                        <td>{{ $cliProduto->tipo == 1 ? requestNumeroDecimalOracle($cliProduto->desconto) : requestPercentualOracle($cliProduto->desconto * 100) }}</td>
                                                                    @endif
                                                                    <td>{{ $cliProduto->tipo }}</td>
                                                                    <td>{{ $cliProduto->descontopara }}</td>
                                                                    <td>{{ $descpara[$cliProduto->descontopara] }}</td>
                                                                    <td>
                                                                        <button id="btnRemoverProduto" type="button" class="btn btn-nw-registro btn-xs">Remover</button>
                                                                    </td>
                                                                </tr>
                                                                @endforeach
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                {!! Form::label('telefonetipo_id', 'Tipo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::select('telefonetipo_id', $telefonetipos, null, ['id'=>'telefonetipo_id', 'class' => 'form-control selectChosen']) !!}
                                                </div>
                                                {!! Form::label('whatsapp', 'WhatsApp:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('whatsapp') }}
                                                </div>
                                                <div class="col-sm-2 media-pt-10">
                                                    <input type="text" id="telefone" class="input-sm form-control telefone" value="{{@$telefone}}">
                                                </div>
                                                <div class="col-sm-2 media-pt-10">
                                                    <button type="button" id='btnAddFone' disabled="disabled" class="btn btn-sm btn-nw-buscas">
                                                        Add
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space cliente-container">
                                                <div class="col-sm-8 col-sm-push-2">
                                                    {{Form::hidden('telefones',"", ['id'=>'telefones'])}}
                                                    <table id="tblTelefones" class="table table-bordered table-hover table-condensed">
                                                        <thead>
                                                            <tr>
                                                                <th>codigo</th>
                                                                <th></th>
                                                                <th style='width: 20%'>Tipo Telefone</th>
                                                                <th>Número</th>
                                                                <th style='width: 10%'>WhatsApp</th>
                                                                <th style='width: 30%'>Operação</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if(isset($promotor))
                                                                @foreach ($promotor->telefones as $telefone)
                                                                <tr id="fone{{$telefone->telefonetipo_id}}">
                                                                    <td>{{$telefone->id}}</td>
                                                                    <td>{{$telefone->telefonetipo->id}}</td>
                                                                    <td>{{$telefone->telefonetipo->descricao}}</td>
                                                                    <td>{{$telefone->telefone}}</td>
                                                                    <td>{{$telefone->whatsapp == 1 ? 'Sim' : 'Não' }}</td>
                                                                    <td>
                                                                        <button type='button' class='btn btn-nw-geral btn-xs' id='btnEditarTelefone'>Editar</button>
                                                                        <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverTelefone'>Remover</button>
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
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="col-md-4">
                            {{ Form::submit('Gravar', ['id'=>'btnSubmit','class' => 'btn btn-nw-registro']) }}
                            <a href="{{url('promover')}}" class="btn btn-nw-geral">Voltar</a>
                        </div>
                    </div>
                </div>
            </ul>
        </div>
        {!! Form::close() !!}
    </div>
</div>

<div id="popup_selects" class="modal fade popupModal dontHideEsc" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-sm" role="document" id="fundo_popup">
        <div class="modal-content">
            <div class="modal-header" id="popup_int">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel" style="text-align: center;">Escolha a Rua</h4>
            </div>
            <div id="popup_int">
                <div class="box-body">
                    <div id="container-nenhum" class="row hidde">
                        <div class="col-sm-10 col-sm-push-1">
                            <button id="btnNovoCliente" type="button" class="btn btn-sm btn-nw-registro">
                                Novo Cliente
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-10 col-sm-push-1">
                            <table id="tbl_descricoes" class="table table-bordered table-condensed" style="padding:0; margin:0">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Descrição</th>
                                        <th>Selecionar</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div><!-- /.box-body -->
            </div>
        </div>
    </div>
</div>

<div id="popup_historico" class="modal fade popupModal dontHideEsc" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-sm" role="document" id="fundo_popup">
        <div class="modal-content">
            <div class="modal-header" id="popup_int">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel" style="text-align: center;">Histórico de Compras</h4>
            </div>
            <div id="popup_int">
                <div class="box-body">
                    <div id="hist-msg" class="row">
                        <div class="col-sm-10 col-sm-push-1 fontSize_16">
                            Nenhum pedido encontrado!
                        </div>
                    </div>
                    <div id="tbl-row" class="row hidden">
                        <div class="col-sm-11">
                            <table id="tbl_historico" class="table table-bordered table-condensed" style="padding:0; margin:0">
                                <thead>
                                    <tr>
                                        <th>Pedido</th>
                                        <th>Data</th>
                                        <th>Forma Pgto</th>
                                        <th>Status</th>
                                        <th>Produto</th>
                                        <th>Qtde</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div><!-- /.box-body -->
            </div>
        </div>
    </div>
</div>

<script src="{{URL::to('js/promotor.js')}}"></script>
<script>
    @if ($errors->any())
        errors = true;
    @endif

    setTimeout(function () {
        @if (isset($promotor))
            @if (isset($show) && $show)
                desativarInputs();

                @if ($promotor->ausente)
                    checkAusente();
                @else
                    showContainers();
                @endif

                adjustTables();
            @elseif (isset($edit) && $edit && !is_null($promotor->cliente_id) && !$promotor->ausente)
                showContainers();

                @if ($promotor->ausente)
                    checkAusente();
                @else
                    showContainers();
                @endif

                adjustTables();
            @endif
        @else
            @if (( isset($compCidadeId) || isset($cidade_id) ) && !$errors->any())
                @php
                    $cidade = isset($cidade_id) ? $cidade_id : $compCidadeId;
                @endphp

                $("#cidade_id").val({{ $cidade }}).trigger("chosen:updated");
            @endif
        @endif
    }, $(document).ready());
</script>

@endsection
