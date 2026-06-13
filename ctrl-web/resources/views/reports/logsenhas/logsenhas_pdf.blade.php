@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize12">
<!-- {{$first = true}} -->
    @if(isset($logsenhas) && count($logsenhas) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($logsenhas as $log)
                @if($first)
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Data/Hora</th>
                        <th class="bordered destaque align-left">Usuário</th>
                        <th class="bordered destaque align-left">Tela</th>
                        <th class="bordered destaque align-left">Status</th>
                        <th class="bordered destaque align-left">Motivo</th>
                        <th></th>
                    </tr>

                    <!-- {{$first = false}} -->
                @endif
                <tr>
                    <td></td>
                    <td class="bordered">{{$log->datahora}}</td>
                    <td class="bordered align-left">{{$log->usuario}}</td>
                    <td class="bordered align-left">{{$log->tela}}</td>
                    <td class="bordered align-left">{{$log->status}}</td>
                    <td class="bordered align-left">{{$log->motivo}}</td>
                    <td></td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
    @endif
</div>
@endsection