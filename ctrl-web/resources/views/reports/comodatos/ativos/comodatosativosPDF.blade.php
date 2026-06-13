@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	@page{margin-bottom: 15px !important;}
</style>
<div class="fontSize14">
	<!-- {{$first = true}} -->
	@if(isset($comodatos) && count($comodatos)>0)
	<table style="margin-top:8px; margin-left: -5px; min-width:100%; max-width: 100%">
		@foreach($comodatos as $comodato) 
		@if(isset($comodato->vencimento))
		@if(!$first)
		<tr style="padding-top: 10px">
			<td colspan="6"><br /><hr /></td>
		</tr>
		@endif
		<tr>
			<td class="negrito" colspan="6">Vencimento: {{$comodato->vencimento}}</td>	
		</tr>
		<!-- {{$first = false}} -->
		@elseif(isset($comodato->totalData))
		<tr><td colspan="6"></td></tr>
		<tr>
			<td></td>
			<td class="bordered destaque align-left negrito" colspan="3">Total Vencimento: </td>
			<td class="bordered destaque negrito">{{$comodato->totalData}}</td>	
			<td></td>
		</tr>
		@elseif(isset($comodato->cliente))
		<tr><td colspan="6"></td></tr>
		<tr>	
			<td colspan="6">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$comodato->cliente}}</td>	
		</tr>
		<tr>
			<th></th>	
			<th class="bordered destaque">Cód. Comodato</th>
			<th class="bordered destaque">Data Contrato</th>
			<th class="bordered destaque align-left">Produto</th>
			<th class="bordered destaque">Quantidade</th>
			<th></th>	
		</tr>
		@elseif(isset($comodato->comodato_id))
		<tr>
			<td></td>	
			<td class="bordered">{{$comodato->comodato_id}}</td>
			<td class="bordered">{{$comodato->datacomodato}}</td>
			<td class="bordered align-left">{{$comodato->produto}}</td>
			<td class="bordered">{{$comodato->quantidade}}</td>
			<td></td>	
		</tr>
		@elseif(isset($comodato->totalGeral))
		<tr><td colspan="6"></td></tr>
		<tr>
			<td></td>
			<td class="bordered destaque align-left negrito" colspan="3">Total Geral: </td>
			<td class="bordered destaque negrito">{{$comodato->totalGeral}}</td>	
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