@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize11">
<!-- {{$first = true}} -->
    @if(isset($comodatos) && count($comodatos) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($comodatos as $com)
                @if($first)
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Cliente</th>
                        <th class="bordered destaque align-left">Representante</th>
                        <th class="bordered destaque align-left">Vencimento</th>
                        <th class="bordered destaque align-left">Qtde Itens</th>
                        <th></th>
                    </tr>

                    <!-- {{$first = false}} -->
                @endif
                @php
                    $color = "white";

                    if ($com->ordem == 1) {
                        $color = "#7ed07e";
                    } else if ($com->vencido == 1) {
                        $color = "#c77272";
                    }
                @endphp
                <tr>
                    <td></td>
                    <td class="bordered align-left" style="background-color: {{$color}};">{{$com->nome}}</td>
                    <td class="bordered align-left" style="background-color: {{$color}};">{{$com->representante}}</td>
                    <td class="bordered align-left" style="background-color: {{$color}};">{{requestDataOracle($com->datavencimento, false)}}</td>
                    <td class="bordered align-left" style="background-color: {{$color}};">{{$com->quantidade}}</td>
                    <td></td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
    @endif
</div>
@endsection