@extends('layouts.mainmenu') 
@section('content')
<style>
	.th-inner {
		padding: 0px !important;
	}
	.panel-heading-table{
		padding: 5px !important;
		padding-left: 10px !important;
	}
</style>
<div id="mainContent" class="content">
	<div id="divCadastro" class="row">
		<div class="col-md-12">
		<!-- <input type="text" id="teee">
		<button id="btnA">aqui</button>
		<script>
			$("#teee").on('focusout', function () {
				var val = $("#teee").val();
				var val = unescape(decodeURIComponent(val)).replace(/\+/g, " ");
				$("#teee").val(val);
				var copyText = document.getElementById("teee");
				copyText.select();
				document.execCommand("Copy");
			});
		</script>
 -->
			<!-- Custom Tabs -->
			<!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
			@if(isset($mcmm))
			{{ Form::model($mcmm, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('mcmm.update', $mcmm->id))) }}
			@else 
			{{ Form::open(['id'=>'fmCadastro','route' => 'mcmm.store', 'class' => 'form-horizontal', 'files' => true]) }} 
			@endif
			<ul>
				<div class="panel panel-default">
					<div class="panel-heading">
						<h3 class="panel-title">Mapa de Controle de Movimento Mensal</h3>
					</div>
					<div class="nav-tabs-custom">
						<ul class="nav nav-tabs">
							<li class="active"><a href="#tab_1" data-toggle="tab">Informações Gerais</a></li>
						</ul>
						<div class="tab-content">
							<div class="tab-pane active" id="tab_1">
								<!-- form start -->
								<div class="row">
									<div id="tabCadastro" class="col-md-12">
										<div class="box-body">
											<div class="col-md-11">
												<div class="form-group crud_space">
													{{Form::label('dataInicio', 'Movimento de:', ['class' => 'input-sm control-label col-sm-2'])}}
													<div class="col-sm-2">
														<div class="input-group generalDatePicker">
															{{Form::text('datainiciofiltro', null, ['id' => 'dataInicio', 'class' => 'input-sm form-control generalDatePicker'])}}
															<span class="input-group-addon">
																<span class="glyphicon glyphicon-calendar"></span>
															</span>
														</div>
													</div>
													{{Form::label('dataFim', 'Até:', ['class' => 'input-sm control-label col-sm-1'])}}
													<div class="col-sm-2">
														<div class="input-group generalDatePicker">
															{{Form::text('datafimfiltro', null, ['id' => 'dataFim', 'class' => 'input-sm form-control generalDatePicker'])}}
															<span class="input-group-addon">
																<span class="glyphicon glyphicon-calendar"></span>
															</span>
														</div>
													</div>
													{{Form::label('datamovimento', 'Movimento Mês/Ano:', ['class' => 'input-sm control-label col-sm-2'])}}
													<div class="col-sm-2">
														<div class="input-group generalDateMesAno">
															{{Form::text('datamovimento', null, ['id' => 'datamovimento', 'class' => 'input-sm form-control generalDateMesAno'])}}
															<span class="input-group-addon">
																<span class="glyphicon glyphicon-calendar"></span>
															</span>
														</div>
													</div>
                                                    <button id="btnSearch" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Compras"><span class="fa fa-search fa-lg"></span></button>
												</div>
												<div class="form-group crud_space">
													{{Form::label('razao_social', 'Razão Social:', ['class' => 'input-sm control-label col-sm-2'])}}
													<div class="col-sm-5">
														{{Form::text('razao_social', @$empresa->razao_social, ['id' => 'razao_social', 'class' => 'input-sm form-control', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>
													{{Form::label('distribuidora', 'Distribuidora:', ['class' => 'input-sm control-label col-sm-2'])}}
													<div class="col-sm-3">
														{{Form::text('distribuidora', @$empresa->distribuidora, ['id' => 'distribuidora', 'class' => 'input-sm form-control', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>
												</div>
												<div class="form-group crud_space">
													{{Form::label('registro_anp', 'Registro ANP:', ['class' => 'input-sm control-label col-sm-2'])}}
													<div class="col-sm-2">
														{{Form::text('registro_anp', @$empresa->registro_anp, ['id' => 'registro_anp', 'class' => 'input-sm form-control', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>
													<div class="col-sm-5" id="divTipoInstalacoes">
														{{Form::label('depd', 'DEPD:', ['class' => 'input-sm control-label col-sm-1'])}}
														<div class="col-sm-1 checkbox">
															{{Form::checkbox('depd', @$empresa->depd, @$empresa->depd)}}
														</div>	
														{{Form::label('depr', 'DEPR:', ['class' => 'input-sm control-label col-sm-1'])}}
														<div class="col-sm-1 checkbox">
															{{Form::checkbox('depr', @$empresa->depr, @$empresa->depr)}}
														</div>	
														{{Form::label('prt', 'PRT:', ['class' => 'input-sm control-label col-sm-1'])}}
														<div class="col-sm-1 checkbox">
															{{Form::checkbox('prt', @$empresa->prt, @$empresa->prt)}}
														</div>	
														{{Form::label('prr', 'PRR:', ['class' => 'input-sm control-label col-sm-1'])}}
														<div class="col-sm-1 checkbox">
															{{Form::checkbox('prr', @$empresa->prr, @$empresa->prr)}}
														</div>	
														{{Form::label('prd', 'PRD:', ['class' => 'input-sm control-label col-sm-1'])}}
														<div class="col-sm-1 checkbox">
															{{Form::checkbox('prd', @$empresa->prd, @$empresa->prd)}}
														</div>
													</div>
													{{Form::label('capacidadearmazenamento', 'Cap. Armaz.:', ['class' => 'input-sm control-label col-sm-1'])}}
													<div class="col-sm-2">
														{{Form::text('capacidadearmazenamento', isset($empresa->capacidadearmazenamento) ? number_format($empresa->capacidadearmazenamento, 0, ',', '.') . " Kg" : null, ['id' => 'capacidadearmazenamento', 'class' => 'input-sm form-control maskPesoInteiro', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>	
												</div>
												<div class="form-group crud_space">
													{{Form::label('endereco', 'Endereço:', ['class' => 'input-sm control-label col-sm-2'])}}
													<div class="col-sm-4">
														{{Form::text('endereco', @$endereco, ['id' => 'endereco', 'class' => 'input-sm form-control', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>
													{{Form::label('cidade', 'Cidade/UF:', ['class' => 'input-sm control-label col-sm-1'])}}
													<div class="col-sm-2">
														{{Form::text('cidade', $cidade_uf, ['id' => 'cidade', 'class' => 'input-sm form-control', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>
													{{Form::label('codigo_ibge', 'Código IBGE:', ['class' => 'input-sm control-label col-sm-1'])}}
													<div class="col-sm-2">
														{{Form::text('codigo_ibge', @$empresa->cidade->cod_ibge, ['id' => 'codigo_ibge', 'class' => 'input-sm form-control', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>
												</div>
												<div class="form-group crud_space">
													{{Form::label('responsavel', 'Responsável:', ['class' => 'input-sm control-label col-sm-2'])}}
													<div class="col-sm-4">
														{{Form::text('responsavel', @$empresa->contratonome, ['id' => 'responsavel', 'class' => 'input-sm form-control'])}}
													</div>
													{{Form::label('cnpj', 'CNPJ:', ['class' => 'input-sm control-label col-sm-1'])}}
													<div class="col-sm-2">
														{{Form::text('cnpj', @$empresa->cnpj, ['id' => 'cnpj', 'class' => 'input-sm form-control', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>
													{{Form::label('id', 'Número:', ['class' => 'input-sm control-label col-sm-1'])}}
													<div class="col-sm-2">
														{{Form::text('id', null, ['id' => 'id', 'class' => 'input-sm form-control', 'readonly' => 'readonly', 'tabindex' => '-1'])}}
													</div>
												</div>
											</div>
											<div class="form-group crud_space">
												<div class="col-md-10 col-md-offset-1">
													<div class="panel panel-default margTop_20">
													    <div class="panel-heading panel-heading-table">
													        <h3 class="panel-title" id='page-title'>Entradas</h3>
												        </div>
														{{Form::hidden('entradas', null, ['id' => 'entradas'])}}
														{{Form::hidden('entradas_original', null, ['id' => 'entradas_original'])}}
														{{Form::hidden('entradas_html', null, ['id' => 'entradas_html'])}}
														<table class="table table-bordered table-condensed table-hover table-striped padding-3" data-height="175" id="tblEntradas">
														</table>
													</div>
												</div>
											</div>
											<div class="form-group crud_space">
												<div class="col-md-10 col-md-offset-1">
													<div class="panel panel-default">
													    <div class="panel-heading panel-heading-table">
													        <h3 class="panel-title" id='page-title'>Saídas</h3>
												        </div>
														{{Form::hidden('saidas', null, ['id' => 'saidas'])}}
														{{Form::hidden('saidas_original', null, ['id' => 'saidas_original'])}}
														{{Form::hidden('saidas_html', null, ['id' => 'saidas_html'])}}
														<table class="table table-bordered table-condensed table-hover table-striped padding-3" data-height="175" id="tblSaidas">
														</table>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="box-footer" style="margin-top: -30px">
							<div class="col-md-4">
								{!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
								<a type="button" href="{{url('mcmm')}}" class="btn btn-nw-geral">Voltar</a>
							</div>
						</div>
					</div>
				</div>
			</ul>
			{{Form::close()}}
		</div>
	</div>
</div>
<script src="{{URL::to('plugins/boostrap-table/extensions/js/mindmup-editabletable.js')}}" type="text/javascript"></script>
<script type="text/javascript" src="{{URL::to('js/mcmm.js')}}"></script>
<script type="text/javascript">
	@if($errors->any())
		validateErrors();
	@else 
	@endif
	$(document).ready(function () {
		@if(isset($mcmm))
			saldoAnterior = JSON.parse("{{$saldoAnterior}}".replace(/\&quot;/g, '"'));
			entradas = '{{$entradas}}'.replace(/\&quot;/g, '"');
			$("#tblEntradas").bootstrapTable('load', JSON.parse(entradas));
			saidas = "{{$saidas}}".replace(/\&quot;/g, '"');
			$("#tblSaidas").bootstrapTable('load', JSON.parse(saidas));
		@endif
		@if(isset($show))
			desativarInputs();
			$("#btnSearch").prop('disabled', true).hide();
		@endif
	});
</script>

@endsection