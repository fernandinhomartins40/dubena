@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
    @media print{@page{size:landscape;}}
</style>
<div style="font-size:9.5px">
@if(isset($clientes) && count($clientes) > 0)
	<table style="margin-top:7px; margin-left: -5px; min-width:100%; font-size:9.5px">
        <tbody id="repor-list" name="report-list">
            <!-- {{$count = 0}} -->
            <!-- {{$cidadeAnterior = null}} -->
            <!-- {{$first = true}} -->
            @foreach($clientes as $cliente)
            @if($cidadeAnterior != $cliente->cidade)
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8"><br /><hr /></td>
                    </tr>
                @endif
                <!-- {{$first = false}} -->
                <tr>
                    <td colspan="8" class="top-style fontSize15 nobord">{{$cliente->cidade}}</td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque">Cód</th>
                    <th class="bordered destaque align-left">Cliente</th>
                    <th class="bordered destaque align-left">Tipo</th>
                    <th class="bordered destaque align-left">CNPJ/CPF</th>
                    <th class="bordered destaque align-left">Telefone</th>
                    <th class="bordered destaque align-left">Segmento</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th></th>
                </tr>
                <!-- {{$city = $clientes->where('cidade',$cliente->cidade)->count()}} -->
            @endif
            <!-- 
                {{$telefone = $cliente->telefone}}
                {{$telefone = str_replace('(','',$telefone)}}
                {{$telefone = str_replace(')','',$telefone)}}
            -->
            <tr>
                <td></td>
                <td class="bordered">{{$cliente->id}}</td>
                <td class="bordered align-left">{{substr($cliente->razao_social, 0, 45)}}</td>
                <td class="bordered align-left">{{$cliente->tipo}}</td>
                <td class="bordered align-left">{{$cliente->cnpj == null ? $cliente->cpf : $cliente->cnpj}}</td>
                <td class="bordered align-left">{{$telefone}}</td>
                <td class="bordered align-left">{{$cliente->segmento}}</td>
                <td class="bordered align-left">{{$cliente->endereco}}</td>
                <td></td>
            </tr>
            <!-- {{$cidadeAnterior = $cliente->cidade}} -->
            <!-- {{$count++}} -->
            @if($count == $city)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="6">Total:</th>
                    <th class="bordered destaque">{{$city}}</th>
                    <th></th>
                </tr>
                <!-- {{$count=0}} -->
            @endif
            @endforeach
        </tbody>
    </table>
@else
    <p class="negrito" style="text-align:center;font-size:12px;">Nenhum resultado encontrado para estes filtros!</p>
@endif
</div>

@endsection