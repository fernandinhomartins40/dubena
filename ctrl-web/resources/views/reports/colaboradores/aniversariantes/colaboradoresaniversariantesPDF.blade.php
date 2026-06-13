@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	td, th{border: none;}
	@media print {
		.conteudo-table{padding-top: 18px}
	}
</style>
<div class="fontSize14 conteudo-table">
	<!-- {{$first = true}} -->
	@if(isset($colaboradores) && count($colaboradores) > 0)
	<table style="margin-top:7px; margin-left: -5px; min-width:100%; font-size: 11px">
		@if($familiares)
		<!-- <div class="familiares"> -->
			@foreach($colaboradores as $colaborador) 
			@if(isset($colaborador->total))
			<tr style="margin-top: 10px">
				<th colspan="6"></th>
			</tr>
			<tr>
				<td></td>	
				<td class="bordered destaque negrito align-left" colspan="3">Total Geral: </td>
				<td class="bordered destaque negrito">{{$colaborador->total}}</td>
				<td></td>
			</tr>
			@elseif(isset($colaborador->totalMes))
			<tr>
				<td></td>	
				<td class="bordered destaque negrito align-left" colspan="3">Total: </td>
				<td class="bordered destaque negrito">{{$colaborador->totalMes}}</td>
				<td></td>
			</tr>
			@elseif(isset($colaborador->mes))	
			<tr>
				<td colspan="6">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$colaborador->mes}}</td>	
			</tr>
			<tr>
				<th></th>	
				<th class="bordered destaque">Dia</th>
				<th class="bordered destaque align-left">Familiar</th>
				<th class="bordered destaque">Parentesco</th>
				<th class="bordered destaque align-left">Colaborador</th>
				<th></th>	
			</tr>
			@elseif(isset($colaborador->empresa_id))
			@if(!$first)
			<tr style="padding-top: 10px">
				<td colspan="6"><br /><hr /></td>
			</tr>
			@endif
			<tr>
				<td class="negrito" colspan="5">{{$colaborador->empresa}}</td>	
			</tr>
			<!-- {{$first = false}} -->
			@else
			<tr>
				<td></td>	
				<td class="bordered">{{$colaborador->dia}}</td>
				<td class="bordered align-left">{{$colaborador->familiar}}</td>
				<td class="bordered">{{$colaborador->parentesco}}</td>
				<td class="bordered align-left">{{$colaborador->colaborador}}</td>
				<th></th>	
			</tr>
			@endif
			@endforeach
		<!-- </div> -->
		@else
		<!-- <div class="colaboradores"> -->
			@foreach($colaboradores as $colaborador) 
			@if(isset($colaborador->total))
			<tr style="margin-top: 10px"><th colspan="6"></th></tr>
			<tr>
				<td></td>	
				<td class="bordered destaque negrito align-left" colspan="3">Total Geral: </td>
				<td class="bordered destaque negrito">{{$colaborador->total}}</td>
				<td></td>
			</tr>
			@elseif(isset($colaborador->totalMes))
			<tr>
				<td></td>	
				<td class="bordered destaque negrito align-left" colspan="3">Total: </td>
				<td class="bordered destaque negrito">{{$colaborador->totalMes}}</td>
				<td></td>
			</tr>
			@elseif(isset($colaborador->mes))	
			<tr>
				<td colspan="6">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$colaborador->mes}}</td>	
			</tr>
			<tr>
				<th></th>	
				<th class="bordered destaque">Dia</th>
				<th class="bordered destaque">Cód.</th>
				<th class="bordered destaque align-left">Nome</th>
				<th class="bordered destaque align-left">Cargo</th>
				<th></th>	
			</tr>
			@elseif(isset($colaborador->empresa_id))
			@if(!$first)
			<tr style="padding-top: 10px">
				<td colspan="6"><br /><hr /></td>
			</tr>
			@endif
			<tr>
				<td class="negrito" colspan="5">{{$colaborador->empresa}}</td>	
			</tr>
			<!-- {{$first = false}} -->
			@else
			<tr>
				<td></td>	
				<td class="bordered">{{$colaborador->dia}}</td>
				<td class="bordered">{{$colaborador->colaborador_id}}</td>
				<td class="bordered align-left">{{$colaborador->nome}}</td>
				<td class="bordered align-left">{{$colaborador->cargo}}</td>
				<th></th>	
			</tr>
			@endif
			@endforeach
		<!-- </div> -->
		@endif
	</table>
	@else
	<p style="text-align:center" class="negrito">Nenhum resultado encontrado para estes filtros!</p>
	@endif
</div>

@endsection