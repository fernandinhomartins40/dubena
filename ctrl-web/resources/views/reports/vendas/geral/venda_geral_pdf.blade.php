@extends('layouts.reportNoBootstrap')

@section('content')

<div class="fontSize14">

<!-- {{$first = true}} -->
<!-- {{$total = 0}} -->
@if(isset($vendas) && count($vendas))

    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach ($vendas as $venda)
            <!-- {{$total += $venda->quant}} -->
            @if ($first)
                <!-- {{$first = false}} -->
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Produto</th>
                    <th class="bordered destaque align-right">Quantidade</th>
                    <th></th>
                </tr>
            @endif

            <tr>
                <td></td>
                <td class="bordered align-left">{{ $venda->produto }}</td>
                <td class="bordered align-right">{{ $venda->quant }}</td>
                <td></td>
            </tr>

            @if ($loop->last)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Total Geral</th>
                    <th class="bordered destaque align-right">{{ $total }}</th>
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