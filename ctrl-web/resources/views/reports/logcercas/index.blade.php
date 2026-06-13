@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Entrada/Saída de Setores</h3>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Entrada/Saída de Setores</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'reportlogsenha','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
													<button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.logcercas')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button type="button" id='gerarPdfLogCerca' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
													<button id="btnFiltroEntregador" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
												</div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('setor_id', $setores, null, ['id' => 'setor_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('cerca_id', 'Cerca:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('cerca_id', $cercas, null, ['id' => 'cerca_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                            </div>
                                        </div><!-- /.box-body -->
                                    </div>
                                    {{ Form::close() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /.content-wrapper -->
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript">
    $("#btnFiltroEntregador").click(function(){
        let url = root + '/report.logcercas.pdf?datainicio=:datainicio&datafim=:datafim&tipo=1';
        redirect(url, true);
    });

    $("#gerarPdfLogCerca").click(function(){
        let url = root + '/report.logcercas.pdf?datainicio=:datainicio&datafim=:datafim&tipo=2';
        redirect(url, false);
    });

    function redirect(url, modal){
        let setor_id = $("#setor_id").val();
        let cerca_id = $("#cerca_id").val();
        let datainicio = insertDataOracle($("#datainicio").val());
        let datafim = insertDataOracle($("#datafim").val());
        url = url.replace(':datainicio',datainicio);
        url = url.replace(':datafim',datafim);

        if (setor_id) {
            url += `&setor_id=${setor_id}`;
        }
        if (cerca_id) {
            url += `&cerca_id=${cerca_id}`;
        }

        if (modal) {
            $("#popup_relatorio").modal('show');
            $("#iFrameReport").attr('src', url);
        } else {
            window.open(url, '_blank');
        }
    }
</script>
@endsection