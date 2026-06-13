@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Controle de Comodatos</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Controle de Comodatos</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-6 col-sm-offset-5">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmReport','class'=>'form-horizontal'])}}
                                            <div class="col-sm-1">
                                                <button type="button" id='btnGerarPDF' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
                                            </div>
                                            <div class="col-sm-1">
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
@include('general.modal_report_iframe')
<script type="text/javascript">

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
        var url = root + '/report.controlecomodato';
        if(typeof pdf != 'undefined')
            url += '.pdf';
        url += '?report'
        callback(url);
    }
</script>
@endsection