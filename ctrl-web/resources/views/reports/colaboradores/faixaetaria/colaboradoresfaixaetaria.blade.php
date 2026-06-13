@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Familiares por Faixa Etária</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Familiares por Faixa Etária</a></li>
                        </ul>
                        <!-- /.box-header -->
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-11 col-sm-offset-2">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {!! Form::label('empresa_id', 'Empresas:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {{Form::select('empresa_id', $empresas, null, ['id' => 'empresa_id', 'class' => 'selectChosen input-sm form-control', 'multiple','data-placeholder' => 'Selecione'])}}
                                                </div>
                                                {!! Form::label('idade', 'Idade até ', ['class'=>'col-sm-1 control-label input-sm', 'style' => 'margin-right: -24px']) !!}
                                                <div class="col-sm-2">
                                                    <div class="col-sm-6">
                                                        {{Form::text('idade', null, ['id' => 'idade', 'class' => 'number input-sm form-control'])}}
                                                    </div>
                                                    {!! Form::label('idade', 'anos.', ['class'=>'col-sm-2 control-label input-sm', 'style' => 'margin-left: -14px']) !!}
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
        setTimeout(function () {
            $(".selectChosen").val('').trigger('chosen:updated');
        }, 100);
        $("#idade").val(0);
    });
    $("#btnIframe").on('click', function() {
        url = root + '/report.colaboradoresfaixaetaria';
        url += '?idade=:idade&empresa_id=:empresa_id'
        var empresa_id = $("#empresa_id").val();
        empresa_id = empresa_id == 'null' || empresa_id == null ? '' : empresa_id;
        url = url.replace(':empresa_id', empresa_id);
        url = url.replace(':idade', $("#idade").val());
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src',url);
    });
    $("#btnGerarPDF").on('click', function() {
        url = root + '/report.colaboradoresfaixaetaria.pdf';
        url += '?idade=:idade&empresa_id=:empresa_id'
        var empresa_id = $("#empresa_id").val();
        empresa_id = empresa_id == 'null' || empresa_id == null ? '' : empresa_id;
        url = url.replace(':empresa_id', empresa_id);
        url = url.replace(':idade', $("#idade").val());
        window.open(url, "_blank")
    });
    $("#idade").val('0').attr('maxlength', 3);
</script>
@endsection