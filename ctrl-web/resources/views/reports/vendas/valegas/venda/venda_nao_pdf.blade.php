@extends('layouts.reportNoBootstrap')

@section('content')

<div class="fontSize_11">
@if(isset($clientes) && count($clientes) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($clientes as $cliente)
            @if ($loop->first)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Cliente</th>
                    <th class="bordered destaque align-left">Endereço</th>
                    <th class="bordered destaque align-left">Telefone</th>
                    <th></th>
                </tr>
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$cliente->cliente}}</td>
                    <td class="bordered align-left">{{$cliente->endereco}}</td>
                    <td class="bordered align-left">{{$cliente->telefone}}</td>
                    <td></td>
                </tr>
            @else
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$cliente->cliente}}</td>
                    <td class="bordered align-left">{{$cliente->endereco}}</td>
                    <td class="bordered align-left">{{$cliente->telefone}}</td>
                    <td></td>
                </tr>
            @endif
            @if($loop->last)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left" colspan="2">Total Geral</th>
                    <th class="bordered destaque">{{count($clientes)}}</th>
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