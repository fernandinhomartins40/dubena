@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	/*@page{margin-bottom: 15px !important;}*/
		@page{size: landscape;}
</style>
<div class="fontSize14">
	@if(isset($contas) && count($contas)>0)
	<div style="font-size: 15px">
		<strong>Contas</strong>
	</div>
	<table style="margin-top:8px; margin-left: -5px; min-width:100%; font-size: 10px">
		<tr>
			<th></th>
			<th class="bordered destaque align-left">Conta</th>
			<th class="bordered destaque">Data Saldo Inicial</th>
			<th class="bordered destaque">Data Saldo Final</th>
			<th class="bordered destaque money">Saldo Inicial</th>
			<th class="bordered destaque money">Saldo Final</th>
			<th></th>
		</tr>
		@foreach($contas as $conta)
		@if(isset($conta->conta_descricao))
		<tr>
			<td></td>
			<td class="bordered align-left">{{$conta->conta_descricao}}</td>
			<td class="bordered ">{{requestDataOracle($conta->datainicio, false)}}</td>
			<td class="bordered">{{requestDataOracle($conta->datafim, false)}}</td>
			<td class="bordered money">{{requestNumeroDecimalOracle($conta->saldoinicial)}}</td>
			<td class="bordered money">{{requestNumeroDecimalOracle($conta->saldofinal)}}</td>
			<td></td>
		</tr>
		@elseif(isset($conta->saldogeralfinal))
		<tr>
			<td></td>
			<td class="bordered destaque align-left negrito" colspan="3">Total Geral: </td>
			<td class="bordered destaque negrito money">{{requestNumeroDecimalOracle($conta->saldogeralinicial)}}</td>
			<td class="bordered destaque negrito money">{{requestNumeroDecimalOracle($conta->saldogeralfinal)}}</td>
			<td></td>
		</tr>
		@else
		@endif
		@endforeach
	</table>
	@endif

	@if(isset($movimentos) && count($movimentos)>0)
	<br />
	<div style="font-size: 15px">
		<strong>Movimentações</strong>
	</div>
	<table style="margin-top:8px; margin-left: 0px; min-width:100%; font-size: 9px">
		<tr>
			<th class="bordered destaque">Data</th>
			<th class="bordered destaque align-left">Conta</th>
			<th class="bordered destaque align-left">Cliente</th>
			<th class="bordered destaque align-left">Descrição</th>
			<th class="bordered destaque money">Entrada</th>
			<th class="bordered destaque money">Saída</th>
			<th class="bordered destaque money">Saldo</th>
		</tr>
		@foreach($movimentos as $movimento)
		@if(isset($movimento->descricao))
		<tr>
			<td class="bordered">{{$movimento->datahorabaixa}}</td>
			<td class="bordered align-left">{{strlen($movimento->conta_descricao) <= 42 ? substr($movimento->conta_descricao, 0, 42) . '' : substr($movimento->conta_descricao, 0, 42) . '...'}}</td>
			<td class="bordered align-left">{{strlen($movimento->cliente) <= 36 ? substr($movimento->cliente, 0, 36) . '' : substr($movimento->cliente, 0, 36) . '...'}}</td>
			<td class="bordered align-left">{{my_mb_ucfirst(strlen($movimento->descricao) <= 55 ? substr($movimento->descricao, 0, 55) . '' : substr($movimento->descricao, 0, 55) . '...')}}</td>
			<td class="bordered money">{{requestNumeroDecimalOracle($movimento->entrada)}}</td>
			<td class="bordered money">{{requestNumeroDecimalOracle($movimento->saida)}}</td>
			<td class="bordered money">{{requestNumeroDecimalOracle($movimento->saldo)}}</td>
		</tr>
		@elseif(isset($movimento->totalGeral))
		<tr>
			<td class="bordered destaque align-left negrito" colspan="4">Total Geral: </td>
			<td class="bordered destaque negrito money">{{requestNumeroDecimalOracle($movimento->totalEntrada)}}</td>
			<td class="bordered destaque negrito money">{{requestNumeroDecimalOracle($movimento->totalSaida)}}</td>
			<td class="bordered destaque negrito money">{{requestNumeroDecimalOracle($movimento->totalGeral)}}</td>
		</tr>
		@else
		@endif
		@endforeach
	</table>
	@else
	<br />
	<p class="negrito align-center">Nenhuma movimentação encontrada para estes filtros!</p>
	@endif
</div>

@endsection
