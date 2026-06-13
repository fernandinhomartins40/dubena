@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	@page{margin-bottom: 15px !important;}
</style>
<div class="fontSize14">
	<!-- {{$first = true}} -->
	@if(isset($comodatos) && count($comodatos)>0)
	<table style="margin-top:8px; margin-left: -5px; min-width:100%;">
		<tr>
			<th></td>
			<th class="bordered destaque align-left">Produto</th>
			<th class="bordered destaque">Comodato Distribuidora</th>
			<th class="bordered destaque">Comodato Cliente</th>
			<th class="bordered destaque">Estoque Próprio</th>	
			<th class="bordered destaque">Estoque Total</th>
			<th></th>
		</tr>
		@foreach($comodatos as $comodato) 
		@if(isset($comodato->produto_id))
		<tr>
			<td></td>	
			<td class="bordered align-left">{{$comodato->produto}}</td>
			<td class="bordered">{{$comodato->quantidaderecebido}}</td>
			<td class="bordered">{{$comodato->quantidadeenviado}}</td>
			<td class="bordered">{{$comodato->estoqueproprio}}</td>
			<td class="bordered">{{$comodato->quantidadeestoque}}</td>
			<td></td>	
		</tr>
		@elseif(isset($comodato->totalestoqueproprio))
		<tr><td colspan="6"></td></tr>
		<tr>
			<td></td>	
			<td class="bordered destaque align-left negrito">Total:</td>
			<td class="bordered destaque negrito">{{$comodato->totalrecebido}}</td>
			<td class="bordered destaque negrito">{{$comodato->totalenviado}}</td>
			<td class="bordered destaque negrito">{{$comodato->totalestoqueproprio}}</td>
			<td class="bordered destaque negrito">{{$comodato->totalestoque}}</td>
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