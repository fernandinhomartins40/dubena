@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	td, th{border: none;}
	@media print{
		.conteudo-table{padding-top: 18px}
	}
</style>
<div class="fontSize14 conteudo-table">
	<!-- {{$first = true}} -->
	@if(isset($colaboradores) && count($colaboradores) > 0)
	<table style="margin-top:7px; margin-left: -15px; min-width:100%; font-size: 11px">
		@foreach($colaboradores as $colaborador) 
		@if(isset($colaborador->total))
		<tr style="margin-top: 10px"><th colspan="6"></th></tr>
		<tr>
			<td></td>	
			<td class="bordered destaque align-left negrito" colspan="3">Total Geral: </td>
			<td class="bordered destaque negrito">{{$colaborador->total}}</td>
			<td></td>
		</tr>
		@elseif(isset($colaborador->empresa))
		@if(!$first)
		<tr style="padding-top: 10px">
			<td colspan="6"><br /><hr /></td>
		</tr>
		@endif
		<tr>
			<td class="negrito" colspan="5">&nbsp;&nbsp;&nbsp;&nbsp;{{$colaborador->empresa}}</td>	
			<td></td>	
		</tr>
		<!-- {{$first = false}} -->
		@elseif(isset($colaborador->exame))
		<tr>
			<td colspan="5">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$colaborador->exame}}</td>	
		</tr>
		<tr>
			<th></th>	
			<th class="bordered destaque">Cód.</th>	
			<th class="bordered destaque align-left">Colaborador</th>	
			<th class="bordered destaque">Último Exame</th>	
			<th class="bordered destaque">Vencimeto Exame</th>	
			<th></th>	
		</tr>
		@else
		<tr>
			<td></td>	
			<td class="bordered">{{$colaborador->colaborador_id}}</td>
			<td class="bordered align-left">{{$colaborador->nome}}</td>
			<td class="bordered">{{$colaborador->data}}</td>
			<td class="bordered">{{$colaborador->datavencimento}}</td>
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