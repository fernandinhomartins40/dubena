@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_11">
<!-- {{$first = true}} -->
@if(isset($logs) && count($logs) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($logs as $log)
            @if ($loop->first)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Tela</th>
                    <th class="bordered destaque align-left">ID</th>
                    <th class="bordered destaque align-left">Data/Hora</th>
                    <th class="bordered destaque">Usuário</th>
                    <th class="bordered destaque align-left">Campo</th>
                    <th class="bordered destaque align-left">Registro Antigo</th>
                    <th class="bordered destaque align-left">Registro Novo</th>
                    <th></th>
                </tr>
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$log->tela}}</td>
                    <td class="bordered align-left">{{$log->id}}</td>
                    <td class="bordered align-left">{{$log->data}}</td>
                    <td class="bordered">{{$log->usuario}}</td>
                    <td class="bordered align-left">{{$log->campo}}</td>
                    <td class="bordered align-left">{{$log->antigo}}</td>
                    <td class="bordered align-left">{{$log->novo}}</td>
                    <td></td>
                </tr>
            @else
                <tr>
                    <td></td>
                    <td class="bordered align-left">{{$log->tela}}</td>
                    <td class="bordered align-left">{{$log->id}}</td>
                    <td class="bordered align-left">{{$log->data}}</td>
                    <td class="bordered">{{$log->usuario}}</td>
                    <td class="bordered align-left">{{$log->campo}}</td>
                    <td class="bordered align-left">{{$log->antigo}}</td>
                    <td class="bordered align-left">{{$log->novo}}</td>
                    <td></td>
                </tr>
            @endif
            
            
        @endforeach
    </table>
@else
    <p style="text-align:center;font-size:13px;"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection