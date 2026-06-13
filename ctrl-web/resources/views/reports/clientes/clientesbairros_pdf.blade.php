@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @media print{@page{size:landscape;}}
</style>
<div class="fontSize11">
<!-- {{$first = true}} -->
@if(isset($clientes) && count($clientes) > 0)
	<table style="margin-top:7px; margin-left: -5px; min-width:100%;">
		@foreach($clientes as $cliente)
			@if(isset($cliente->total))
				<tr>
					<th></th>
					<th class="bordered destaque align-left" colspan="6">Total</th>
					<th class="bordered destaque">{{$cliente->total}}</th>
					<th></th>
				</tr>
			@elseif(isset($cliente->cidade_id))
				@if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="10" class="nobord"><br /><hr /></td>
                    </tr>
                @endif
				<tr>
                    <td colspan="3" class="top-style fontSize15 nobord">Cidade: {{$cliente->cidade}}</td>
                </tr>
				<!-- {{$first = false}} -->
			@elseif(isset($cliente->bairro_id))
                <tr>
                    <td colspan="3" class="fontSize_13 nobord pad-top-bot-5">&nbsp;&nbsp;Bairro: {{$cliente->bairro}}</td>
                </tr>
				<tr>
					<th></th>
					<th class="bordered destaque">Cód.</th>
					<th class="bordered destaque align-left">Cliente</th>
					<th class="bordered destaque align-left">Telefone</th>
					<th class="bordered destaque">Últ. Compra</th>
					<th class="bordered destaque align-left">Endereço</th>
					<th class="bordered destaque">Setor</th>
					<th class="bordered destaque">Segmento</th>
					<th></th>
				</tr>
			@elseif(isset($cliente->cliente_id))
				<tr>
					<td></td>
					<td class="bordered">{{$cliente->cliente_id}}</td>
					<td class="bordered align-left">{{$cliente->cliente}}</td>
					<td class="bordered align-left">{{$cliente->telefone}}</td>
					<td class="bordered">{{$cliente->ultimacompra}}</td>
					<td class="bordered align-left">{{$cliente->endereco}}</td>
					<td class="bordered">{{$cliente->setor}}</td>
					<td class="bordered">{{$cliente->segmento}}</td>
					<td></td>
				</tr>
			@endif
			@if ($loop->last)
				<tr>
					<td colspan="9"></td>
				</tr>
				<tr>
					<th></th>
					<th class="bordered destaque align-left" colspan="6">Total Geral</th>
					<th class="bordered destaque">{{$cliente->totalgeral}}</th>
					<th></th>
				</tr>
			@endif
		@endforeach
	</table>
@else
	<p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
@endif
</div>
@endsection