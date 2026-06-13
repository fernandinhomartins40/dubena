@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
	<div class="row">
		<div class="col-xs-12">
			<div class="box-header">
				<div class="panel panel-default">
					<!-- /.box-header -->
					<div class="panel-heading">
						<h3 class="box-title">Clientes Aniversariantes {{$compram ? 'que Compram' : ''}}</h3>
					</div>
					<div class="nav-tabs-custom">
						<ul class="nav nav-tabs">
							<li class="active"><a href="#tab_1" data-toggle="tab">Clientes Aniversariantes {{$compram ? 'que Compram' : ''}}</a></li>
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
													{{ Form::select('empresa_id',$empresas,null,['id' => 'empresa_id','class'=>'form-control selectChosen input-sm', 'multiple', 'data-placeholder' => "Selecione" ]) }}
												</div>
												{{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
												<div class="col-sm-4">
													{{ Form::select('setor_id',$setores,null,['id' => 'setor_id','class'=>'form-control selectChosen input-sm', 'multiple', 'data-placeholder' => "Selecione" ]) }}
												</div>
											</div>
											<div class="form-group crud_space">
												{{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
												<div class="col-sm-2">
													<div class="input-group date generalDatePicker" id="datetimepicker1">
														{{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
														<span class="input-group-addon">
															<span class="glyphicon glyphicon-calendar"></span>
														</span>
													</div>
												</div>
												{{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) }}
												<div class="col-sm-2">
													<div class="input-group date generalDatePicker" id="datetimepickerfim">
														{{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
														<span class="input-group-addon">
															<span class="glyphicon glyphicon-calendar"></span>
														</span>
													</div>
												</div>
												<!-- {{ Form::label('bairro_id', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) }}
												<div class="col-sm-4">
													{{ Form::select('bairro_id',$bairros,null,['id' => 'bairro_id','class'=>'form-control selectChosen input-sm']) }}
												</div> -->
											</div> 
											<div class="form-group crud_space">
												{{ Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
												<div class="col-sm-2">
													{{ Form::select('segmento_id',$segmentos,null,['id' => 'segmento_id','class'=>'form-control selectChosen input-sm']) }}
												</div>
												<div class="col-sm-2">
													<button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
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
	var url = '';
	$("#btnLimpar").on('click', function() {
		$(".selectChosen").val('').trigger('chosen:updated');
		$("#datainicio, #datafim").val(dataAtual());
	});
	$("#btnIframe").on('click', function() {
		setUrl(function () {
			$("#popup_relatorio").modal('show');
			$("#iFrameReport").attr('src',url);
		});
	});
	$("#btnGerarPDF").on('click', function () {
		setUrl(function () {
			window.open(url, '_blank');
		}, true);
	});
	$("#empresa_id").on('change', function(){
		mudaEstadoSelectReport('empresa_id', 'setor_id');
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
		@if($compram)
		url = root + '/report.aniversariantescompram';
		@else
		url = root + '/report.clientesaniversariantes';
		@endif
		if(typeof pdf !== 'undefined')
			url += '.pdf?datainicio=:datainicio&datafim=:datafim&setor_id=:setor_id&segmento_id=:segmento_id&empresa_id=:empresa_id';
		else
			url += '?datainicio=:datainicio&datafim=:datafim&setor_id=:setor_id&segmento_id=:segmento_id&empresa_id=:empresa_id';
		url = url.replace(':datainicio', $("#datainicio").val());
		url = url.replace(':datafim', $("#datafim").val());
		var setor_id = $("#setor_id").val();
		setor_id = setor_id == 'null' || setor_id == null? '' : setor_id;
		url = url.replace(':setor_id', setor_id);
		url = url.replace(':segmento_id', $("#segmento_id").val());
		// url = url.replace(':bairro_id', $("#bairro_id").val());;
		var empresa_id = $("#empresa_id").val();
		empresa_id = empresa_id == 'null' || empresa_id == null? '' : empresa_id;
		url = url.replace(':empresa_id', empresa_id);
		callback();
	}
</script>
@endsection