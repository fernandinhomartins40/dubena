@extends('layouts.mainmenu') 
@section('content')
<div id="mainContent" class="content">
	<div id="divCadastro" class="row">
		<div class="col-md-12">

			{{ Form::model($chequeemitido, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true)) }}
			<ul>
				<div class="nav-tabs-custom">
					<div class="header panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">Cheque Emitido</h3>
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
										<div class="form-group crud_space">
											{!! Form::label('conta_id', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-3">
												{!! Form::select('conta_id',$contas , null, ['id'=>'conta_id', 'class' => 'form-control selectChosen'])!!}
											</div>
											{!! Form::label('numerocheque', 'Nº Cheque:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-1">
												{!! Form::text('numerocheque' , null, ['id'=>'numerocheque', 'class' => 'form-control input-sm number'])!!}
											</div>
											{{Form::label('dataemissao', 'Emissão:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												<div class="input-group">
													{{Form::text('dataemissao', requestDataOracle($chequeemitido->dataemissao, false), ['id' => 'dataemissao', 'class' => 'input-sm form-control'])}}
													<span class="input-group-addon">
														<span class="glyphicon glyphicon-calendar"></span>
													</span>
												</div>
											</div>
											{{Form::label('datavencimento', 'Vencimento:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												<div class="input-group">
													{{Form::text('datavencimento',  requestDataOracle($chequeemitido->datavencimento, false), ['id' => 'datavencimento', 'class' => 'input-sm form-control'])}}
													<span class="input-group-addon">
														<span class="glyphicon glyphicon-calendar"></span>
													</span>
												</div>
											</div>
										</div>
										<div class="form-group crud_space">
											{{Form::label('observacao', 'Observações:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-5">
												{{Form::text('observacao', null, ['id' => 'observacao', 'class' => 'input-sm form-control'])}}
											</div>
											{{Form::label('valor', 'Valor:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valor', requestNumeroDecimalOracle($chequeemitido->valor), ['id' => 'valor', 'class' => 'input-sm form-control', 'readonly'])}}
											</div>
											{{Form::label('chequesituacao_id', 'Status:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::select('chequesituacao_id', $chequesituacao, null, ['id' => 'chequesituacao_id', 'class' => 'input-sm form-control selectChosen'])}}
											</div>
										</div>
										<div class="form-group crud_space margTop_15">
											<div class="col-sm-10 col-sm-offset-1">
												<table id="tblChequeEmitido" class="table table-bordered table-hover table-responsive table-condensed">
													<thead>
														<tr>
															<th>Cód.</th>
															<th>Nº Parc.</th>
															<th>Emissão</th>
															<th>Vencimento</th>
															<th>Valor</th>
															<th>Valor Efetivado</th>
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
															<td>{{requestNumeroDecimalOracle($parcela->valor)}}</td>
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
								<a href="{{URL::to('chequeemitido')}}" type="button" class="btn btn-nw-geral">Voltar</a>
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
	desativarInputs();
</script>

@endsection