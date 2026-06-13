@extends('layouts.reportNoBootstrap')

@section('content')
<style type="text/css">
@media print{@page{size:landscape;}}
</style>
<div class="fontSize13">
<!-- {{$first = true}} -->
@if(isset($clientes) && count($clientes) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($clientes as $cliente)
            @if(isset($cliente->clientestotal))
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Total</th>
                    <th class="bordered destaque">{{$cliente->clientestotal}}</th>
                    <th class="bordered destaque" colspan="2"></th>
                    <th class="bordered destaque">{{$cliente->comprastotal}}</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->setor))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="8"><br /><hr /></td>
                    </tr>
                @endif
                <!-- {{$first = false}} -->
                <tr>
                    <td colspan="3" class="negrito" style="font-size: 13px; padding-left: -15px">{{$cliente->setor}}</td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Cliente</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th class="bordered destaque align-left">Telefone</th>
                    <th class="bordered destaque">Última Compra Promoção</th>
                    <th class="bordered destaque">Qtd. Compras</th>
                    <th></th>
                </tr>
            @elseif(isset($cliente->cliente))
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$cliente->cliente}}</td>
                    <td class="bordered align-left">{{$cliente->endereco}}</td>
                    <td class="bordered align-left">{{$cliente->telefone}}</td>
                    <td class="bordered">{{$cliente->ultima}}</td>
                    <td class="bordered">{{$cliente->quantidade}}</td>
                    <td></td>
                </tr>
            @endif
            @if($loop->last)
                <tr>
                    <td colspan="6"></td>
                </tr>
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Total Geral</th>
                    <th class="bordered destaque">{{$cliente->total}}</th>
                    <th class="bordered destaque" colspan="2"></th>
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