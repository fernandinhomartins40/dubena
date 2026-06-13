@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Movimentações de Caixas</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Movimentações de Caixas</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmReport','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                            {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('empresa_id',$empresas,null,['id' => 'empresa_id','class'=>'form-control selectChosen input-sm', 'data-placeholder' => "Selecione" ]) }}
                                                </div>
                                                {{ Form::label('conta_id', 'Contas:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('conta_id',$contas,null,['id' => 'conta_id','class'=>'form-control selectChosen input-sm', 'multiple', 'data-placeholder' => "Selecione" ]) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{ Form::text('datainicio',null,['id' => 'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>    
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-3 control-label input-sm', 'id' => 'labelData']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{ Form::text('datafim',null,['id' => 'datafim','class'=>'form-control generalDatePicker input-sm']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>    
                                                    </div>
                                                </div>
                                                {{ Form::label('ativo', 'Ativo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="col-sm-5 checkbox">
                                                        {{ Form::checkbox('ativo', true, ['id' => 'ativo']) }}
                                                    </div>
                                                    <div class="col-sm-7">
                                                        <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                        <!-- <button type="button" id='btnGerarPDF' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button> -->
                                                        <button id="btnIframe" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                    </div>
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
        $("#conta_id").empty();
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
        if(isEmpty($("#datainicio").val())) {
            bootbox.alert('O campo Data Início é obrigatório');
            return;
        }
        if(isEmpty($("#datafim").val())) {
            bootbox.alert('O campo Data Fim é obrigatório');
            return;
        }
        if(isEmpty($("#empresa_id").val())) {
            bootbox.alert('O campo Empresa é obrigatório');
            return;
        }
        var url = root + '/report.fluxocaixa';
        if(typeof pdf != 'undefined')
            url += '.pdf';
        url += '?datafim=:datafim&datainicio=:datainicio&conta_id=:conta_id&empresa_id=:empresa_id&ativo=:ativo';
        url = url.replace(':tipo', $("#tipo:checked").val());
        var conta_id = $("#conta_id").val();
        conta_id = conta_id == 'null' || conta_id == null? '' : conta_id;
        url = url.replace(':conta_id', conta_id);
        url = url.replace(':datainicio', $("#datainicio").val());
        url = url.replace(':datafim', $("#datafim").val());
        var empresa_id = $("#empresa_id").val();
        empresa_id = empresa_id == 'null' || empresa_id == null? '' : empresa_id;
        url = url.replace(':empresa_id', empresa_id);
        url = url.replace(':ativo', typeof $("#ativo:checked").val() != 'undefined');
        callback(url);
    }

    $("#empresa_id").on('change', function () {
        var empresa_id = $("#empresa_id").val();
        var url = root + "/api/searchContasByEmpresa/" + empresa_id;
        $("#conta_id").empty();
            var html = '';
        ajaxGenerator(url, 'GET', function  (data) {
            if(data.length > 0){
                $.each(data, function (i, el) {
                    html += "<option value=" + el.id + ">" + el.descricao + "</option>";
                });
            }
        });
        $("#conta_id").append(html).trigger('chosen:updated');
    });

</script>
@endsection