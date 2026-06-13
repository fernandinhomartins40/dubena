@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	@page{margin-bottom: 15px !important;}
</style>
<div class="fontSize14">
	<!-- {{$setorAnterior = -1}} -->
	<!-- {{$totalInativado = 0}} -->
	@if(isset($clientes) && count($clientes) > 0)
	<table style="margin-top:8px; margin-left: -5px; min-width:100%; font-size: 10.5px">
		@foreach($clientes as $cliente) 
			@if($setorAnterior != $cliente->setor)
				@if(!$loop->first)
					@if($totalInativado > 1)
						<tr>
							<th></th>	
							<th class="bordered destaque align-left" colspan="6">Total Inativado:</th>
							<th class="bordered destaque">{{$totalInativado}}</th>
							<th></th>
						</tr>
					@endif
					<!-- {{$totalInativado = 0}} -->
					<tr style="padding-top: 10px">
						<td colspan="9"><br /><hr /></td>
					</tr>
				@endif
				<tr>
					<td class="negrito" colspan="9">{{$cliente->setor == null ? 'Sem Setor' : $cliente->setor}}</td>	
				</tr>
				<tr><td></td></tr>
				<tr>
					<th></th>	
					<th class="bordered destaque align-left">Data Inativação</th>
					<th class="bordered destaque">Cód</th>
					<th class="bordered destaque align-left">Cliente</th>
					<th class="bordered destaque">Data Cadastro</th>
					<th class="bordered destaque">Últ. Compra</th>
					<th class="bordered destaque">S/ Compras</th>
					<th class="bordered destaque">S/ Compras</th>
					<th></th>	
				</tr>
			@endif
			<tr>
				<td></td>
					<td class="bordered align-left">{{requestDataOracle($cliente->datainativacao, false)}}</td>
					<td class="bordered">{{$cliente->cliente_id}}</td>	
					<td class="bordered align-left">{{strlen($cliente->nome) <= 46 ? $cliente->nome : substr($cliente->nome, 0, 43) . '...'}}</td>
					<td class="bordered">{{requestDataOracle($cliente->datacadastro, false)}}</td>
					<td class="bordered">{{$cliente->ultimacompra == null ? 'Sem Compra' : requestDataOracle($cliente->ultimacompra, false)}}</td>	
					<td class="bordered">{{round($cliente->diasinativo, 0) . ' dias'}}</td>
					<td class="bordered">{{str_replace('.', ',', round($cliente->mesessemcompras, 1) . ' meses')}}</td>
				<td></td>
			</tr>
			<!-- {{$totalInativado++}} -->
			@if($loop->last)
				@if($totalInativado > 1)
					<tr>
						<th></th>	
						<th class="bordered destaque align-left" colspan="6">Total Inativado:</th>
						<th class="bordered destaque">{{$totalInativado}}</th>
						<th></th>
					</tr>
				@endif
				@if($clientes->count() > 1)
					<tr><td></td></tr>
					<tr>
						<td></td>
						<td class='align-left destaque negrito bordered' colspan="6">Total Geral:</td>
						<td class='destaque negrito bordered'>{{$clientes->count()}}</td>
						<td></td>
					</tr>
				@endif
			@endif
			<!-- {{$setorAnterior = $cliente->setor}} -->
		@endforeach
	</table>
	@else
	<br />
	<p style="text-align:center" class="negrito">Nenhum resultado encontrado para estes filtros!</p>
	@endif
</div>

@endsection