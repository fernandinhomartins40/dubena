@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
<!-- {{$count = 0}} -->
<!-- {{$first = true}} -->
@if(isset($veiculos) && count($veiculos) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($veiculos as $veiculo)
            @if(isset($veiculo->totalrodado))
                <tr>
                    <th></td>
                    <th class="bordered destaque align-left" colspan="4">Total</th>
                    <th class="bordered destaque">{{$veiculo->totalrodado}}</th>
                    <th class="bordered destaque">{{$veiculo->totallitros}}</th>
                    <th class="bordered destaque">{{$veiculo->media}}</th>
                    <th></th>
                </tr>
            @elseif(isset($veiculo->veiculo_id))
                @if(!$first)
                    <tr style="padding-top: 10px">
                        <td colspan="9"><br /><hr /></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="5" class="top-style fontSize15">Veículo: {{$veiculo->placa}} - {{$veiculo->veiculo}}</td>
                </tr>
                <!-- {{$first = false}} -->
                <tr>
                    <th></th>
                    <th class="bordered destaque">Data</th>
                    <th class="bordered destaque">Condutor</th>
                    <th class="bordered destaque">Km Anterior</th>
                    <th class="bordered destaque">Km Atual</th>
                    <th class="bordered destaque">Km Rodado</th>
                    <th class="bordered destaque">Litros</th>
                    <th class="bordered destaque">Média</th>
                    <th></th>
                </tr>
            @elseif(isset($veiculo->colaborador))
                <tr>
                    <td></td>
                    <td class="bordered">{{$veiculo->data}}</td>
                    <td class="bordered">{{$veiculo->colaborador}}</td>
                    <td class="bordered">{{$veiculo->kmanterior}}</td>
                    <td class="bordered">{{$veiculo->kmatual}}</td>
                    <td class="bordered">{{$veiculo->kmrodado}}</td>
                    <td class="bordered">{{$veiculo->litros}}</td>
                    <td class="bordered">{{$veiculo->mediaconsumo}}</td>
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