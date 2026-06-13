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
        <tr>
            <td colspan="8" class="top-style fontSize15 nobord" style="margin-left:20px;">{{$filtrosetor}}</td>
        </tr>
        <tr>
            <th></th>
            <th class="bordered destaque">Cód</th>
            <th class="bordered destaque align-left">Nome/Razão Social</th>
            <th class="bordered destaque align-left">Endereço</th>
            <th class="bordered destaque align-left">Telefone</th>
            <th class="bordered destaque align-left">e-mail</th>
            <th></th>
        </tr>
        @foreach($clientes as $cliente)
            <!-- 
                {{$telefone = $cliente->telefone}}
                {{$telefone = str_replace('(','',$telefone)}}
                {{$telefone = str_replace(')','',$telefone)}}
            -->
            <tr>
                <td></td>
                <td class="bordered">{{$cliente->id}}</td>
                <td class="bordered align-left">{{substr($cliente->razao_social, 0, 45)}}</td>
                <td class="bordered align-left">{{$cliente->endereco}}</td>
                <td class="bordered align-left">{{$telefone}}</td>
                <td class="bordered align-left">{{$cliente->email}}</td>
                <td></td>
            </tr>
            <!-- {{$count++}} -->
            @endforeach
            <tr>
                <th></th>
                <th class="bordered destaque align-left" colspan="4">Total:</th>
                <th class="bordered destaque">{{$count}}</th>
                <th></th>
            </tr>
        </tbody>
    </table>
@else
    <p class="negrito" style="text-align:center;font-size:12px;">Nenhum resultado encontrado para estes filtros!</p>
@endif
</div>

@endsection