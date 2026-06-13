@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Funcionários de Convênio</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Funcionários de Convênio</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-11 col-sm-offset-1">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('convenio_id', 'Convênio:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('convenio_id',$convenios,null,['id' => 'convenio_id','class'=>'form-control selectChosen input-sm']) }}
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
    $("#btnLimpar").on('click', function() {
        $(".selectChosen").val('').trigger('chosen:updated');
    });
    $("#btnGerarPDF").on('click', function () {
        var url = root + '/report.conveniofuncionarios.pdf?convenio_id=:convenio_id';
        var convenio_id = $("#convenio_id").val();
        convenio_id = convenio_id == 'null' || convenio_id == null ? '' : convenio_id;
        url = url.replace(':convenio_id', convenio_id);
        window.open(url, '_blank');
    });
    $("#btnIframe").on('click', function () {
        var url = root + '/report.conveniofuncionarios?convenio_id=:convenio_id';
        var convenio_id = $("#convenio_id").val();
        convenio_id = convenio_id == 'null' || convenio_id == null ? '' : convenio_id;
        url = url.replace(':convenio_id', convenio_id);
        $("#popup_relatorio").modal('show');
        $("#iFrameReport").attr('src',url);
    });
</script>
@endsection