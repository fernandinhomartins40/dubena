<div class="form-group-sm col-sm-12">
    {{ Form::label('datahoraacao', 'Data Ação:', ['class'=>'col-sm-2 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('datahoraacaoshow', 'Data Pedido/Previsão:', ['class'=>'col-sm-2 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{-- {{ Form::label('', 'Convênio:', ['class'=>'col-sm-2 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }} --}}
    <label id="labelConvenio" for="" class="col-sm-2 labelTop control-label input-sm" style="text-align: left !important;">
        <span class="convenio-label">Convênio:</span>
    </label>
    {{ Form::label('promocao', 'Promoção:', ['class'=>'col-sm-4 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('pedidosituacao_id', 'Status:', ['class'=>'col-sm-2 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
</div>
{{ Form::hidden('numerocartao',null,['class'=>'form-control input-sm ', 'id' => 'numerocartao']) }}
{{ Form::hidden('datahoraacao',null,['class'=>'form-control input-sm generalDateTimePickerSeconds ','id' => 'datahoraacao']) }}
<div class="form-group col-sm-12">
    <div class="col-sm-2">
        <div class="input-group generalDateTimePickerSeconds">
            {{ Form::text('datahoraacaoshow',requestDataOracle(@$pedido->datahoraacao, true, false),['class'=>'form-control input-sm generalDateTimePickerSeconds ', 'disabled' => 'true', 'id' => 'datahoraacaoshow']) }}
            <span class="input-group-addon" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                <i class="glyphicon glyphicon-calendar"></i>
            </span>
        </div>
    </div>
    <div class="col-sm-2">
        <div class="input-group generalDateTimePickerSeconds">
            {{ Form::text('datahoraprevisaoentrega',requestDataOracle(@$pedido->datahoraprevisaoentrega, true, false),['class'=>'form-control input-sm generalDateTimePickerSeconds', 'id' => 'datahoraprevisaoentrega']) }}
            <span class="input-group-addon" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                <i class="glyphicon glyphicon-calendar"></i>
            </span>
        </div>
    </div>
    <b class="fontSize_12">
        <div class="col-sm-2 fontSize_11" id='divConvenio' style="color: green">

        </div>
    </b>
    <b class="fontSize_12">
        <div class="col-sm-2 fontSize_11" id='divPromocao' style="color: green">

        </div>
    </b>
    {{ Form::label('entregaurgente', 'Urgente:', ['class'=>'col-sm-1 control-label input-sm',  'style'=>'text-align: right !important;']) }}
    <div class="col-sm-1 checkbox-inline">
        {{ Form::checkbox('entregaurgente') }}
    </div>
    <div class="col-sm-2">
        {{ Form::select('pedidosituacao_id', $status, null,['class'=>'form-control input-sm selectChosen', 'id' => 'pedidosituacao_id']) }}
    </div>
</div>
<div class="form-group-sm col-sm-12">
    {{ Form::label('entregatelefone', 'Telefone:', ['class'=>'col-sm-2 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('nomecliente', 'Cliente:', ['class'=>'col-sm-8 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('cep', 'CEP:', ['class'=>'col-sm-2 control-label labelTop input-sm', 'style' => 'text-align: left !important;']) }}
</div>
<div class="form-group col-sm-12">
    <div class="col-sm-2">
        <div class="input-group">
            {{ Form::text('entregatelefone',@$telefone,['class'=>'form-control input-sm', 'accesskey' => 'N']) }}
            <span class="input-group-addon" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;" >
                <a href="#" id="btnBuscaClienteTelefone"><i class="glyphicon glyphicon-search" ></i></a>
            </span>
        </div>
    </div>
    {{ Form::hidden('cliente_id',null,['id' => 'cliente_id']) }}
    {{Form::hidden('cliente_id_erro',null, ['id'=>'cliente_id_erro'])}}
    {{Form::hidden('cliente_nome_erro',null, ['id'=>'cliente_nome_erro'])}}
    <div class="col-sm-8">
        <div class="input-group">
            @if(isset($clienteSelectize))
                <select id="nomecliente" name="nomecliente" placeholder="Buscar cliente"  class="form-control input-sm" value="" data-selectize-value = '{{$clienteSelectize}}'></select>
            @else
                <select id="nomecliente" name="nomecliente" placeholder="Buscar cliente"  class="form-control input-sm" value="" data-selectize-value = '[]'></select>
            @endif
            <span class="input-group-addon" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                <a disabled="true" href="#" data-toggle="modal" id="btnEditCliente" ><i class="glyphicon glyphicon-pencil"></i></a>
            </span>
        </div>
    </div>
    <div class="col-sm-2">
        <div class="input-group2">
            {{ Form::text('entregacep', @$cep, ['class' => 'form-control input-sm', 'id' => 'entregacep']) }}
            <!--
            <span class="input-group-addon" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                <a href="#" id="buscarEnderecoEntrega"><i class="glyphicon glyphicon-search"></i></a>
            </span>
            -->
        </div>
    </div>
</div>

<div class="form-group-sm col-sm-12">
    {{ Form::label('uf', 'Estado:', ['class'=>'col-sm-1 control-label labelTop input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('cidade_id', 'Cidade:', ['class'=>'col-sm-2 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('bairro_id', 'Bairro:', ['class'=>'col-sm-3 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('rua_id', 'Endereço:', ['class'=>'col-sm-3 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('numero', 'Nº:', ['class'=>'col-sm-1 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('complemento', 'Complemento:', ['class'=>'col-sm-2 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
</div>
<div class="form-group col-sm-12">
    <div class="col-sm-1">
        {{ Form::select('ufentrega', $uf, $ufEmpresa, ['class' => 'form-control selectChosen', 'id' => 'ufentrega']) }}
    </div>
    <div class="col-sm-2">
        {{ Form::select('entregacidade_id', $cidades, $cidade_id, ['class' => 'form-control selectChosen','id' => 'entregacidade_id']) }}
    </div>
    <div class="col-sm-3">
        {{ Form::select('entregabairro_id', $bairros, null, ['id' => 'entregabairro_id','class' => 'form-control selectChosen ']) }}
    </div>
    <div class="col-sm-3">
        @if(isset($ruaSelectize))
            <select id="entregarua_id" name="entregarua_id" placeholder="Buscar rua"  class="form-control input-sm" value="" data-selectize-value = '{{$ruaSelectize}}'></select>
        @else
            <select id="entregarua_id" name="entregarua_id" placeholder="Buscar rua"  class="form-control input-sm" value="" data-selectize-value = '[]'></select>
        @endif
        {{Form::hidden('rua_id_erro',null, ['id'=>'rua_id_erro'])}}
        {{Form::hidden('rua_descricao_erro',null, ['id'=>'rua_descricao_erro'])}}
    </div>
    <div class="col-sm-1">
        {{ Form::text('entreganumero',null,['class'=>'form-control input-sm number ', 'id' => 'entreganumero']) }}
    </div>
    <div class="col-sm-2">
        <div class="input-group">
            {{ Form::text('entregacomplemento',null,['class'=>'form-control input-sm', 'id' => 'entregacomplemento']) }}
            <span class="input-group-addon" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                <a href="#" id="btnBuscaClienteEndereco" urlclick='{{url("clienteendereco/buscaclienteendereco?rua=:rua&num=:num&complemento=:complemento")}}'>
                    <i class="glyphicon glyphicon-search"></i>
                </a>
            </span>
        </div>
    </div>
</div>
<div class='extra'>
    <div class="form-group-sm col-sm-12">
        {{ Form::label('observacao', 'Observação:', ['class'=>'col-sm-6 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
        {{ Form::label('entregapontoreferencia', 'Referência:', ['class'=>'col-sm-6 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    </div>
    <div class="form-group col-sm-12">
        <div class="col-sm-6">
            {{ Form::text('observacao',null,['class'=>'form-control input-sm', 'id' => 'observacao']) }}
        </div>
        <div class="col-sm-6">
            {{ Form::text('entregapontoreferencia',null,['class'=>'form-control input-sm']) }}
        </div>
    </div>
</div>
<div class="form-group-sm col-sm-12">
    {{ Form::label('pedidooperacao_id', 'Operação:', ['class'=>'col-sm-3 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('entregasetor_id', 'Setor:', ['class'=>'col-sm-3 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('colaborador_id', 'Colaborador:', ['class'=>'col-sm-3 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
    {{ Form::label('condicaopagamento_id', 'Forma de Pgto:', ['class'=>'col-sm-3 labelTop control-label input-sm', 'style' => 'text-align: left !important;']) }}
</div>
<div class="form-group col-sm-12">
    <div class="col-sm-3">
        {{ Form::select('pedidooperacao_id', $operacoes, null, ['class' => 'form-control selectChosen']) }}
    </div>
    <div class="col-sm-3">
        {{ Form::select('entregasetor_id', [], null, ['class' => 'form-control selectChosen', 'id' => 'entregasetor_id']) }}
    </div>
    <div class="col-sm-3">
        {{ Form::select('colaborador_id', [], null, ['class' => 'form-control selectChosenClear', 'id' => 'colaborador_id']) }}
    </div>
    <div class="col-sm-3">
        {{ Form::select('condicaopagamento_id', [], null, ['class' => 'form-control selectChosen', 'id' => 'condicaopagamento_id']) }}
    </div>
</div>

<div class="form-group crud_space">
    <div class="col-sm-7">
    </div>
</div>