@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
<!-- {{$first = true}} -->
@if(isset($pedidos) && count($pedidos))
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($pedidos as $pedido)
            @if ($loop->first)
                <tr>
                    <th></th>
                    <th class="bordered destaque">Pedido</th>
                    <th class="bordered destaque align-left">Cliente</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th class="bordered destaque">Setor</th>
                    <th class="bordered destaque">Produto</th>
                    <th class="bordered destaque">Qtde.</th>
                    <th class="bordered destaque money">Valor</th>
                    <th></th>
                </tr>
            @endif
            @if(isset($pedido->pedido_id))
                <tr>
                    <td></td>
                    <td class="bordered">{{$pedido->pedido_id}}</td>
                    <td class="bordered align-left">{{$pedido->cliente}}</td>
                    <td class="bordered align-left">{{$pedido->endereco}}</td>
                    <td class="bordered">{{$pedido->setor}}</td>
                    <td class="bordered">{{$pedido->produto}}</td>
                    <td class="bordered">{{$pedido->quantidade}}</td>
                    <td class="bordered money">{{requestNumeroDecimalOracle($pedido->valor)}}</td>
                    <td></td>
                </tr>
            @endif
            @if ($loop->last)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="5">Total Geral</th>
                    <th class="bordered destaque">{{$pedido->quantidadetotal}}</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($pedido->total)}}</th>
                    <th></th>
                </tr>
            @endif
        @endforeach
    </table>
@else
    <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection