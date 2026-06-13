@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
td, th{border: none;}
@page{size: landscape;}
@media print{ .conteudo-table{padding-top:18px ;}}
</style>
<div class="fontSize14 conteudo-table">
	@if(isset($clientes) && count($clientes)>0)
	<table style="margin-top:7px; margin-left: -5px; min-width:100%; font-size: 11px">
		<tr>
			<th></th>	
			<th class="bordered destaque">Cód.</th>
			<th class="bordered destaque align-left">Convênio</th>
			<th class="bordered destaque align-left">Telefones</th>
			<th class="bordered destaque">Dia Entrega</th>
			<th class="bordered destaque">Dia Receb.</th>
			<th class="bordered destaque">Desconto</th>
			<th class="bordered destaque">Situação</th>
			<th></th>	
		</tr>
		@foreach($clientes as $cliente) 
		@if(isset($cliente->total))
		<tr>
			<td></td>	
			<td class="bordered destaque negrito align-left" colspan="6">Total: </td>
			<td class="bordered negrito destaque">{{$cliente->total}}</td>
			<td></td>
		</tr>
		@else
		<tr>
			<td></td>	
			<td class="bordered">{{$cliente->cliente_id}}</td>
			<td class="bordered align-left">{{$cliente->nome}}</td>
			<td class="bordered align-left">{{$cliente->telefones}}</td>
			<td class="bordered">{{$cliente->entrega}}</td>
			<td class="bordered">{{$cliente->recebimento}}</td>
			<td class="bordered">{{$cliente->desconto}}</td>
			<td class="bordered">{{$cliente->situacao == 1 ? 'Ativo' : 'Inativo'}}</td>
			<th></th>	
		</tr>
		@endif
		@endforeach
	</table>
	@else
	<p style="text-align:center" class="negrito">Nenhum resultado encontrado para estes filtros!</p>
	@endif
</div>

@endsection