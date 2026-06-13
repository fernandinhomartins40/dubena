@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Comodatos Ativos/Inativos</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Comodatos Ativos/Inativos</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmReport','class'=>'form-horizontal'])}}
                                            <input type="hidden" id="pdf">
                                            <div class="form-group crud_space">
                                                {!! Form::label('tipo', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {{ Form::radio('tipo', '0', true) }} Revenda para Cliente PJ<br />
                                                    {{ Form::radio('tipo', '1', false) }} Revenda para Cliente PF<br />
                                                    {{ Form::radio('tipo', '3', false) }} Revenda para Clientes<br />
                                                    {{ Form::radio('tipo', '2', false) }} Distribuidora para Revenda
                                                </div>
                                                <!-- {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{ Form::text('datainicio',null,['id' => 'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div> -->
                                                {{ Form::label('datafim', 'Vencimento Até:', ['class'=>'col-sm-2 control-label input-sm', 'id' => 'labelData']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{ Form::text('datafim',null,['id' => 'datafim','class'=>'form-control generalDatePicker input-sm']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>    
                                                    </div>
                                                    {{ Form::label('todas_datas', 'ou Todos Vencimentos:', ['class'=>'control-label input-sm']) }}
                                                    {{ Form::checkbox('todas_datas') }}
                                                </div>
                                                {{ Form::label('ativo', 'Ativo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('ativo')}}
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
        $(".selectChosen").val('').trigger('chosen:updated');
        $("#datainicio, #datafim").val(dataAtual());
    });
    $("#ativo").on('change', function () {
        if ($(this).is(':checked'))
            var text = 'Vencimento Até: ';
        else
            var text = 'Inativos Até: ';
        $("#labelData").text(text); 
    });
    $("#btnGerarPDF").on('click', function () {
        setUrl(function (url) {
            window.open(url, '_blank');
        }, true);
    });
    $("#btnIframe").on('click', function () {
        setUrl(function (url) {
            $("#popup_relatorio").modal('show');
            $("#iFrameReport").attr('src',url);
        });
    });
    function setUrl(callback, pdf) {
        // if(isEmpty($("#datainicio").val())) {
        //     bootbox.alert('O campo Data Início é obrigatório');
        //     return;
        // }
        if(isEmpty($("#datafim").val())) {
            bootbox.alert('O campo Data Fim é obrigatório');
            return;
        }
        var url = root + '/report.comodatosativos';
        if(typeof pdf != 'undefined')
            url += '.pdf';
        url += '?datafim=:datafim&tipo=:tipo&ativo=:ativo&todas_datas=:todas_datas';
        url = url.replace(':tipo', $("#tipo:checked").val());
        url = url.replace(':ativo', typeof $("#ativo:checked").val() != 'undefined');
        url = url.replace(':todas_datas', typeof $("#todas_datas:checked").val() != 'undefined');
        url = url.replace(':datainicio', $("#datainicio").val());
        url = url.replace(':datafim', $("#datafim").val());
        callback(url);
    }
</script>
@endsection