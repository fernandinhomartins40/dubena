@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize12">
<!-- {{$first = true}} -->
@if(isset($clientes) && count($clientes))
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
    <tr>
        <th class="bordered destaque align-left" colspan="2">Quantidade Pedidos</th>
        <th class="bordered destaque">{{count($clientes)}}</th>
    </tr>
    <tr>
        <th class="bordered destaque align-left" colspan="2">Quantidade Pedidos Avaliados</th>
        <th class="bordered destaque">{{$qtrating}}</th>
    </tr>
    <tr>
        <th class="bordered destaque align-left" colspan="2">Quantidade Pedidos Entrega não Realizada</th>
        <th class="bordered destaque">{{$qtnaoentregue}}</th>
    </tr>
    <tr>
        <th class="bordered destaque align-left" colspan="2">Avaliação Média</th>
        <th class="bordered destaque">{{$qtrating>0?number_format(($somarating/$qtrating), 2, ',', '.'):0}}</th>
    </tr>

    <tr>
        <th class="bordered destaque">Pedido</th>
        <th class="bordered destaque">Data</th>
        <th class="bordered destaque">Status</th>
        <th class="bordered destaque align-left">Cliente</th>
        <th class="bordered destaque align-left">Avaliação</th>
        <th class="bordered destaque">Nota</th>
        <th></th>
    </tr>

        @foreach($clientes as $cliente)
            <tr>
                <td class="bordered">{{$cliente->erp_id}}</td>
                <td class="bordered">{{$cliente->data}}</td>
                <td class="bordered">{{$cliente->status}}</td>
                <td class="bordered align-left">{{$cliente->cliente}}</td>
                <td class="bordered align-left">{{$cliente->mensagem}}</td>
                <td class="bordered">{{$cliente->rating==null?'':number_format($cliente->rating, 0, ',', '.')}}</td>
                <td></td>
            </tr>                
        @endforeach
    </table>
@else
    <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection