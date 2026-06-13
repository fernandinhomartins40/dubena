@extends('layouts.reportNoBootstrap')

@section('content')
<style>
@media print{
    .anchor{color:#000;}
    .table-report-menor{margin-top:7px; margin-left: -5px; min-width:100%;}
}
</style>
<div class="fontSize_12">
@if(isset($centros) && count($centros) > 0)
    @if(!$incluido)
        <p class="aviso" style="text-align:center;font-style:italic;font-weight:bold;">Centro de Custos de juros, multas e descontos para baixa não foi definido nas configurações da empresa, portanto não serão mostrados!</p>
    @endif
    <table class="table-report-menor">
        @foreach($centros as $centro)
            @if(isset($centro->total))
                <tr>
                    <th colspan="2"></th>
                    <th class="bordered destaque align-left">Total</th>
                    <td class="bordered destaque money">{{requestNumeroDecimalOracle($centro->total)}}</td>
                    <th colspan="2"></th>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="4"></td>
                </tr>
            @elseif(isset($centro->centro_id))
                    @if($centro->nivel == 1)
                        <tr>
                            <td colspan="2"></td>
                            <th colspan="2"class="bordered destaque align-left fontSize_13">{{ $centro->codigo}} - {{$centro->centro}}</th>
                            <td colspan="2"></td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="2"></td>
                            <th colspan="2" class="bordered align-left">&nbsp;&nbsp;&nbsp;{{ $centro->codigo}} - {{$centro->centro}}</th>
                            <td colspan="2"></td>
                        </tr>
                    @endif
            @elseif(isset($centro->centrocusto_id))
                <tr>
                    <td colspan="2"></td>
                    @if($centro->nivel > 2)
                        @if($centro->link)
                            <td class="bordered  align-left"><a href="{{url('report.despesas_cc.sub')}}?centrocusto={{$centro->centrocusto_id}}&datainicio={{$datainicio}}&datafim={{$datafim}}&hub=1&id={{$empresa}}"
                                class="anchor">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$centro->codigo}} - {{$centro->centro}}</a></td>
                        @else
                            <td class="bordered align-left">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$centro->codigo}} - {{$centro->centro}}</td>
                        @endif
                    @else
                        @if($centro->link)
                            <td class="bordered  align-left"><a href="{{url('report.despesas_cc.sub')}}?centrocusto={{$centro->centrocusto_id}}&datainicio={{$datainicio}}&datafim={{$datafim}}&hub=1&id={{$empresa}}"
                                class="anchor">&nbsp;&nbsp;&nbsp;{{$centro->codigo}} - {{$centro->centro}}</a></td>                    
                        @else
                            <td class="bordered align-left">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$centro->codigo}} - {{$centro->centro}}</td>
                        @endif
                    @endif
                    <td class="bordered money">{{requestNumeroDecimalOracle($centro->valor)}}</td>
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
                    <th class="bordered destaque money">{{requestNumeroDecimalOracle($centro->totalgeral)}}</th>
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