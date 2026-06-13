@extends('layouts.reportNoBootstrap')

@section('content')
<style>
    .alert-circle {
        border-radius: 8px;
        width: 15px;
        height: 15px;
    }
</style>
<div class="fontSize11">
<!-- {{$first = true}} -->
    @if(isset($giro) && count($giro) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($giro as $gi)
                @if($first)
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Cliente</th>
                        <th class="bordered destaque align-left">Qtde Com.</th>
                        <th class="bordered destaque align-left"></th>
                        <th class="bordered destaque align-left">Meses</th>
                        <th class="bordered destaque align-left">Compras</th>
                        <th class="bordered destaque align-left">Giro</th>
                        <th class="bordered destaque align-left"></th>
                        <th></th>
                    </tr>

                    <!-- {{$first = false}} -->
                @endif
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$gi->nome}}</td>
                    <td class="bordered align-left">{{$gi->qtde_comodato}}</td>
                    <td class="bordered align-left">
                        {{--  --}}
                        <div class="alert-circle" style="background-color: {{$gi->qtde_comodato > $gi->giro ? "red" : "green"}};"></div>
                    </td>
                    <td class="bordered align-left">{{$gi->diff}}</td>
                    <td class="bordered align-left">{{$gi->qtde_compras}}</td>
                    <td class="bordered align-left">{{number_format($gi->giro,2,'.',',')}}</td>
                    <td class="bordered align-left">
                        <div class="alert-circle" style="background-color: {{$gi->giro >= 1 ? "green" : "red"}};"></div>
                    </td>
                    <td></td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
    @endif
</div>
@endsection