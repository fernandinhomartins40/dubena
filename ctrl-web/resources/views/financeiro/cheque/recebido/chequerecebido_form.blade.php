@extends('layouts.nomenu')
@section('content')

<link href="{{URL::to('plugins/chosen/chosen.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/datatables/dataTables.bootstrap.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/selectize/css/selectize.bootstrap3.css')}}" rel="stylesheet" type="text/css" />

<script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
<script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}"></script>
<script src="{{URL::to('plugins/bootbox.min.js')}}"></script>
<script src="{{URL::to('plugins/chosen/chosen.jquery.latin.js')}}"></script>
<script src="{{URL::to('js/jqueryMaskMoney.js')}}"></script>
<script src="{{URL::to('js/shortcut.js')}}"></script>
<script src="{{URL::to('plugins/datatables/jquery.dataTables.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datatables/dataTables.bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/jquery.mask.min.js')}}"></script>
<script src="{{URL::to('plugins/selectize/js/standalone/selectize.min.js')}}"></script>
<script src="{{URL::to('js/custom.js')}}"></script>
<script>
	root = '{{url("")}}';
	var urlDataTable = "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}";
</script>

<div id="mainContent" class="content">
	<div id="divCadastro" class="row">
		<div class="col-md-12">

			<!-- Custom Tabs -->
			<!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
			@if(isset($chequerecebido))
			{{ Form::model($chequerecebido, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('chequerecebido.update', $chequerecebido->id))) }}
			@else
			{{ Form::open(['id'=>'fmCadastro','route' => 'chequerecebido.store', 'class' => 'form-horizontal', 'files' => true]) }}
			@endif
			<ul>
				<div class="nav-tabs-custom">
					<div class="header panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">Cadastro de Cheques Recebidos</h3>
						</div>
					</div>
					<ul class="nav nav-tabs">
						<li class="active"><a href="#tab_1" data-toggle="tab">Informações Gerais</a></li>
					</ul>
					<div class="tab-content">
						<div class="tab-pane active" id="tab_1">
							<!-- form start -->
							<div class="row">
								<div id="tabCadastro" class="col-sm-12">
									<div class="box-body">
										{{Form::hidden('parcelas', null, ['id' => 'parcelas'])}}
										{!! Form::select('contas', $contas, null, ['id'=>'contas', 'class' => 'hidden'])!!}
										<div class="form-group crud_space">
											{{Form::hidden('banco_id_erro',"", ['id'=>'banco_id_erro'])}}
											{{Form::hidden('banco_descricao_erro',"", ['id'=>'banco_descricao_erro'])}}
											{{Form::hidden('banco_id_erro_corresp',"", ['id'=>'banco_id_erro_corresp'])}}
											{{Form::hidden('banco_descricao_erro_corresp',"", ['id'=>'banco_descricao_erro_corresp'])}}
											{!! Form::label('banco_id', 'Banco:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-4">
												@if(isset($chequerecebido) && $chequerecebido->banco != null)
												<select id="searchbox" name="banco_id" placeholder="Buscar banco" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[{"id":{{$chequerecebido->banco->id}},"descricao":"{{$chequerecebido->banco->descricao}}"}]'></select>
												@else
												<select id="searchbox" name="banco_id" placeholder="Buscar banco" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[]'></select>
												@endif
											</div>
											<div class="col-sm-7">
												{!! Form::label('agencia', 'Agência:', ['class'=>'col-sm-1 control-label input-sm']) !!}
												<div class="col-sm-3">
													{!! Form::text('agencia' , null, ['id'=>'agencia', 'class' => 'form-control input-sm'])!!}
												</div>
												{!! Form::label('numeroconta', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) !!}
												<div class="col-sm-3">
													{!! Form::text('numeroconta' , null, ['id'=>'numeroconta', 'class' => 'form-control input-sm'])!!}
												</div>
												{!! Form::label('numerocheque', 'Nº Cheque:', ['class'=>'col-sm-2 control-label input-sm']) !!}
												<div class="col-sm-2">
													{!! Form::text('numerocheque' , null, ['id'=>'numerocheque', 'class' => 'form-control input-sm number'])!!}
												</div>
											</div>
										</div>
										<div class="form-group crud_space">
											{{Form::label('dataemissao', 'Emissão:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												<div class="input-group generalDatePicker">
													{{Form::text('dataemissao', null, ['id' => 'dataemissao', 'class' => 'input-sm form-control generalDatePicker'])}}
													<span class="input-group-addon">
														<span class="glyphicon glyphicon-calendar"></span>
													</span>
												</div>
											</div>
											{{Form::label('datavencimento', 'Vencimento:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												<div class="input-group generalDatePicker">
													{{Form::text('datavencimento', null, ['id' => 'datavencimento', 'class' => 'input-sm form-control generalDatePicker'])}}
													<span class="input-group-addon">
														<span class="glyphicon glyphicon-calendar"></span>
													</span>
												</div>
											</div>
											{{Form::label('observacao', 'Observações:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-5">
												<div class="col-sm-12">
													{{Form::text('observacao', null, ['id' => 'observacao', 'class' => 'input-sm form-control'])}}
												</div>
											</div>
										</div>
										<div class="form-group crud_space">
											{{Form::label('valor', 'A Receber:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valor', $valorTotal, ['id' => 'valor', 'class' => 'input-sm form-control', "tabIndex" => "-1", 'readonly'])}}
											</div>
											@if($encontrocontas)
											{{Form::label('valorCredito', 'Crédito:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valorCredito', $valorCredito, ['id' => 'valorCredito', 'class' => 'input-sm form-control dinheiro', "tabIndex" => "-1", 'readonly'])}}
												{{Form::hidden('parcelasContasPagar', $parcelasContasPagar, ['id' => 'parcelasContasPagar'])}}
											</div>
											@endif
											{{Form::label('valorcheque', 'Cheque:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valorcheque', $valorCheque, ['id' => 'valorcheque', 'class' => 'input-sm form-control dinheiro'])}}
											</div>
										</div>
										<div class="form-group crud_space margTop_15">
											<div class="col-sm-10 " style="margin-left: 9.5%">
												<table id="tblChequeRecebido" class="table table-bordered table-hover table-responsive table-condensed">
													<thead>
														<tr>
															<th>Cód.</th>
															<th>Nº Parc.</th>
															<th>Emissão</th>
															<th>Vencimento</th>
															<th>Valor</th>
															<th>Cliente</th>
															@if(isset($chequerecebido))
															<th>Situação</th>
															@endif
														</tr>
													</thead>
													<tbody>
														@foreach($parcelas as $parcela)
														<tr>
															<td>{{$parcela->id}}</td>
															<td>{{$parcela->numero}}</td>
															<td>{{requestDataOracle($parcela->financeiro->dataemissao, false)}}</td>
															<td>{{requestDataOracle($parcela->datavencimento, false)}}</td>
															<td>{{requestNumeroDecimalOracle($parcela->valorefetivado)}}</td>
															<td>{{$parcela->financeiro->cliente->nome}}</td>
															@if(isset($chequerecebido))
															<th></th>
															@endif
														</tr>
														@endforeach
													</tbody>
												</table>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="box-footer">
							<div class="col-md-4">
								{!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
							</div>
						</div>
					</div>
				</div>

			</ul>
			{{Form::close()}}
		</div>
	</div>
</div>
<script type="text/javascript" src="{{URL::to('js/cheque.js')}}"></script>

<script type="text/javascript">
	var dataAnterior = dataAtual();
	var dataAnterior2 = dataAnterior;
	var valorAntCheque = 0;
	$(document).ready(function() {
		initSelectize();
		chequeRecebido = true;
		inicializarTabelas();
		var errors = false;
		@if($errors->any())
			//
			carregarParcelasErro();
			errors = true;
		//
		@endif
		var paramsUrl = '{{\Request::getQueryString()}}';
		if(isEmpty(paramsUrl) && !errors){
			window.location.href = root + '/financeiro.fecharModalIframe';
		}
	});

	function gravarCheque(formData) {
		ajaxGenerator(root + '/chequerecebido', 'POST', function (data) {
			if(data.substr(0,3) == 'OK|'){
				bootbox.alert({message: 'Gravado com sucesso!', callback: function () {
					window.location.href = root + '/financeiro.fecharModalIframe';
				}});
			} else {
				bootbox.alert('Erro:' + data);
			}
		}, null, formData);
	}

	$("#fmCadastro").on('submit', function() {
		validaCampos(function () {
			var i = 0;
			tblChequeRecebido.rows().every(function () {
				var d = this.data();
				parcelas[i] = d;
				i++;
			});
			$("#parcelas").val(JSON.stringify(parcelas));

			var formData = new FormData($("#fmCadastro")[0]);

			var valorCheque = $("#valorcheque").val().replace('R$ ', '').replace('.', '').replace(',', '.');
			valorCheque = parseFloat(valorCheque);
			var valorParcelas = $("#valor").val().replace('R$ ', '').replace('.', '').replace(',', '.');
			valorParcelas = parseFloat(valorParcelas);
			var valorCredito = typeof $("#valorCredito").val() != 'undefined' ? $("#valorCredito").val().replace('R$ ', '').replace('.', '').replace(',', '.') : 'undefined';
			valorCredito = valorCredito != 'undefined' ? parseFloat(valorCredito) : 'undefined';
			if(valorCredito != 'undefined' && ((valorCredito + valorCheque) < valorParcelas)){
				bootbox.alert({
					message: 'O valor do cheque junto com o crédito deve ser maior que o valor das parcelas!',
					callback: function () {
						setTimeout(function() {
							$("#valorcheque").focus();
						}, 500);
					}
				});
			} else if (valorCredito == 'undefined' && valorCheque < valorParcelas){
				bootbox.alert({
					message: 'O valor do cheque deve ser maior que o valor das parcelas!',
					callback: function () {
						setTimeout(function() {
							$("#valorcheque").focus();
						}, 500);
					}
				});
			} else if(valorCheque > valorParcelas){
				var diferenca = valorCheque - valorParcelas;
				verificaChequeMaior(formData, diferenca);
			} else {
				gravarCheque(formData);
			}
		});
		return false;
	});

	function initSelectize() {
		$('#searchbox').selectize({
			valueField: 'id',
			labelField: 'descricao',
			searchField: ['descricao'],
			maxOptions: 10,
			options: [],
			create: false,
			render: {
				option: function(item, escape) {
					return '<div>' +escape(item.descricao)+'</div>';
				}
			},
			optgroups: [
			{value: 'banco', label: 'Bancos'},
			],
			optgroupField: 'class',
			optgroupOrder: ['banco'],
			load: function(query, callback) {
				if (!query.length) return callback();
				$.ajax({
					url: root+'/api/searchBanco',
					type: 'GET',
					dataType: 'json',
					data: {
						q: query
					},
					error: function() {
						callback();
					},
					success: function(res) {
						callback(res.data);
					}
				});
			},
			onChange: function(){
				//alert('aqui');
				//preencheDadosAlunos($('#searchbox').selectize()[0].selectize.getValue());
				$('#banco_id_erro').val($('#searchbox').selectize()[0].selectize.getValue());
				$('#banco_descricao_erro').val($('#searchbox').selectize()[0].selectize.getItem(this.items[0]).context.innerText);
				//console.log($('#searchbox').selectize()[0].selectize.getItem(this.items[0]).context.innerText);
			},onInitialize: function() {
				var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
				var self = this;
				@if($errors->any())
				var opt = [{"id":$('#banco_id_erro').val(),"descricao":$('#banco_descricao_erro').val()}];
				opt.forEach( function (existingOption) {
					self.addOption(existingOption);
					self.addItem(existingOption[self.settings.valueField]);
				});
				@else
				if(Object.prototype.toString.call( existingOptions ) === "[object Array]") {
					existingOptions.forEach( function (existingOption) {
						self.addOption(existingOption);
						self.addItem(existingOption[self.settings.valueField]);
					});
				}
				else if (typeof existingOptions === 'object') {
					self.addOption(existingOptions);
					self.addItem(existingOptions[self.settings.valueField]);
				}
				@endif
			}
		});
	}

	$("#valorcheque").on('focusin', function () {
		if(!$("#dataemissao").prop('disabled'))
			dataAnterior = $("#dataemissao").val();
		valorAntCheque = $(this).val();
		setTimeout(function () {
			$("#dataemissao").removeClass('hasError');
		}, 200);
	});
	$("#valorcheque").on('blur', function () {
		var valorCheque = $("#valorcheque").val().replace('R$ ', '').replace('.', '').replace(',', '.');
		valorCheque = parseFloat(valorCheque);
		var valorParcelas = $("#valor").val().replace('R$ ', '').replace('.', '').replace(',', '.');
		valorParcelas = parseFloat(valorParcelas);
		var valorCredito = typeof $("#valorCredito").val() != 'undefined' ? $("#valorCredito").val().replace('R$ ', '').replace('.', '').replace(',', '.') : 'undefined';
		valorCredito = valorCredito != 'undefined' ? parseFloat(valorCredito) : 0;
		var trocoAdiantamento = (valorCheque + valorCredito) > valorParcelas;
		$("#dataemissao").prop('disabled', trocoAdiantamento);

		if(trocoAdiantamento){
			$("#dataemissao").val(dataAtual());
		} else {
			$("#dataemissao").val(dataAnterior2);
		}
		if(valorAntCheque != $(this).val())
			$("#dataemissao").addClass('hasError');
		if(dataAnterior != dataAtual())
			dataAnterior2 = dataAnterior
		setTimeout(function () {
			$("#dataemissao").removeClass('hasError');
		}, 3000);
	});
</script>

@endsection
