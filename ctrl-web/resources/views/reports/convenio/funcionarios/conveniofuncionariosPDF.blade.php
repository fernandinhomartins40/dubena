@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	@page{margin-bottom: 15px !important;}
</style>
<div class="fontSize14">
	<!-- {{$first = true}} -->
	@if(isset($clientes) && count($clientes)>0)
	<table style="margin-top:8px; margin-left: -5px; min-width:100%; font-size: 11px">
		@foreach($clientes as $cliente) 
		@if(isset($cliente->totalGeral))
		<tr><td colspan="4"></td></tr>
		<tr><td></td></tr>
		<tr>
			<td></td>	
			<td class="bordered destaque negrito align-left" colspan="2">Total Geral: </td>
			<td class="bordered destaque negrito">{{$cliente->totalGeral}}</td>
			<td></td>	
		</tr>
		@elseif(isset($cliente->totalSetor))
		<tr class="negrito">
			<td></td>
			<td class="bordered destaque align-left" colspan="2">Total Setor: </td>
			<td class="bordered destaque">{{$cliente->totalSetor}}</td>	
			<td></td>
		</tr>
		@elseif(isset($cliente->empresaconvenio_id))
		@if(!$first)
		<tr style="padding-top: 10px">
			<td colspan="7"><br /><hr /></td>
		</tr>
		@endif
		<tr>
			<td class="negrito" colspan="5">{{$cliente->empresaconvenio}}</td>	
		</tr>
		<!-- {{$first = false}} -->
		@elseif(isset($cliente->setor))
		<tr>	
			<td colspan="5" id="setor-td">
				&nbsp;&nbsp;&nbsp;&nbsp;
				@if(isset($geraPdf))
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				@endif
				{{$cliente->setor}}
			</td>	
		</tr>
		<tr>
			<th></th>	
			<th class="bordered destaque">Cód.</th>
			<th class="bordered destaque align-left">Conveniado</th>
			<th class="bordered destaque align-left">Endereço</th>
			<th></th>	
		</tr>
		@elseif(isset($cliente->cliente_id))
		<tr>
			<td></td>	
			<td class="bordered">{{$cliente->cliente_id}}</td>
			<td class="bordered align-left">{{$cliente->nome}}</td>
			<td class="bordered align-left">{{$cliente->endereco}}</td>
			<td></td>	
		</tr>
		@elseif(isset($cliente->totalConvenio))
		<tr style="margin-top: 10px"><th colspan="6"></th></tr>
		<tr class="negrito">
			<td></td>
			<td class="bordered destaque align-left" colspan="2">Total Convênio: </td>
			<td class="bordered destaque">{{$cliente->totalConvenio}}</td>	
			<td></td>
		</tr>
		@endif
		@endforeach
	</table>
	@else
	<p style="text-align:center" class="negrito">Nenhum resultado encontrado para estes filtros!</p>
	@endif
</div>

@endsection