@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Contas a Pagar</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Contas a Pagar</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    {{ Form::select('empresa_id',$empresas,null,['id' => 'empresa_id','class'=>'form-control selectChosen input-sm', 'multiple', 'data-placeholder' => 'Selecione']) }}
                                                </div>
                                                {{ Form::label('cliente_id', 'Fornecedor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    {{ Form::select('cliente_id',[],null,['id' => 'cliente_id','class'=>'form-control input-sm', 'placeholder' => 'Buscar Fornecedor', 'data-selectize-value' => '[]']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{Form::text('datainicio', null, ['id' => 'datainicio', 'class' => 'input-sm form-control generalDatePicker'])}}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>  
                                                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group generalDatePicker">
                                                        {{Form::text('datafim', null, (['id' => 'datafim', 'class' => 'input-sm form-control generalDatePicker']))}}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{Form::label('tipo', "Tipo Filtro:", ['class' => 'col-sm-1 control-label input-sm'])}}
                                                <div class="col-sm-2">
                                                    {{Form::select('tipo', ['E' => 'Emissão', 'V' => 'Vencimento', 'P' => 'Pagamento'], null, ['class' =>'selectChosen'])}}
                                                </div>
                                                {{ Form::label('situacao', 'Situação:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('situacao',[2 => 'Todas', 1 => 'Pagas', 0 => 'Não Pagas'],null,['id' => 'situacao','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space"> 
                                                {{ Form::label('ordem', 'Ordenar Por:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2 radio">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                    {{Form::radio('ordem', 'D' , true)}} <label id="ordemData"> Emissão </label>
                                                    <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                    {{Form::radio('ordem', 'C' , false)}} <label > Fornecedor </label>
                                                </div>
                                                <div class="col-sm-2 col-sm-offset-1">
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
    var cliente_id = '';
    $("#tipo").on('change', function() {
        if($(this).val() == 'P'){
                $("#ordemData").html('Pagamento');
            $("#situacao").val('1').prop('disabled', true).trigger('chosen:updated');
        } else {
            if($(this).val() == 'V')
                $("#ordemData").html('Vencimento');
            else 
                $("#ordemData").html('Emissão');
            $("#situacao").val('2').prop('disabled', false).trigger('chosen:updated');
        }
    });
    $("#btnLimpar").on('click', function() {
        $("#empresa_id").val('');
        var select = $("#cliente_id").selectize()[0].selectize;
        $("input.generalDatePicker").val(dataAtual());
        select.clearOptions();
        $("#tipo").val('E');
        $("#situacao").val(2).prop('disabled', false).trigger('chosen:updated');
        $(".selectChosen").trigger('chosen:updated');
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
        }, false)
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
        var url = root + '/report.contaspagar';
        if(typeof pdf != 'undefined' && pdf)
            url += '.pdf';
        url += '?empresa_id=:empresa_id&cliente_id=:cliente_id&datainicio=:datainicio&datafim=:datafim&tipo=:tipo&situacao=:situacao&ordem=:ordem';
        var empresa_id = $("#empresa_id").val();
        empresa_id = empresa_id == 'null' || empresa_id == null ? '' : empresa_id;
        url = url.replace(':empresa_id', empresa_id);
        url = url.replace(':cliente_id', cliente_id);
        url = url.replace(':datainicio', $("#datainicio").val());
        url = url.replace(':datafim', $("#datafim").val());
        url = url.replace(':tipo', $("#tipo").val());
        url = url.replace(':situacao', $("#situacao").val());
        url = url.replace(':ordem', $("#ordem:checked").val());
        callback(url);
    }
    $(document).ready(function() {
        // $("#cliente_id_chosen .chosen-search input").on('keyup', function() {
        //     var that = this;
        //     setTimeout(function(){
        //         var caractersSearch =  $(that).val();
        //         var url = root + '/api/searchClientePedido?q=' + caractersSearch;
        //         if(!isEmpty(caractersSearch)){
        //             ajaxGenerator(url, "GET", function(json){
        //                 if(typeof(json == 'object')){
        //                     var select = $("#cliente_id");
        //                     select.empty();
        //                     var data = json.data;
        //                     console.error(data.length);
        //                     if(data.length > 0) {   
        //                         $.each(data, function (i, el) {
        //                             select.append('<option value="' + el.id + '">' + el.nome + '</option>');
        //                         });
        //                     } else {
        //                         select.append('<option value="">Selecione</option>')
        //                     }
        //                     select.trigger('chosen:updated');
        //                 }
        //             });
        //             $(that).val(caractersSearch);
        //         }
        //     }, 1000);
        // });
        //configura o plugin selectize para a busca de cliente pelo nome
        $("#cliente_id").selectize({
            valueField: "id",
            labelField: "nome",
            searchField: ["nome"],
            maxOptions: 10,
            hideSelected: true,
            options: [],
            create: false,
            render: {
                option: function (item, escape) {
                    return "<div>" + escape(item.nome) + ' - <b>' + escape(item.nome_informal) + "</b></div>";
                }
            },
            optgroups: [
            {value: "cliente", label: "Fornecedores"}
            ],
            optgroupField: "class",
            optgroupOrder: ["cliente"],
            load: function (query, callback) {
                var select = $("#cliente_id").selectize()[0].selectize;
                select.clearOptions();
                var empresa_id = $("#empresa_id").val();
                empresa_id = empresa_id == 'null' || empresa_id == null? '0' : empresa_id;
                var url = root + "/api/searchFornecedoresEmpresaUser/" + empresa_id + "?q=" + query;
                ajaxGenerator(url, 'GET', function (res) {
                    callback(res.data);
                }, function (data) {
                    console.log(data);
                    callback();
                })
            },
            onChange: function (data) {
                var select = $("#cliente_id").selectize()[0].selectize;
                if (typeof select.getItem(this.items[0]).context === "object") {
                    cliente_id = select.getValue();
                } else {
                    cliente_id = '';
                }
            }
        });
    });
</script>
@endsection