@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	@page{margin-bottom: 15px !important;}
</style>
<div class="fontSize14">
	<!-- {{$first = true}} -->
	@if(isset($comodatos) && count($comodatos)>0)
	<table style="margin-top:8px; margin-left: -15px; min-width:100%;">
		@foreach($comodatos as $comodato) 
		@if(isset($comodato->cliente))
		@if(!$first)
		<tr style="padding-top: 10px">
			<td colspan="6"><br /><hr style="margin-left: 10px" /></td>
		</tr>
		@endif
		<tr>	
			<td class="negrito" colspan="6">{{$comodato->cliente}}</td>	
		</tr>
		<tr >
			<th></th>	
			<th class="bordered destaque align-left">Produto</th>
			<th class="bordered destaque">Qtde em Comodato</th>
			<th class="bordered destaque">Qtde em Compras</th>
			<th class="bordered destaque">Giro</th>
			<th></th>	
		</tr>
		<!-- {{$first = false}} -->
		@elseif(isset($comodato->totalCliente))
		<tr>
			<td></td>	
			<td class="bordered destaque align-left negrito">Total: </td>
			<td class="bordered destaque negrito">{{$comodato->totalComodatadoCliente}}</td>
			<td class="bordered destaque negrito">{{$comodato->totalCompradoCliente}}</td>
			<td class="bordered destaque negrito">{{$comodato->mediaGiroCliente}}</td>
			<td></td>	
		</tr>
		@elseif(isset($comodato->totalComodatado))
		<tr><td colspan="6"></td></tr>
		<tr>
			<td></td>
			<td class="bordered destaque align-left negrito">Total Geral: </td>
			<td class="bordered destaque negrito">{{$comodato->totalComodatado}}</td>
			<td class="bordered destaque negrito">{{$comodato->totalComprado}}</td>
			<td class="bordered destaque negrito">{{$comodato->mediaGiro}}</td>	
			<td></td>
		</tr>
		@else
		<tr>
			<td></td>
			<td class="bordered align-left">{{$comodato->produto}}</td>
			<td class="bordered">{{$comodato->qdecomodatado}}</td>
			<td class="bordered">{{$comodato->qdecomprado}}</td>
			<td class="bordered">{{$comodato->giro}}</td>	
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