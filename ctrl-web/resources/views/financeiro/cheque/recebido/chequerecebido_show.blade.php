@extends('layouts.mainmenu') 

@section('content')

<div id="mainContent" class="content">
	<div id="divCadastro" class="row">
		<div class="col-md-12">

			{{ Form::model($chequerecebido, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true)) }}
			<ul>
				<div class="nav-tabs-custom">
					<div class="header panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">Cheque Recebido</h3>
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
											{!! Form::label('banco_id', 'Banco:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-3">
												{!! Form::select('banco_id', $bancos, null, ['id'=>'banco_id', 'class' => 'form-control selectChosen'])!!}
											</div>
											{!! Form::label('numerocheque', 'Nº Cheque:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-1">
												{!! Form::text('numerocheque' , null, ['id'=>'numerocheque', 'class' => 'form-control input-sm number'])!!}
											</div>
											{!! Form::label('agencia', 'Agência:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-2">
												{!! Form::text('agencia' , null, ['id'=>'agencia', 'class' => 'form-control input-sm'])!!}
											</div>
											{!! Form::label('numeroconta', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) !!}
											<div class="col-sm-2">
												{!! Form::text('numeroconta' , null, ['id'=>'numeroconta', 'class' => 'form-control input-sm'])!!}
											</div>
										</div>
										<div class="form-group crud_space">
											{{Form::label('dataemissao', 'Emissão:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												<div class="input-group ">
													{{Form::text('dataemissao', requestDataOracle($chequerecebido->dataemissao, false), ['id' => 'dataemissao', 'class' => 'input-sm form-control '])}}
													<span class="input-group-addon">
														<span class="glyphicon glyphicon-calendar"></span>
													</span>
												</div>
											</div>
											{{Form::label('datavencimento', 'Vencimento:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												<div class="input-group ">
													{{Form::text('datavencimento', requestDataOracle($chequerecebido->datavencimento, false), ['id' => 'datavencimento', 'class' => 'input-sm form-control '])}}
													<span class="input-group-addon">
														<span class="glyphicon glyphicon-calendar"></span>
													</span>
												</div>
											</div>
											{{Form::label('valor', 'A Receber:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valor', requestNumeroDecimalOracle($chequerecebido->valor - $chequerecebido->diferencavalor), ['id' => 'valor', 'class' => 'input-sm form-control', 'readonly'])}}
											</div>
											{{Form::label('valorcheque', 'Cheque:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('valorcheque', requestNumeroDecimalOracle($chequerecebido->valor), ['id' => 'valorcheque', 'class' => 'input-sm form-control dinheiro'])}}
											</div>
										</div>
										<div class="form-group crud_space">
											{{Form::label('observacao', 'Observações:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-5">
												{{Form::text('observacao', null, ['id' => 'observacao', 'class' => 'input-sm form-control'])}}
											</div>
											{{Form::label('chequesituacao_id', 'Status:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::select('chequesituacao_id', $chequesituacao, null, ['id' => 'chequesituacao_id', 'class' => 'input-sm form-control selectChosen'])}}
											</div>
											@if(is_null($chequerecebido->chequeEmitidoEncontroContas))
											{{Form::label('credito', 'Débito:', ['class' => 'input-sm control-label col-sm-1'])}}
											<div class="col-sm-2">
												{{Form::text('credito', requestNumeroDecimalOracle($chequerecebido->chequeRecebidoEncontroContas->sum('valortotal')), ['id' => 'observacao', 'class' => 'input-sm form-control'])}}
											</div>
											@endif
										</div>
										<div class="form-group crud_space margTop_15">
											<div class="col-sm-10 col-sm-offset-1">
												<table id="tblChequeRecebido" class="table table-bordered table-hover table-responsive table-condensed">
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
								<a href="{{URL::to('chequerecebido')}}" class="btn btn-nw-geral">Voltar</a>
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