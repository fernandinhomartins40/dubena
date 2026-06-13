@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Convênios</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Convênios</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-11 col-sm-offset-2">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {!! Form::label('status', 'Situação:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {{Form::select('status', [ 2 => 'Ambos', 1 => 'Ativo', 0 => 'Inativo'], null, ['class' => 'selectChosen'])}}
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
        $(".selectChosen").val(2).trigger('chosen:updated');
    });
    $("#btnIframe").on('click', function() {
        var url = root + '/report.convenio?status=:status';
        var status = $("#status").val();
        url = url.replace(':status', status);
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src',url);
    });
    $("#btnGerarPDF").on('click', function () {
        var url = root + '/report.convenio.pdf?status=:status';
        var status = $("#status").val();
        url = url.replace(':status', status);
        window.open(url, '_blank');
    });
</script>
@endsection