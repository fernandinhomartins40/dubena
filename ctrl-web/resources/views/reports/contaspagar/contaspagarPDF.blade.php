@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	@page{margin-bottom: 15px !important;}
	@media print {
		@page{size: landscape;}
	}
</style>
<div class="fontSize14">
	<!-- {{$first = true}} -->
	@if(isset($parcelas) && count($parcelas) > 0)
	<table style="margin-top:8px; margin-left: -5px; min-width:100%; font-size: 11px">
		@foreach($parcelas as $parcela) 
		@if(isset($parcela->totalCondPgto))
		<!-- <tr><td colspan="12"></td></tr>
		<tr>
			<td></td>
			<td></td>
			<td class="bordered destaque align-left" colspan="5">Total Condição de Pagamento: </td>
			<td class="bordered destaque money">{{$parcela->totalValor}}</td>
			<td class="bordered destaque money">{{$parcela->totalLiquido}}</td>
			<td class="bordered destaque">{{$parcela->totalCondPgto}}</td>	
			<td></td>
		</tr>
		<tr><td colspan="12"></td></tr> -->
		@elseif(isset($parcela->totalGeral))
		<tr><td colspan="12"></td></tr>
		<tr>
			<td></td>
			<td class="bordered destaque align-left negrito" colspan="5">Total Geral: </td>
			<td class="bordered destaque money negrito">{{$parcela->totalValor}}</td>
			<td class="bordered destaque money negrito">{{$parcela->totalLiquido}}</td>
			<td class="bordered destaque negrito">{{$parcela->totalGeral}}</td>	
			<td></td>
		</tr>
		@elseif(isset($parcela->totalData))
		<tr>
			<td></td>
			<td class="bordered destaque align-left negrito" colspan="5">Total {{$ordem}}</td>
			<td class="bordered destaque money negrito">{{$parcela->totalValor}}</td>
			<td class="bordered destaque money negrito">{{$parcela->totalLiquido}}</td>
			<td class="bordered destaque negrito">{{$parcela->totalData}}</td>	
			<td></td>
		</tr>
		@elseif(isset($parcela->totalCliente))
		<tr>
			<td></td>
			<td class="bordered destaque align-left negrito" colspan="5">Total Fornecedor: </td>
			<td class="bordered destaque money negrito">{{$parcela->totalValor}}</td>
			<td class="bordered destaque money negrito">{{$parcela->totalLiquido}}</td>
			<td class="bordered destaque negrito">{{$parcela->totalCliente}}</td>	
			<td></td>
		</tr>
		@elseif(isset($parcela->empresa))
		@if(!$first)
		<tr style="padding-top: 10px">
			<td colspan="12"><br /><hr /></td>
		</tr>
		@endif
		<tr>
			<td class="negrito" colspan="5">
				{{$parcela->empresa}}
			</td>	
		</tr>
		<!-- {{$first = false}} -->
		@elseif(isset($parcela->pagamento))
		<tr>	
			<td colspan="12" class="negrito fontSize14">&nbsp;&nbsp;{{$parcela->pagamento}}</td>	
		</tr>
		@elseif(isset($parcela->ordemCliente))
		<tr><td colspan="9"></td></tr>
		<tr>	
			<td colspan="12">
				&nbsp;&nbsp;&nbsp;&nbsp;
				@if(isset($isPdf))
					&nbsp;&nbsp;&nbsp;&nbsp;
				@endif
				{{$parcela->cliente}}
			</td>	
		</tr>
		<tr>
			<th></th>	
			<th class="bordered destaque">Cód. Parcela</th>
			<th class="bordered destaque">Documento</th>
			<th class="bordered destaque">Emissão</th>
			<th class="bordered destaque">Vencimeto</th>
			<th class="bordered destaque">Baixa</th>
			<th class="bordered destaque">Valor</th>
			<th class="bordered destaque">Valor Líquido</th>
			<th class="bordered destaque">Situação</th>
			<th></th>	
		</tr>
		@elseif(isset($parcela->ordemData))
		<tr><td colspan="9"></td></tr>
		<tr>	
			<td colspan="12">
				&nbsp;&nbsp;&nbsp;&nbsp;
				@if(isset($isPdf))
					&nbsp;&nbsp;
				@endif
				{{$parcela->ordemData}}
			</td>	
		</tr>
		<tr>
			<th></th>	
			<th class="bordered destaque">Cód. Parcela</th>
			<th class="bordered destaque">Documento</th>
			@if($ordem == 'Emissão: ')
			<th class="bordered destaque align-left">Fornecedor</th>
			<th class="bordered destaque">Vencimento</th>
			<th class="bordered destaque">Baixa</th>
			@elseif($ordem == 'Vencimento: ')
			<th class="bordered destaque align-left">Fornecedor</th>
			<th class="bordered destaque">Emissão</th>
			<th class="bordered destaque">Baixa</th>
			@elseif($ordem == 'Pago: ')
			<th class="bordered destaque align-left">Fornecedor</th>
			<th class="bordered destaque">Emissão</th>
			<th class="bordered destaque">Vencimento</th>
			@else
			<th class="bordered destaque">Baixa</th>
			<th class="bordered destaque align-left">Emissão</th>
			<th class="bordered destaque">Vencimento</th>
			@endif
			<th class="bordered destaque">Valor</th>
			<th class="bordered destaque">Valor Líquido</th>
			<th class="bordered destaque">Situação</th>
			<th></th>	
		</tr>
		@elseif(isset($parcela->situacao))
		<tr>
			<td></td>
			<td class="bordered">{{$parcela->parcela_id}}</td>
			<td class="bordered">{{$parcela->documento}}</td>
			@if($ordem == 'Emissão: ')
			<td class="bordered align-left">{{substr($parcela->cliente, 0, 40)}}</td>
			<td class="bordered">{{$parcela->datavencimento}}</td>
			<td class="bordered">{{$parcela->datahorabaixa}}</td>
			@elseif($ordem == 'Vencimento: ')
			<td class="bordered align-left">{{substr($parcela->cliente, 0, 40)}}</td>
			<td class="bordered">{{$parcela->datacompetencia}}</td>
			<td class="bordered">{{$parcela->datahorabaixa}}</td>
			@elseif($ordem == 'Pago: ')
			<td class="bordered align-left">{{substr($parcela->cliente, 0, 40)}}</td>
			<td class="bordered">{{$parcela->datacompetencia}}</td>
			<td class="bordered">{{$parcela->datavencimento}}</td>
			@else
			<td class="bordered">{{$parcela->datacompetencia}}</td>
			<td class="bordered">{{$parcela->datavencimento}}</td>
			<td class="bordered">{{$parcela->datahorabaixa}}</td>
			@endif
			<td class="bordered money">{{$parcela->valor}}</td>
			<td class="bordered money">{{$parcela->valorefetivado}}</td>
			<td class="bordered">{{$parcela->situacao}}</td>	
			<td></td>
		</tr>
		@endif
		@endforeach
	</table>
	@else
	<br />
	<p style="text-align:center" class="negrito">Nenhum resultado encontrado para estes filtros!</p>
	@endif
</div>

@endsection