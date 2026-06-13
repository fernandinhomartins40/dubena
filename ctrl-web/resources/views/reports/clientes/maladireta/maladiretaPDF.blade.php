@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	@page{margin-bottom: 15px !important;}
	@media print {
		@page{size: landscape;}
	}
</style>
<div class="fontSize14">
	<!-- {{$dataAnterior = -1}} -->
	<!-- {{$totalInativado = 0}} -->
	@if(isset($clientes) && count($clientes) > 0)
	<table style="margin-top:8px; margin-left: -5px; min-width:100%; font-size: 11px">
		@foreach($clientes as $cliente) 
			@if($dataAnterior != requestDataOracle($cliente->datainativacao, false))
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
					<td class="negrito" colspan="9">{{requestDataOracle($cliente->datainativacao, false)}}</td>	
				</tr>
				<tr><td></td></tr>
				<tr>
					<th></th>	
					<th class="bordered destaque">Cód</th>
					<th class="bordered destaque align-left">Nome</th>
					<th class="bordered destaque align-left">Setor</th>
					<th class="bordered destaque">Data Cadastro</th>
					<th class="bordered destaque">Ult. Compra</th>
					<th class="bordered destaque">Dias Inativo</th>
					<th class="bordered destaque">Meses S/ Compras</th>
					<th></th>	
				</tr>
			@endif
			<tr>
				<td></td>
					<td class="bordered">{{$cliente->cliente_id}}</td>	
					<td class="bordered align-left">{{$cliente->nome}}</td>
					<td class="bordered align-left">{{$cliente->setor}}</td>
					<td class="bordered">{{requestDataOracle($cliente->datacadastro, false)}}</td>
					<td class="bordered">{{$cliente->ultimacompra == null ? 'Nunca Comprou' : requestDataOracle($cliente->ultimacompra, false)}}</td>	
					<td class="bordered">{{round($cliente->diasinativo, 0)}}</td>
					<td class="bordered">{{str_replace('.', ',', round($cliente->mesessemcompras, 1))}}</td>
				<td></td>
			</tr>
			<!-- {{$totalInativado++}} -->
			@if($loop->last)
				<tr>
					<th></th>	
					<th class="bordered destaque align-left" colspan="6">Total Inativado:</th>
					<th class="bordered destaque">{{$totalInativado}}</th>
					<th></th>
				</tr>
				<tr><td></td></tr>
				<tr>
					<td></td>
					<td class='align-left destaque negrito bordered' colspan="6">Total Geral:</td>
					<td class='destaque negrito bordered'>{{$clientes->count()}}</td>
					<td></td>
				</tr>
			@endif
			<!-- {{$dataAnterior = requestDataOracle($cliente->datainativacao, false)}} -->
		@endforeach
	</table>
	@else
	<br />
	<p style="text-align:center" class="negrito">Nenhum resultado encontrado para estes filtros!</p>
	@endif
</div>

@endsection