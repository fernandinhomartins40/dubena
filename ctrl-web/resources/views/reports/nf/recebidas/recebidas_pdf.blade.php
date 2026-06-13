@extends('layouts.reportNoBootstrap')

@section('content')
<!-- {{$first = true}} -->
<div class="font_size12">
    @if(isset($documentos) && count($documentos) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($documentos as $doc)
                @if(isset($doc->quantidade))
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="2">Total</th>
                        <th class="bordered destaque">{{$doc->quantidade}}</th>
                        <th class="bordered destaque" colspan="2"></th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($doc->valor)}}</th>
                        <th></th>
                    </tr>
                @elseif(isset($doc->principal))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="7"><br /><hr /></td>
                        </tr>
                    @endif
                    <!-- {{$first = false}} -->
                    <tr>
                        <td colspan="4" class="negrito" style="font-size: 13px; padding-left: -15px">{{$doc->tipo}}: {{$doc->principal}}</td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">{{$doc->sec}}</th>
                        <th class="bordered destaque align-left">{{$doc->ter}}</th>
                        <th class="bordered destaque">Modelo</th>
                        <th class="bordered destaque">Número</th>
                        <th class="bordered destaque">Série</th>
                        <th class="bordered destaque money">Valor</th>
                        <th></th>
                    </tr>
                @elseif(isset($doc->modelo))
                    <tr>
                        <td></td>
                        <td class="bordered align-left">{{$doc->secundario}}</td>
                        <td class="bordered align-left">{{$doc->terciario}}</td>
                        <td class="bordered">{{$doc->modelo}}</td>
                        <td class="bordered">{{$doc->numero}}</td>
                        <td class="bordered">{{$doc->serie}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($doc->valor)}}</td>
                        <td></td>
                    </tr>
                @endif
                @if ($loop->last && isset($doc->totalquant))
                    <tr>
                        <td colspan="8"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left" colspan="2">Total Geral</th>
                        <th class="bordered destaque">{{$doc->totalquant}}</th>
                        <th class="bordered destaque" colspan="2"></th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($doc->totalgeral)}}</th>
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