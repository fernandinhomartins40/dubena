@extends('layouts.reportNoBootstrap')

@section('content')
<style>
@media print{
    .anchor{color:#000;}
    .table-report-menor{margin-top:7px; margin-left: -5px; min-width:100%;}
}
</style>
<div class="fontSize_12">
<!-- {{$first = true}} -->
<!-- {{$header = false}} -->
@if(isset($despesas) && count($despesas) > 0)
    <table class="table-report-menor">
        @foreach($despesas as $despesa)
            @if(isset($despesa->totalplan))
                <tr>
                    <th colspan="2"></th>
                    <th class="bordered destaque align-left">Total</th>
                    <td class="bordered destaque money">{{requestNumeroDecimalOracle($despesa->totalplan)}}</td>
                    <th colspan="2"></th>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="4"></td>
                </tr>
            @elseif(isset($despesa->plano_id))
                    @if($despesa->nivel == 1)
                        <tr>
                            <td colspan="2"></td>
                            <th colspan="2"class="bordered destaque align-left fontSize_13">{{ $despesa->codigo}} - {{$despesa->plano}}</th>
                            <td colspan="2"></td>
                            <!-- {{$header = false}} -->
                        </tr>
                    @else
                        <tr>
                            <td colspan="2"></td>
                            <th colspan="2" class="bordered align-left">&nbsp;&nbsp;&nbsp;{{ $despesa->codigo}} - {{$despesa->plano}}</th>
                            <td colspan="2"></td>
                            <!-- {{$header = false}} -->
                        </tr>
                    @endif
            @elseif(isset($despesa->planoconta_id))
                @if($header)
                    <tr>
                        <th colspan="2"></th>
                        <th class="bordered destaque  align-left">Tipo/SubTipo despesas</th>
                        <th class="bordered destaque money">Valor</th>
                        <th colspan="2"></th>
                    </tr>
                @endif
                <!-- {{$header = false}} -->
                <tr>
                    <td colspan="2"></td>
                    @if($despesa->nivel > 2)
                        @if($despesa->link)
                            <td class="bordered  align-left"><a href="{{url('report.despesas.sub')}}?planoconta={{$despesa->planoconta_id}}&datainicio={{$datainicio}}&datafim={{$datafim}}&hub={{$despesa->tipo}}&id={{$despesa->tipo_id}}"
                                class="anchor">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$despesa->codigo}} - {{$despesa->planoconta}}</a></td>
                        @else
                            <td class="bordered align-left">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$despesa->codigo}} - {{$despesa->planoconta}}</td>
                        @endif
                    @else
                        @if($despesa->link)
                            <td class="bordered  align-left"><a href="{{url('report.despesas.sub')}}?planoconta={{$despesa->planoconta_id}}&datainicio={{$datainicio}}&datafim={{$datafim}}&hub={{$despesa->tipo}}&id={{$despesa->tipo_id}}"
                                class="anchor">&nbsp;&nbsp;&nbsp;{{$despesa->codigo}} - {{$despesa->planoconta}}</a></td>                    
                        @else
                            <td class="bordered align-left">&nbsp;&nbsp;&nbsp;{{$despesa->codigo}} - {{$despesa->planoconta}}</td>
                        @endif
                    @endif
                    <td class="bordered money">{{requestNumeroDecimalOracle($despesa->valor)}}</td>
                    <td colspan="2"></td>
                </tr>
            @endif
            @if ($loop->last)
                <tr>
                    <td></td>
                </tr>
                <tr>
                    <th colspan="2"></th>
                    <th class="bordered destaque align-left">Total Geral</th>
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($despesa->totalgeral)}}</th>
                    <th colspan="2"></th>
                </tr>
            @endif            
        @endforeach
    </table>
@else
    <p style="text-align:center;font-size:12px;"><strong>Nenhum resultado encontrado para estes filtros!</strong></p>
@endif
</div>
@endsection