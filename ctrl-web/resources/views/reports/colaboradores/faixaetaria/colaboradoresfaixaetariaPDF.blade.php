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
	<table style="margin-top:7px; margin-left: -15px; min-width:100%; font-size: 13px">
		@foreach($colaboradores as $colaborador) 
		@if(isset($colaborador->empresa))
		@if(!$first)
		<tr style="padding-top: 10px;">
			<td colspan="7"><br /><hr style="margin-left: 30px" /></td>
		</tr>
		@endif
		<tr>
			<td></td>	
			<td class="negrito" colspan="4">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$colaborador->empresa}}</td>
		</tr>
		<!-- {{$first = false}} -->
		@elseif(isset($colaborador->colaborador))
		<tr>
			<td></td>
			<td></td>		
			<td colspan="3" style="padding-left: -3px"><span>{{$colaborador->colaborador}}</span></td>	
			<td></td>		
		</tr>
		<tr>
			<td></td>	
			<td></td>	
			<td class="bordered destaque">Idade</td>	
			<td class="bordered destaque align-left">Nome Familiar</td>	
			<td class="bordered destaque">Parentesco</td>	
			<td></td>	
			<td></td>		
		</tr>
		@elseif(isset($colaborador->familiar))
		<tr>
			<td></td>	
			<td></td>	
			<td class="bordered">{{$colaborador->idade}}</td>
			<td class="bordered align-left">{{$colaborador->familiar}}</td>
			<td class="bordered">{{$colaborador->parentesco}}</td>
			<td></td>	
			<td></td>		
		</tr>
		@endif    
		@if ($loop->last && isset($colaborador->totalGeral))
		<tr><td colspan="6"></td></tr>
		<tr>
			<td></td>	
			<td></td>	
			<td class="bordered destaque negrito align-left" colspan="2">Total Geral:</td>	
			<td class="bordered destaque negrito">{{$colaborador->totalGeral}}</td>	
			<td></td>	
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