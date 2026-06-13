@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	td, th{border: none;}
	@media print {
		@page{size: landscape;}
	}
</style>
<div class="fontSize14">
	<!-- {{$first = true}} -->
	@if(isset($clientes) && count($clientes)>0)
	<table style="padding-top:9px; margin-left: -5px; min-width:100%;font-size: 11px">
		@foreach($clientes as $cliente) 
		@if(isset($cliente->totalGeral))
		<tr><td></td></tr>
		<tr>
			<td></td>	
			<td class="bordered destaque negrito align-left" colspan="4">Total Geral: </td>
			<td class="bordered destaque negrito">{{$cliente->totalGeral}}</td>
			<td></td>	
		</tr>
		@elseif(isset($cliente->total))
		<tr>
			<td></td>	
			<td class="bordered destaque negrito align-left" colspan="4">Total Mês: </td>
			<td class="bordered destaque negrito">{{$cliente->total}}</td>
			<td></td>	
		</tr>
		@elseif(isset($cliente->empresa_id))
		@if(!$first)
		<tr style="padding-top: 10px">
			<td colspan="7"><br /><hr /></td>
		</tr>
		@endif
		<tr>
			<td class="negrito" colspan="6">{{$cliente->empresa}}</td>	
		</tr>
		<!-- {{$first = false}} -->
		@elseif(isset($cliente->mes))
		<tr>	
			<td colspan="6">&nbsp;&nbsp;&nbsp;&nbsp;Mês: {{$cliente->mes}}</td>	
		</tr>
		<tr>
			<th></th>	
			<th class="bordered destaque">Dia</th>
			<th class="bordered destaque align-left">Cliente</th>
			<th class="bordered destaque align-left">Telefones</th>
			<th class="bordered destaque">Últ. Compra</th>
			<th class="bordered destaque align-left">Endereço</th>
			<th></th>	
		</tr>
		@else
		<tr>
			<td></td>	
			<td class="bordered">{{$cliente->dia}}</td>
			<td class="bordered align-left">{{$cliente->nome}}</td>
			<td class="bordered align-left">{{$cliente->telefones}}</td>
			<td class="bordered">{{$cliente->ulltimaCompra}}</td>
			<td class="bordered align-left">{{$cliente->endereco}}</td>
			<td></td>	
		</tr>
		@endif
		@endforeach
	</table>
	@else
	<p class="negrito align-center">Nenhum resultado encontrado para estes filtros!</p>
	@endif
</div>

@endsection