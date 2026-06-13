@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
<!-- {{$first = true}} -->
	@if(isset($operacoes) && count($operacoes) > 0)
        <table style="margin-top:7px; margin-left: -5px; min-width:100%;">
            @foreach($operacoes as $op)
                @if(isset($op->quantiatotal))
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Total da Condição</th>
                        <th class="bordered destaque">{{$op->quantiatotal}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($op->vendatotal)}}</th>
                        <th class="bordered destaque money"></th>
                        <th></th>
                    </tr>
                @elseif(isset($op->condicao_id))
                    @if(!$first)
                        <tr style="padding-top: 10px">
                            <td colspan="8" class="nobord"><br /><hr /></td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="8" class="top-style fontSize15 nobord">Condição: {{$op->condicao}}</td>
                    </tr>
                    <!-- {{$first = false}} -->
                    <tr>
                        <th></th>
                        <th class="bordered destaque">Produto</th>
                        <th class="bordered destaque">Quantidade</th>
                        <th class="bordered destaque money">Valor Total</th>
                        <th class="bordered destaque money">Preço Médio</th>
                        <th></th>
                    </tr>
                @elseif(isset($op->produto))
                    <tr>
                        <td></td>
                        <td class="bordered">{{$op->produto}}</td>
                        <td class="bordered">{{$op->quantidade}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($op->valor)}}</td>
                        <td class="bordered money">{{requestNumeroDecimalOracle($op->media)}}</td>
                        <td></td>
                    </tr>
                @endif
                @if($loop->last)
                    <tr>
                        <td colspan="8"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <th class="bordered destaque align-left">Total Geral</th>
                        <th class="bordered destaque">{{$op->totalquantidade}}</th>
                        <th class="bordered destaque money">{{requestNumeroDecimalOracle($op->totalgeral)}}</th>
                        <th class="bordered destaque money"></th>
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