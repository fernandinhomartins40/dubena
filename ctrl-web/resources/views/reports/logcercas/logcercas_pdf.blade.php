@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize12">
<!-- {{$first = true}} -->
    @if(isset($logcercas) && count($logcercas) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($logcercas as $log)
                @if($log->is_setor)
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="8" class="nobord"><br /><hr /></td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="9" class="top-style fontSize15 nobord">Setor: {{$log->setor}}</td>
                    </tr>

                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Setor</th>
                        <th class="bordered destaque align-left">Cerca</th>
                        <th class="bordered destaque align-left">Colaborador</th>
                        <th class="bordered destaque align-left">Data/Hora Entrada</th>
                        <th class="bordered destaque align-left">Data/Hora Saída</th>
                        <th class="bordered destaque align-left">Tempo (min)</th>
                        <th class="bordered destaque align-left">Coordenadas</th>
                        <th></th>
                    </tr>

                    <!-- {{$first = false}} -->
                @else
                    <tr>
                        <td></td>
                        <td class="bordered align-left">{{$log->setor}}</td>
                        <td class="bordered align-left">{{$log->cerca}}</td>
                        <td class="bordered align-left">{{$log->colaborador}}</td>
                        <td class="bordered align-left">{{$log->entrada}}</td>
                        <td class="bordered align-left">{{$log->saida}}</td>
                        <td class="bordered align-left">{{$log->minutos}}</td>
                        <td class="bordered align-left">{{$log->latitude}} {{$log->longitude}}</td>
                        <td></td>
                    </tr>
                @endif
            @endforeach
        </table>
    @else
        <p style="text-align:center"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
    @endif
</div>
@endsection