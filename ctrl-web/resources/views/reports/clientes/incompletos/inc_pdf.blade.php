@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_11">
<!-- {{$first = true}} -->
@if(isset($clientes) && count($clientes) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($clientes as $cliente)
            @if ($loop->first)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Cód.</th>
                    <th class="bordered destaque align-left">Cliente</th>
                    <th class="bordered destaque">Data Nasc.</th>
                    <th class="bordered destaque align-left">Setor</th>
                    <th class="bordered destaque align-left">Telefone</th>
                    <th></th>
                </tr>
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$cliente->id}}</td>
                    <td class="bordered align-left">{{$cliente->nome}}</td>
                    <td class="bordered">{{$cliente->tipo == 'F' ? requestDataOracle($cliente->data,false) : '--'}}</td>
                    <td class="bordered align-left">{{$cliente->setor}}</td>
                    <td class="bordered align-left">{{$cliente->telefone}}</td>
                    <td></td>
                </tr>
            @else            
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$cliente->id}}</td>
                    <td class="bordered align-left">{{$cliente->nome}}</td>
                    <td class="bordered">{{$cliente->tipo == 'F' ? requestDataOracle($cliente->data,false) : '--'}}</td>
                    <td class="bordered align-left">{{$cliente->setor}}</td>
                    <td class="bordered align-left">{{$cliente->telefone}}</td>
                    <td></td>
                </tr>
            @endif  
            @if ($loop->last)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Total</th>
                    <th class="bordered destaque">{{count($clientes)}}</th>
                    <th class="bordered destaque" colspan="3"></th>
                    <th></th>
                </tr>
            @endif                      
        @endforeach
    </table>
@else
    <p style="text-align:center;font-size:13px;"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection