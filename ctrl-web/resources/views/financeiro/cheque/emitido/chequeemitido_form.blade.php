@extends('layouts.nomenu')
@section('content')

<link href="{{URL::to('plugins/chosen/chosen.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/datatables/dataTables.bootstrap.css')}}" rel="stylesheet" type="text/css" />

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
			@if(isset($chequeemitido))
			{{ Form::model($chequeemitido, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('chequeemitido.update', $chequeemitido->id))) }}
			@else
			{{ Form::open(['id'=>'fmCadastro','route' => 'chequeemitido.store', 'class' => 'form-horizontal', 'files' => true]) }}
			@endif
			<ul>
				<div class="nav-tabs-custom">
					<div class="header panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">Cadastro de Cheques Emitidos</h3>
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
										{{Form::hidden('financeiro_id', $financeiro_id, ['id' => 'financeiro_id'])}}
										<div class="form-group crud_space">
											{!! Form::label('conta_id', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-3">
												{!! Form::select('conta_id',$contas , null, ['id'=>'conta_id', 'class' => 'form-control selectChosen'])!!}
											</div>
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
											{!! Form::label('numerocheque', 'Nº Cheque:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-1">
												{!! Form::text('numerocheque' , null, ['id'=>'numerocheque', 'class' => 'form-control input-sm number'])!!}
											</div>
										</div>
										<div class="form-group crud_space">
											{{Form::label('observacao', 'Observações:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-5">
												{{Form::text('observacao', null, ['id' => 'observacao', 'class' => 'input-sm form-control'])}}
											</div>
											{{Form::label('valor', 'Valor:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valor', $valorTotal, ['id' => 'valor', 'class' => 'input-sm form-control', 'readonly'])}}
											</div>
											@if($encontrocontas)
											{{Form::label('valorCredito', 'Crédito:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valorCredito', $valorCredito, ['id' => 'valorCredito', 'class' => 'input-sm form-control dinheiro', 'readonly'])}}
												{{Form::hidden('parcelasContasPagar', $parcelasContasPagar, ['id' => 'parcelasContasPagar'])}}
											</div>
										</div>
										<div class="form-group crud_space">
											{{Form::label('valorliquido', 'Líquido:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valorliquido', $valorLiquido, ['id' => 'valorliquido', 'class' => 'input-sm form-control dinheiro', 'readonly'])}}
											</div>
											@endif
										</div>
										<div class="form-group crud_space">
											<div class="divUnicoCheque">
												@if((isset($variasParcelas) && $variasParcelas) || $errors->any())
												{{Form::label('unicocheque', 'Gerar um Único Cheque:', ['class' => 'input-sm control-label col-sm-2'])}}
												<div class="col-sm-1 checkbox">
													{{Form::checkbox('unicocheque', true, true, ['id' => 'unicocheque'])}}
												</div>
												@endif
											</div>
										</div>
										<div class="form-group crud_space margTop_15">
											<div class="col-sm-12" style="max-width: 98%; margin-left: 3%">
												<table id="tblChequeEmitido" class="table table-bordered table-hover table-responsive table-condensed">
													<thead>
														<tr>
															<th>Cód.</th>
															<th>Nº Parc.</th>
															<th>Emissão</th>
															<th>Vencimento</th>
															<th>Valor</th>
															<th>Fornecedor</th>
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
	$(document).ready(function() {
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
			bootbox.alert({
				message: 'Gravado com sucesso!',
				callback: function () {
					window.location.href = root + '/financeiro.fecharModalIframe';
				}
			});
		}
	});

	$("#fmCadastro").on('submit', function () {
		var parcelas = {};
		var i = 0;
		tblChequeEmitido.rows().every(function () {
			var d = this.data();
			parcelas[i] = d;
			i++;
		});
		$("#parcelas").val(JSON.stringify(parcelas));
		formData = new FormData($(this)[0]);
		ajaxGenerator(root + '/chequeemitido', 'POST', function (data) {
			if(data.substr(0,3) == 'OK|'){
				bootbox.alert({message: 'Gravado com sucesso!', callback: function () {
					window.location.href = root + '/financeiro.fecharModalIframe';
				}});
			} else {
				bootbox.alert('Erro:' + data);
			}
		}, null, formData);
		return false;
	});

</script>

@endsection
