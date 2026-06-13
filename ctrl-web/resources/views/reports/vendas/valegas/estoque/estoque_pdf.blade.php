@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_11">
<!-- {{$first = true}} -->
@if(isset($estoque) && count($estoque) > 0)
    <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
        @foreach($estoque as $vale)
            @if ($loop->first)
                <tr>
                    <th></th>
                    <th class="bordered destaque">Produto</th>
                    <th class="bordered destaque">Cheios</th>
                    <th class="bordered destaque">Vale Gás</th>
                    <th class="bordered destaque">Quantidade a Considerar</th>
                    <th></th>
                </tr>
                <tr>
                    <td></td>
                    <td class="bordered">{{$vale->produto}}</td>
                    <td class="bordered">{{$vale->cheios}}</td>
                    <td class="bordered">{{$vale->valegas}}</td>
                    <td class="bordered">{{$vale->considerar}}</td>
                    <td></td>
                </tr>
            @else            
                <tr>
                    <td></td>
                    <td class="bordered">{{$vale->produto}}</td>
                    <td class="bordered">{{$vale->cheios}}</td>
                    <td class="bordered">{{$vale->valegas}}</td>
                    <td class="bordered">{{$vale->considerar}}</td>
                    <td></td>
                </tr>
            @endif  
            @if ($loop->last)
                <tr>
                    <th></th>
                    <th class="bordered destaque align-left">Total Geral</th>
                    <th class="bordered destaque">{{$estoque->sum('cheios')}}</th>
                    <th class="bordered destaque">{{$estoque->sum('valegas')}}</th>
                    <th class="bordered destaque">{{$estoque->sum('considerar')}}</th>
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