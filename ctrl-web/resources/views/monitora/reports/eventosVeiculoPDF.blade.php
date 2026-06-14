@extends('monitora.layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
	td, th{border: none;}
	@media print {
		.conteudo-table{padding-top: 18px}
	}
</style>
<div class="fontSize14 conteudo-table">
	<!-- {{$first = true}} -->
        <p style="margin-left:50px;font-weight:bold;">Veículo: {{$veiculo->descricao}}</p>
        <p style="margin-left:50px;font-weight:bold;">Placa: {{$veiculo->placa}}</p>
        <p style="margin-left:50px;font-weight:bold;">Motorista: {{$veiculo->motorista}}</p>
        <p style="margin-left:50px;font-weight:bold;">Velocidade Máxima: {{$veiculo->velocidade_maxima.' km/h'}}</p>
        <p style="margin-left:50px;font-weight:bold;"><br>EXCESSO DE VELOCIDADE</p>
	@if(isset($velocidades) && count($velocidades) > 0)
	<table style="margin-top:7px; margin-left: -5px; min-width:100%; font-size: 11px">
            <tr>
                    <th></th>	
                    <th class="bordered destaque">Data/Hora</th>
                    <th class="bordered destaque">Velocidade</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th></th>	
            </tr>
            @foreach($velocidades as $velocidade) 
            <tr>
                    <td></td>	
                    <td class="bordered">{{Carbon\Carbon::parse($velocidade->dhposition)->format('d/m/Y H:i:s')}}</td>
                    <td class="bordered">{{number_format($velocidade->speed,2,',','.').' km/h'}}</td>
                    <td class="bordered align-left">{{$velocidade->address}}</td>
                    <th></th>	
            </tr>
            @endforeach
	</table>
	@else
	<p style="text-align:center" class="negrito">Nenhum excesso de velocidade encontrado para esse filtro!</p>
	@endif
        
        <p style="margin-left:50px;font-weight:bold;"><br>PARADAS</p>
	@if(isset($parados) && count($parados) > 0)
	<table style="margin-top:7px; margin-left: -5px; min-width:100%; font-size: 11px">
            <tr>
                    <th></th>	
                    <th class="bordered destaque">Data/Hora</th>
                    <th class="bordered destaque">Tempo Parado</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th></th>	
            </tr>
            @foreach($parados as $parado) 
            <tr>
                    <td></td>	
                    <td class="bordered">{{Carbon\Carbon::parse($parado->data_inicio)->format('d/m/Y H:i:s')}}</td>
                    <td class="bordered">{{(gmdate("H:i:s", $parado->tempo_parado)).' '}}</td>
                    <td class="bordered align-left">{{$parado->address}}</td>
                    <th></th>	
            </tr>
            @endforeach
	</table>
	@else
	<p style="text-align:center" class="negrito">Nenhuma parada encontrada para esse filtro!</p>
	@endif
</div>

@endsection