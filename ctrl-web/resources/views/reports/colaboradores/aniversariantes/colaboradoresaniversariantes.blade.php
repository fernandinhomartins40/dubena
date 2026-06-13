@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="nav-tabs-custom">
                        <div class="panel-heading">
                            <h3 class="box-title">Colaboradores Aniversariantes</h3>
                        </div>
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Colaboradores</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Familiares</a></li>
                        </ul>
                        <!-- /.box-header -->
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {!! Form::label('empresa_id', 'Empresas:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {{Form::select('empresa_id', $empresas, null, ['id' => 'empresa_id', 'class' => 'selectChosen input-sm form-control', 'multiple','data-placeholder' => 'Selecione'])}}
                                                </div>
                                                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button type="button" id='btnGerarPDF' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
                                                    <button id="btnIframe" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                </div>
                                            </div> 
                                            {{ Form::close() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_2">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {!! Form::label('familiaresempresa_id', 'Empresas:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {{Form::select('familiaresempresa_id', $empresas, null, ['id' => 'familiaresempresa_id', 'class' => 'selectChosen input-sm form-control', 'multiple','data-placeholder' => 'Selecione'])}}
                                                </div>
                                                {{ Form::label('familiaresdatainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker">
                                                        {{ Form::datetime('familiaresdatainicio',null,['id'=>'familiaresdatainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('familiaresdatafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker">
                                                        {{ Form::datetime('familiaresdatafim',null,['id'=>'familiaresdatafim','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="reset" id="familiaresBtnLimpar" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button type="button" id='familiaresBtnGerarPDF' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
                                                    <button id="familiaresBtnIframe" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                </div>
                                            </div> 
                                            {{ Form::close() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.content-wrapper -->
                </div>
            </div>
        </div>
    </div>
</div>
@include('general.modal_report_iframe')

<script type="text/javascript">
    $("#btnLimpar").on('click', function() {
        setTimeout(function () {
            $("#empresa_id").val('').trigger('chosen:updated');
            $("#datainicio, #datafim").val(dataAtual());
        }, 100);
    });
    $("#btnGerarPDF").on('click', function () {
        setUrl(function () {
            window.open(url, '_blank');
        }, true);
    });
    $("#btnIframe").on('click', function() {
        setUrl(function () {
            $("#popup_relatorio").modal('show');
            $("#iFrameReport").attr('src',url);
        });
    });
    $("#familiaresBtnLimpar").on('click', function() {
        setTimeout(function () {
            $("#familiaresempresa_id").val('').trigger('chosen:updated');
            $("#familiaresdatainicio, #familiaresdatafim").val(dataAtual());
        }, 100);
    });
    $("#familiaresBtnIframe").on('click', function() {
        setUrlFamiliares(function () {
            $("#popup_relatorio").modal('show');
            $("#iFrameReport").attr('src',url);
        });
    });
    $("#familiaresBtnGerarPDF").on('click', function() {
        setUrlFamiliares(function () {
            window.open(url, '_blank');
        }, true);
    });
    function setUrl(callback, pdf) {
        if(isEmpty($("#datainicio").val())) {
            bootbox.alert('O campo Data Início é obrigatório');
            return;
        }
        if(isEmpty($("#datafim").val())) {
            bootbox.alert('O campo Data Fim é obrigatório');
            return;
        }
        
        url = root + '/report.colaboradoresaniversariantes';
        if(typeof pdf != 'undefined')
            url += '.pdf';
        url += '?datainicio=:datainicio&datafim=:datafim&empresa_id=:empresa_id'
        url = url.replace(':datainicio', $("#datainicio").val());
        url = url.replace(':datafim', $("#datafim").val());
        var empresa_id = $("#empresa_id").val();
        empresa_id = empresa_id == 'null' || empresa_id == null? '' : empresa_id;
        url = url.replace(':empresa_id', empresa_id);
        callback();
    }
    function setUrlFamiliares(callback, pdf) {
        if(isEmpty($("#familiaresdatainicio").val())) {
            bootbox.alert('O campo Data Início é obrigatório');
            return;
        }
        if(isEmpty($("#familiaresdatafim").val())) {
            bootbox.alert('O campo Data Fim é obrigatório');
            return;
        }
        url = root + '/report.colaboradoresaniversariantes.familiares';
        if(typeof pdf != 'undefined')
            url += '.pdf';
        url += '?datainicio=:datainicio&datafim=:datafim&empresa_id=:empresa_id'
        url = url.replace(':datainicio', $("#familiaresdatainicio").val());
        url = url.replace(':datafim', $("#familiaresdatafim").val());
        var empresa_id = $("#familiaresempresa_id").val();
        empresa_id = empresa_id == 'null' || empresa_id == null? '' : empresa_id;
        url = url.replace(':empresa_id', empresa_id);
        callback();
    }
</script>
@endsection