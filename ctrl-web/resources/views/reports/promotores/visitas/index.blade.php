@extends('layouts.mainmenu')

@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Promotores - Clientes Visitados</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Promotores - Clientes Visitados</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'filtrovendatodossetor','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-2 control-label input-sm'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('user_id', 'Promotores:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('user_id',$users,null,['id' => 'user_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('setor_id',$setores,null,['id' => 'setor_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-2 col-sm-offset-1">
                                                    <button type="button" id="btnLimpar"
                                                        onclick="window.location.href = '{{ route('report.promovisitas') }}'"
                                                        class="btn btn-sm btn-github" data-toggle="tooltip"
                                                        data-trigger="hover" data-placement="bottom" title="Limpar">
                                                        <span class="fa fa-recycle fa-lg"></span>
                                                    </button>
                                                    <button type="button" id="gerarPdfVisitados"
                                                        class="btn btn-nw-registro btn-sm" data-toggle="tooltip"
                                                        data-trigger="hover" data-placement="bottom" title="Gerar PDF">
                                                        <span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span>
                                                    </button>
                                                    <button id="btnFiltroVisitados" type="button"
                                                        class="btn btn-nw-buscas btn-sm" data-toggle="tooltip"
                                                        data-trigger="hover" data-placement="bottom"
                                                        title="Visualizar Relatório">
                                                        <span class="fa fa-print fa-lg"></span>
                                                    </button>
                                                </div>
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
        </div>
    </div>
</div>

@include('general.modal_report_iframe')

<script type="text/javascript">

    $("#btnFiltroVisitados").click(function(){
        let url = root + '/report.promovisitas.pdf?datainicio=:datainicio&datafim=:datafim&user_id=:user&setor_id=:setor&tipo=1';
        redirect(url, true);
    });

    $("#gerarPdfVisitados").click(function(){
        let url = root + '/report.promovisitas.pdf?datainicio=:datainicio&datafim=:datafim&user_id=:user&setor_id=:setor&tipo=2';
        redirect(url, false);
    });

    function redirect(url, modal) {
        let datainicio = insertDataOracle($("#datainicio").val());
        let datafim = insertDataOracle($("#datafim").val());
        let user = $("#user_id").val() == "" ? null : $("#user_id").val();
        let setor = $("#setor_id").val() == "" ? -1 : $("#setor_id").val();

        url = url.replace(':datainicio', datainicio);
        url = url.replace(':datafim', datafim);
        url = url.replace(':user', user);
        url = url.replace(':setor', setor);

        openModalReport(url, modal);
    }
</script>

@endsection
