@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Giro de Comodatos</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Giro de Comodatos</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmReport','class'=>'form-horizontal'])}}
                                            <input type="hidden" id="pdf">
                                            <div class="form-group crud_space">
                                                {!! Form::label('tipo', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-11">
                                                    <div class="col-sm-4">
                                                        {{ Form::radio('tipo', '0', true, ['onclick' => 'searchClientes(0)']) }} Revenda para Cliente PJ<br />
                                                        {{ Form::radio('tipo', '1', false, ['onclick' => 'searchClientes(1)']) }} Revenda para Cliente PF<br />
                                                        {{ Form::radio('tipo', '2', false, ['onclick' => 'searchClientes(2)']) }} Distribuidora para Revenda
                                                    </div>
                                                    {{ Form::label('maior', 'Giro Maior que:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('maior',null,['id' => 'maior','class'=>'form-control floatNumber input-sm']) }}  
                                                    </div>
                                                    {{ Form::label('menor', 'Giro Menor que:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('menor',null,['id' => 'menor','class'=>'form-control floatNumber input-sm']) }}  
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group-crud_space">
                                                {{ Form::label('cliente_id', 'Cliente:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                                                <div class="col-sm-11">
                                                    <div class="col-sm-4">
                                                        {{ Form::select('cliente_id', [], null,['id' => 'cliente_id','class'=>'form-control selectChosen input-sm']) }}
                                                    </div>
                                                    {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePicker">
                                                            {{ Form::text('datainicio',null,['id' => 'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                            <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                            </span>    
                                                        </div>
                                                    </div>
                                                    {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePicker">
                                                            {{ Form::text('datafim',null,['id' => 'datafim','class'=>'form-control generalDatePicker input-sm']) }}
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
            var text = 'Ativos Até: ';
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
        if(isEmpty($("#datainicio").val())) {
            bootbox.alert('O campo Data Início é obrigatório');
            return;
        }
        if(isEmpty($("#datafim").val())) {
            bootbox.alert('O campo Data Fim é obrigatório');
            return;
        }
        var maior = '';
        var menor = '';
        if(!isEmpty($("#maior").val()) && !isEmpty($("#menor").val())) {
            var maior = parseFloat($("#maior").val().replace(',', '.'));
            var menor = parseFloat($("#menor").val().replace(',', '.'));
            if(maior > menor){
                bootbox.alert('O campo Maior que não pode ser menor que o campo Menor que');
                return;
            }
        } else if(!isEmpty($("#menor").val())) {
            var menor = parseFloat($("#menor").val().replace(',', '.'));
        } else if(!isEmpty($("#maior").val())) {
            var maior = parseFloat($("#maior").val().replace(',', '.'));
        }
        var url = root + '/report.girocomodato';
        if(typeof pdf != 'undefined')
            url += '.pdf';
        url += '?datafim=:datafim&datainicio=:datainicio&tipo=:tipo&cliente_id=:cliente_id&maior=:maior&menor=:menor';
        url = url.replace(':tipo', $("#tipo:checked").val());
        var cliente_id = $("#cliente_id").val() != null ? $("#cliente_id").val() : '';
        url = url.replace(':cliente_id', cliente_id);
        url = url.replace(':datainicio', $("#datainicio").val());
        url = url.replace(':datafim', $("#datafim").val());
        url = url.replace(':menor', menor);
        url = url.replace(':maior', maior);
        callback(url);
    }

    function searchClientes(tipo) {
        var url = root + '/api/searchClientesComodato/' + tipo;
        $("#cliente_id").empty();
        ajaxGenerator(url, "GET", function (data) {
            if(typeof data == 'array'|| typeof data == 'object') {
                html = "<option value=''>Selecione</option>";
                $.each(data, function(i, el) {
                    html += "<option value='" + el.id + "'>" + el.nome + "</option>"; 
                });
                $("#cliente_id").append(html).trigger('chosen:updated');
            } else {
                bootbox.alert('Erro ao buscar clientes: ' + data);
            }
        });
        $("#cliente_id").trigger('chosen:updated');
    }
    $(document).ready(function() {
        searchClientes(0);
    });
</script>
@endsection