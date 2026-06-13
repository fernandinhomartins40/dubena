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
	@if(isset($colaboradores) && $colaboradores->filter(function($c) {return isset($c->colaborador);})->count())
	<table style="margin-top:7px; margin-left: -5px; min-width:100%; font-size: 11px">
		@foreach($colaboradores as $colaborador) 
		@if(isset($colaborador->totalgeral))
		<tr style="margin-top: 10px"><td colspan="8"></td></tr>
		<tr>
			<td></td>	
			<td class="bordered destaque align-left negrito" colspan="6">Total Geral: </td>
			<td class="bordered destaque negrito">{{$colaborador->totalgeral}}</td>
			<td></td>
		</tr>
		@elseif(isset($colaborador->empresaExists))
		@if(!$first)
		<tr style="padding-top: 10px">
			<td colspan="10"><br /><hr /></td>
		</tr>
		@endif
		<tr>
			<td class="negrito" colspan="8">{{$colaborador->empresa}}</td>	
		</tr>
		<tr>
			<th></th>	
			<th class="bordered destaque">Próximas Férias</th>	
			<th class="bordered destaque align-left">Colaborador</th>	
			<th class="bordered destaque">Admissão</th>	
			<th class="bordered destaque">Últimas Férias</th>	
			<th class="bordered destaque">Sem Férias a</th>	
			<th class="bordered destaque">Limite máximo</th>	
			<th class="bordered destaque">Dias em Férias</th>	
			<th></th>	
		</tr>
		<!-- {{$first = false}} -->
		@elseif(isset($colaborador->totalEmpresa))
		<tr>
			<td></td>	
			<td class="bordered destaque align-left negrito" colspan="6">Total: </td>
			<td class="bordered destaque negrito">{{$colaborador->totalEmpresa}}</td>
			<td></td>
		</tr>
		@elseif(isset($colaborador->proximasferias))
		<tr>
			<td></td>	
			<td class="bordered">{{requestDataOracle($colaborador->proximasferias)}}</td>
			<td class="bordered align-left">{{$colaborador->colaborador}}</td>
			<td class="bordered">{{$colaborador->dataadmissao}}</td>
			<td class="bordered">{{$colaborador->ultimasferias}}</td>
			<td class="bordered">{{$colaborador->mesessemferias}}</td>
			<td class="bordered">{{requestDataOracle($colaborador->limitemaximo)}}</td>
			<td class="bordered">{{$colaborador->diasultimasferias}}</td>
			<td></td>	
		</tr>
		@endif
		@endforeach
	</table>
	@else
	<p style="text-align:center" class="negrito">{{$erro ? $erro : 'Nenhum resultado encontrado para estes filtros!'}}</p>
	@endif
</div>

@endsection