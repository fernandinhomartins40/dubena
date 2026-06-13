@extends('layouts.mainmenu')
@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($colaborador))
            {{ Form::model($colaborador, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('colaborador.update', $colaborador->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'colaborador.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Colaborador</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Endereço</a></li>
                            <li class=""><a href="#tab_3" data-toggle="tab">Contatos</a></li>
                            <li class=""><a href="#tab_4" data-toggle="tab">Família</a></li>
                            <li class=""><a href="#tab_5" data-toggle="tab">Férias</a></li>
                            <li class=""><a href="#tab_6" data-toggle="tab">Exames</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('tipopessoa_id', 'Tipo de Pessoa:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('tipopessoa_id', $tipopessoas, $tipopessoa, ['class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'mudarTipoPessoa();', 'id'=>'tipopessoa_id']) !!}
                                                </div>
                                                {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-1 checkbox">

                                                    {{ Form::checkbox('ativo') }}
                                                </div>
                                            </div>
                                            <div id='' class='divTipoPessoa'>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('nome', 'Nome/Razão Social:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-9">
                                                        {!! Form::text('nome',null,['class'=>'form-control input-sm']) !!}
                                                    </div>
                                                </div>
                                                <div id="" class="divPessoaFisica">
                                                    <div class="form-group crud_space">
                                                        {!! Form::label('cpf', 'CPF:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            {!! Form::text('cpf',null,['class'=>'form-control input-sm cpf','onKeyPress' => ""]) !!}
                                                        </div>
                                                        {!! Form::label('rg', 'RG:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            {!! Form::text('rg',null,['class'=>'form-control input-sm rg']) !!}
                                                        </div>
                                                        {!! Form::label('sexo', 'Sexo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            {!! Form::select('sexo',["F"=>"Feminino","M"=>"Masculino"], null, ['class' => 'form-control selectDisableSearch']) !!}
                                                        </div>
                                                    </div>
                                                    <div class="form-group crud_space">
                                                        {!! Form::label('ctps', 'CTPS:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                        <div class="col-sm-9">
                                                            {!! Form::text('ctps',null,['class'=>'form-control input-sm ctps']) !!}
                                                        </div>
                                                    </div>
                                                    <div class="form-group crud_space">
                                                        {!! Form::label('estadocivil_id', 'Estado Civil:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                        <div class="col-sm-9">
                                                            {!! Form::select('estadocivil_id', $estadocivils, null, ['class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) !!}
                                                        </div>
                                                    </div>
                                                    <div class="form-group crud_space">
                                                        {!! Form::label('datanascimento', 'Nascimento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            <div class="input-group generalDatePickerDefaultDateFalse">
                                                                {!! Form::text('datanascimento',null,['class'=>'form-control input-sm generalDatePickerDefaultDateFalse']) !!}
                                                                <span class="input-group-addon">
                                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        {!! Form::label('dataadmissao', 'Admissão:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            <div class="input-group generalDatePickerDefaultDateFalse">
                                                                {!! Form::text('dataadmissao',null,['class'=>'form-control input-sm generalDatePickerDefaultDateFalse']) !!}
                                                                <span class="input-group-addon">
                                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        {!! Form::label('datadesligamento', 'Desligamento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            <div class="input-group generalDatePickerDefaultDateFalse">
                                                                {!! Form::text('datadesligamento',null,['class'=>'form-control input-sm generalDatePickerDefaultDateFalse']) !!}
                                                                <span class="input-group-addon">
                                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="" class="divPessoaJuridica">
                                                    <div class="form-group crud_space">
                                                        {!! Form::label('cnpj', 'CNPJ:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            {!! Form::text('cnpj',null,['class'=>'form-control input-sm cnpj','onKeyPress' => ""]) !!}
                                                        </div>
                                                        {!! Form::label('inscricao_estadual', 'Insc.Est.:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            {!! Form::text('inscricao_estadual',null,['class'=>'form-control input-sm']) !!}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('cargo_id', 'Cargo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-9">
                                                    {!! Form::select('cargo_id', $cargos, null, ['class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('observacoes', 'Observações:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-9">
                                                    {!! Form::text('observacoes',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_2">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            @include('general.endereco_form_partial')
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_3">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('email', 'E-mail:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-8">
                                                    {!! Form::text('email',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="col-md-8 col-md-offset-3 margTop_10">
                                                <div class="col-md-4">
                                                    {!! Form::select('telefonetipo_id', $telefonetipos, null, ['id'=>'telefonetipo_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                                <div class="col-md-3">
                                                    {!! Form::text('telefone',null,['id'=>'telefone', 'placeholder'=>'Telefone', 'class'=>'form-control input-sm telefone']) !!}
                                                </div>
                                                <button type="button" id="btnAddFone" class="btn btn-xs btn-nw-buscas" onclick="addFone();">Adicionar</button>
                                            </div>
                                            <div class="col-md-8 col-md-offset-3">
                                                {{Form::hidden('telefones',"", ['id'=>'telefones'])}}
                                                <table id="tblTelefones" class="table table-bordered table-hover table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th></th>
                                                            <th>Tipo Telefone</th>
                                                            <th>Número</th>
                                                            <th>Operação</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="colaboradors-list" name="colaboradors-list">
                                                        @if(isset($colaborador->telefones))
                                                        @foreach ($colaborador->telefones as $telefone)
                                                        <tr id="fone{{$telefone->telefonetipo_id}}">
                                                            <td>{{$telefone->telefonetipo_id}}</td>
                                                            <td>{{$telefone->telefonetipo->descricao}}</td>
                                                            <td>{{$telefone->telefone}}</td>
                                                            <td><button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverTelefone'>Remover</button></td>
                                                        </tr>
                                                        @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div><!-- /.box -->
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_4">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="col-md-8 col-md-offset-3">
                                                <div class="col-sm-3">
                                                    {!! Form::select('parentesco_id', $parentescos, null, ['id'=>'parentesco_id', 'class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) !!}
                                                </div>
                                                <div class="col-md-3">
                                                    {!! Form::text('familianome',null,['id'=>'familianome', 'placeholder'=>'Nome', 'class'=>'form-control input-sm']) !!}
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="input-group generalDatePickerDefaultDateFalse">
                                                        {!! Form::datetime('familiadatanascimento',null,['class'=>'form-control input-sm generalDatePickerDefaultDateFalse', 'placeholder'=>'Data nascimento', 'id'=>'familiadatanascimento']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <button type="button" id="btnAddFamilia" class="btn btn-xs btn-nw-buscas" onclick="addFamilia();">Adicionar</button>
                                            </div>
                                            <div class="col-md-8 col-md-offset-3">
                                                {{Form::hidden('colaboradorfamilias',"", ['id'=>'colaboradorfamilias'])}}
                                                <table id="tblColaboradorfamilias" class="table table-bordered table-hover table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th></th>
                                                            <th>Parentesco</th>
                                                            <th>Nome</th>
                                                            <th>Data Nascimento</th>
                                                            <th>Operação</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="colaboradors-list" name="colaboradors-list">
                                                        @if(isset($colaborador))
                                                        @foreach ($colaborador->colaboradorfamilias as $colaboradorfamilia)
                                                        <tr id="fone{{$colaboradorfamilia->id}}">
                                                            <td>{{$colaboradorfamilia->parentesco_id}}</td>
                                                            <td>{{$colaboradorfamilia->parentesco->descricao}}</td>
                                                            <td>{{$colaboradorfamilia->nome}}</td>
                                                            <td>{{$colaboradorfamilia->datanascimento}}</td>
                                                            <td><button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverFamilia'>Remover</button></td>
                                                        </tr>
                                                        @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div><!-- /.box -->
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_5">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="col-md-8 col-md-offset-3">
                                                <div class="col-md-3">
                                                    <div class="input-group generalDatePickerDefaultDateFalse">
                                                        {!! Form::text('feriasdatainicio',null,['class'=>'form-control input-sm generalDatePickerDefaultDateFalse', 'placeholder'=>'Data início', 'id'=>'feriasdatainicio']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    {!! Form::text('feriasdias',null,['id'=>'feriasdias', 'placeholder'=>'Dias', 'class'=>'form-control input-sm number']) !!}
                                                </div>
                                                {!! Form::label('feriasgozada', 'Férias gozada:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-md-1 checkbox">
                                                    {{ Form::checkbox('feriasgozada') }}
                                                </div>
                                                <button type="button" id="btnAddFerias" class="btn btn-xs btn-nw-buscas" onclick="addFerias();">Adicionar</button>
                                                <button type="button" id="btnEditFerias" class="btn btn-xs btn-nw-buscas" style="display: none;"  onclick="addFerias(true);">Atualizar</button>
                                                <button type="button" id="btnCancelaEditFerias" class="btn btn-xs btn-nw-registro" style="display: none;">Cancelar</button>
                                            </div>
                                            <div class="col-md-8 col-md-offset-3">
                                                {{Form::hidden('colaboradorferias',"", ['id'=>'colaboradorferias'])}}
                                                <table id="tblColaboradorferias" class="table table-bordered table-hover table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th>Data Início</th>
                                                            <th>Qtde Dias</th>
                                                            <th>Férias Gozada</th>
                                                            <th>Operação</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="colaboradors-list" name="colaboradors-list">
                                                        @if(isset($colaborador))
                                                        @foreach ($colaborador->colaboradorferias as $colaboradorferias)
                                                        <tr id="fone{{$colaboradorferias->id}}">
                                                            <td>{{$colaboradorferias->datainicio}}</td>
                                                            <td>{{$colaboradorferias->dias}}</td>
                                                            <td>{{$colaboradorferias->gozada == 1 ? "Sim" : "Não"}}</td>
                                                            <td>
                                                                <button type='button' class='btn btn-nw-geral btn-xs' id='btnEditarFerias'>Editar</button>
                                                                <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverFerias'>Remover</button>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div><!-- /.box -->
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_6">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="col-md-12 col-md-offset-2">
                                                <div class="col-sm-2">
                                                    {!! Form::select('tipoexame_id', $tiposexames, null, ['id'=>'tipoexame_id', 'class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) !!}
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="input-group generalDateTimePickerDefaultDateFalse">
                                                        {!! Form::datetime('dataexame',null,['class'=>'form-control input-sm generalDateTimePickerDefaultDateFalse', 'placeholder'=>'Data', 'id'=>'dataexame']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="input-group generalDateTimePickerDefaultDateFalse">
                                                        {!! Form::datetime('vencimentoexame',null,['class'=>'form-control input-sm generalDateTimePickerDefaultDateFalse', 'placeholder'=>'Vencimento', 'id'=>'vencimentoexame']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('alerta', 'Alerta:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-md-1 checkbox">
                                                    {{ Form::checkbox('alerta') }}
                                                </div>
                                                <button type="button" id="btnAddExame" class="btn btn-xs btn-nw-buscas" onclick="addExame();">Adicionar</button>
                                            </div>
                                            <div class="col-md-10 col-md-offset-2">
                                                {{Form::hidden('colaboradorexames',"", ['id'=>'colaboradorexames'])}}
                                                <table id="tblColaboradorexames" class="table table-bordered table-hover table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th></th>
                                                            <th>Tipo Exame</th>
                                                            <th>Data</th>
                                                            <th>Vencimento</th>
                                                            <th>Alerta</th>
                                                            <th>Operação</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="colaboradors-list" name="colaboradors-list">
                                                        @if(isset($colaborador))
                                                        @foreach ($colaborador->colaboradorexames as $colaboradorexame)
                                                        <tr id="fone{{$colaboradorexame->id}}">
                                                            <td>{{$colaboradorexame->tipoexame->id}}</td>
                                                            <td>{{$colaboradorexame->tipoexame->descricao}}</td>
                                                            <td>{{$colaboradorexame->data}}</td>
                                                            <td>{{$colaboradorexame->datavencimento}}</td>
                                                            <td>{{$colaboradorexame->alerta == 1 ? "Sim" : "Não"}}</td>
                                                            <td><button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverExame'>Remover</button></td>
                                                        </tr>
                                                        @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div><!-- /.box -->
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                <a href="{{url('colaborador')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </ul><!-- /.col -->
        </div>
    </div>
</div>
@include('general.popupbairrocidade_form_partial')

<!-- page script -->
<!--<script src="{{URL::to('js/colaborador.js')}}"></script>-->
<script type="text/javascript">
    var editingRowFerias;
    function addFone() {
        if (!isInt($('#telefonetipo_id').val())) {
            bootbox.alert('Preencha o tipo de telefone.');
            return;
        }
        if ($('#telefone').val().trim() == '') {
            bootbox.alert('Preencha o telefone.');
            return;
        }
        tblFone.row.add([
            $('#telefonetipo_id').val(),
            $('#telefonetipo_id option:selected').text(),
            $('#telefone').val(),
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverTelefone'>Remover</button>"
        ]).draw(false);
        $('#telefone').val('');
    }
    function addFamilia() {
        if (!isInt($('#parentesco_id').val())) {
            bootbox.alert('Preencha o parentesco do colaborador.');
            return;
        }
        if ($('#familianome').val().trim() == '') {
            bootbox.alert('Preencha o nome do parente.');
            return;
        }
        if ($('#familiadatanascimento').val().trim() == '') {
            bootbox.alert('Preencha a data de nascimento do parente.');
            return;
        }
        tblFamilia.row.add([
            $('#parentesco_id').val(),
            $('#parentesco_id option:selected').text(),
            $('#familianome').val(),
            $('#familiadatanascimento').val(),
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverFamilia'>Remover</button>"
        ]).draw(false);
        $('#familianome').val('');
    }
    function addFerias(atualiza) {
        if ($('#feriasdatainicio').val().trim() == '') {
            bootbox.alert('Preencha a data de início das férias.');
            return;
        }
        if (!isInt($('#feriasdias').val())) {
            bootbox.alert('Preencha os dias das férias.');
            return;
        }
        var data = [
            $('#feriasdatainicio').val(),
            $('#feriasdias').val(),
            $('#feriasgozada').prop('checked') == 1 ? 'Sim' : 'Não',
            "<button type='button' class='btn btn-nw-geral btn-xs' id='btnEditarFerias'>Editar</button> " +
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverFerias'>Remover</button>"
        ];
        if (typeof atualiza !== "undefined" && atualiza){
            $("#btnEditFerias").hide();
            $("#btnCancelaEditFerias").hide();
            $("#btnAddFerias").show();
            tblFerias.row(editingRowFerias).data(data);
        } else {
            tblFerias.row.add(data).draw(false);
        }
        limpaCamposFerias();
    }
    $("#btnCancelaEditFerias").on('click', function(){
        $("#btnEditFerias").hide();
        $("#btnCancelaEditFerias").hide();
        $("#btnAddFerias").show();
       limpaCamposFerias();
    });
    function limpaCamposFerias() {
        $('#feriasdias').val('');
        $('#feriasdatainicio').val('');
        $('#feriasgozada').prop("checked", false);
    }
    function addExame() {
        if (!isInt($('#tipoexame_id').val())) {
            bootbox.alert('Preencha o tipo de exame.');
            return;
        }
        if ($('#dataexame').val().trim() == '') {
            bootbox.alert('Preencha a data do exame.');
            return;
        }
        if ($('#vencimentoexame').val().trim() == '') {
            bootbox.alert('Preencha o vencimento do exame.');
            return;
        }
        tblExames.row.add([
            $('#tipoexame_id').val(),
            $('#tipoexame_id option:selected').text(),
            $('#dataexame').val(),
            $('#vencimentoexame').val(),
            $('#alerta').prop('checked') == 1 ? 'Sim' : 'Não',
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverExame'>Remover</button>"
        ]).draw(false);
        $('#dataexame').val('');
        $('#vencimentoexame').val('');
        $('#alerta').prop("checked", false);
    }
    var confirm;
    var t;
    var root = '{{url("/")}}';
    $('.modal-wide').on('show.bs.modal', function () {
        var height = $(window).height() - 200;
        $(this).find('.modal-body').css('max-height', height);
    });
    $(".delete").on("submit", function () {
        return confirm("Quer remover o registro atual?");
    });
    $(document).ready(function ($) {
        mudarTipoPessoa();
        $('#popup_capture').on('hidden.bs.modal', function () {
            stopCapture();
        })
        $(".modal-wide").on("show.bs.modal", function () {
            var height = $(window).height() - 200;
            $(this).find(".modal-body").css("max-height", height);
        });
        tblFone = $('#tblTelefones').DataTable({
            "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [
                {
                    "targets": [0],
                    "visible": false
                }
            ]
        });
        tblFamilia = $('#tblColaboradorfamilias').DataTable({
            "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [
                {
                    "targets": [0],
                    "visible": false
                }
            ]
        });
        tblFerias = $('#tblColaboradorferias').DataTable({
            "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [{
                    "targets": [0],
                    "visible": true
                }]
        });
        tblExames = $('#tblColaboradorexames').DataTable({
            "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [{
                    "targets": [0],
                    "visible": false
                }]
        });
        $("#fmCadastro").on("submit", function () {
            var fones = [];
            tblFone.rows().every(function () {
                var d = this.data();
                fones.push(d);
            });
            $('#telefones').val(JSON.stringify(fones));
            var familias = [];
            tblFamilia.rows().every(function () {
                var d = this.data();
                familias.push(d);
            });
            $('#colaboradorfamilias').val(JSON.stringify(familias));
            var ferias = [];
            tblFerias.rows().every(function () {
                var d = this.data();
                ferias.push(d);
            });
            $('#colaboradorferias').val(JSON.stringify(ferias));
            var exames = [];
            tblExames.rows().every(function () {
                var d = this.data();
                exames.push(d);
            });
            $('#colaboradorexames').val(JSON.stringify(exames));
        });


        $('#tblTelefones').on('click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
            var telefone = $(trElem).children("td")[2];
            if ($(firstTd).text() != "") {
                if ($(this).context.id == 'btnRemoverTelefone') {
                    tblFone
                            .row($(this).parents('tr'))
                            .remove()
                            .draw();
                }
            }
            ;
        });

        $('#tblColaboradorfamilias').on('click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
            /*var telefone = $(trElem).children("td")[2];*/
            if ($(firstTd).text() != "") {
                if ($(this).context.id == 'btnRemoverFamilia') {
                    tblFamilia
                            .row($(this).parents('tr'))
                            .remove()
                            .draw();
                }
            }
            ;
        });
        $('#tblColaboradorferias').on('click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
            var qde = $(trElem).children("td")[1]; //takes the first td which would have your Id
            var feriasGozadas = $(trElem).children("td")[2]; //takes the first td which would have your Id
            /*var telefone = $(trElem).children("td")[2];*/
            if ($(firstTd).text() != "") {
                if ($(this).context.id == 'btnRemoverFerias') {
                    tblFerias
                            .row($(this).parents('tr'))
                            .remove()
                            .draw();
                } else if ($(this).context.id == 'btnEditarFerias') {
                    $("#feriasdatainicio").val($(firstTd).text()).trigger('focus');
                    $("#feriasdias").val($(qde).text());
                    $("#feriasgozada").prop("checked", $(feriasGozadas).text() === "Sim");
                    editingRowFerias = $(this).parents('tr');
                    $("#btnAddFerias").hide();
                    $("#btnCancelaEditFerias").show();
                    $("#btnEditFerias").show();
                }
            }
        });

        $('#tblColaboradorexames').on('click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
            /*var telefone = $(trElem).children("td")[2];*/
            if ($(firstTd).text() != "") {
                if ($(this).context.id == 'btnRemoverExame') {
                    tblExames
                            .row($(this).parents('tr'))
                            .remove()
                            .draw();
                }
            }
            ;
        });
    });

    $(document).ready(function() {
        @if ($errors -> any())
            carregarTelefonesErro();
            carregarFamiliasErro();
            carregarFeriasErro();
            carregarExamesErro();
        @endif
    });

    setTimeout(function () {
    @if (isset($show))
        desativarInputs();
        let ids = [
            ".btn-nw-registro", ".btn-nw-geral", ".btnBuscarEndereco", '#btnBuscarCEP', 'btn-nw-buscas',
            '.novoCadEndereco', '#btnAddFone', '#btnAddFamilia', '#btnAddFerias', '#btnAddExame', '#cpf', '#rg', '#datanascimento'
        ];
        desativarInputsEspecificos(ids);
    @endif

    }, $(document).ready());

    function carregarTelefonesErro() {
        tblFone.clear();
        contatos = JSON.parse($('#telefones').val());
        for (i = 0; i < contatos.length; i++) {
            tblFone.row.add([
                contatos[i][0],
                contatos[i][1],
                contatos[i][2],
                contatos[i][3]
            ]).draw(false);
        }
    }

    function carregarFamiliasErro() {
        tblFamilia.clear();
        familias = JSON.parse($('#colaboradorfamilias').val());
        for (i = 0; i < familias.length; i++) {
            tblFamilia.row.add([
                familias[i][0],
                familias[i][1],
                familias[i][2],
                familias[i][3],
                familias[i][4]
            ]).draw(false);
        }
    }

    function carregarFeriasErro() {
        tblFerias.clear();
        ferias = JSON.parse($('#colaboradorferias').val());
        for (i = 0; i < ferias.length; i++) {
            tblFerias.row.add([
                ferias[i][0],
                ferias[i][1],
                ferias[i][2],
                ferias[i][3]
            ]).draw(false);
        }
    }

    function carregarExamesErro() {
        tblExames.clear();
        exames = JSON.parse($('#colaboradorexames').val());
        for (i = 0; i < exames.length; i++) {
            tblExames.row.add([
                exames[i][0],
                exames[i][1],
                exames[i][2],
                exames[i][3],
                exames[i][4],
                exames[i][5]
            ]).draw(false);
        }
    }
</script>

@endsection
