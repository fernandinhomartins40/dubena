
@extends('monitora.layouts.mainmenu')

@section('content')

<style>
    #floating-panel {
        position: absolute;
        top: 10px;
        left: 25%;
        z-index: 5;
        background-color: #fff;
        padding: 5px;
        border: 1px solid #999;
        text-align: center;
        font-family: 'Roboto','sans-serif';
        line-height: 30px;
        padding-left: 10px;
    }
    .panel {
        margin-bottom: 5px;
    }
    .box-header {
        padding: 0px;
    }
    .col-xs-12 {
        padding-right: 0px;
    }
    .content {
        padding-top: 5px;
    }
    .table-info {
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        border-bottom-left-radius: 10px;
        border-bottom-right-radius: 10px;
    }
    table { border-collapse: separate; }
</style>
@include('monitora.rastreamento.css')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Consulta de Eventos (paradas e excesso de velocidade) por Veículo</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <div id="tabCadastro" class="col-md-12">
                                    <div class="box-body">
                                        {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                        <div class="form-group crud_space">
                                            {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                            <div class="col-sm-3">
                                                <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                    {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDateTimePicker']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
                                            <div class="col-sm-3">
                                                <div class="input-group date generalDateTimePicker" id="datetimepicker2">
                                                    {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDateTimePicker']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('monitora.rota.index')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                <button id="btnIframe-tab_3" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-3">
                                                {{ Form::select('empresa_id',$empresas,null,['id' => 'empresa_id','class'=>'form-control selectChosen input-sm', 'onchange'=>'carregarVeiculos();']) }}
                                            </div>
                                            {{ Form::label('veiculo_id', 'Veículo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                            <div class="col-sm-4">
                                                {{ Form::select('veiculo_id',[],null,['id' => 'veiculo_id','class'=>'form-control selectChosen input-sm']) }}
                                            </div>
                                        </div>
                                        {{ Form::close() }}
                                    </div>
                                    <!-- /.box-body -->
                                </div>

                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                </div><!-- /.col -->
            </div><!-- /.row -->

        </div><!-- /.content-wrapper -->
    </div>
    @include('monitora.general.modal_report_iframe')
    <script>
        $(document).ready(function ($) {
        });

        function carregarVeiculos(){
            $.get("{{ url('veiculos/dropdown')}}",
		{ option: $("#empresa_id").val() },
		function(data) {
			var veiculos = $('#veiculo_id');
			veiculos.empty();
			$.each(data.veiculos, function(index, element) {
				veiculos.append("<option value='"+ element.id +"'>" + element.placa + " : " + element.descricao + "</option>");
			});
                        veiculos.trigger("chosen:updated");
		});
        }
        $("#btnIframe-tab_3").on('click', function () {
		setUrl(function (url) {
			$("#popup_relatorio").modal('show');
			$("#iFrameReport").attr('src',url);
		}, false)
	});
	function setUrl(callback, pdf) {
		var url = root + '/report.eventosVeiculo';
		if(typeof pdf != 'undefined' && pdf)
			url += '.pdf';
                url += '?datainicio=:datainicio&datafim=:datafim&veiculo_id=:veiculo_id';
                url = url.replace(':datainicio', $("#datainicio").val());
                url = url.replace(':datafim', $("#datafim").val());
                var veiculo_id = $("#veiculo_id").val();
                veiculo_id = veiculo_id == 'null' || veiculo_id == null? '' : veiculo_id;
                url = url.replace(':veiculo_id', veiculo_id);
		callback(url);
	}
    </script>
    @endsection
